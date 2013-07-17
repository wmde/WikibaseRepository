<?php

namespace Wikibase\Api;

use ApiBase;
use MWException;

use Wikibase\Lib\ClaimGuidValidator;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Lib\EntityIdParser;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\EntityId;
use Wikibase\Entity;
use Wikibase\EntityContentFactory;
use Wikibase\Property;
use Wikibase\Statement;
use Wikibase\Claims;
use Wikibase\Claim;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for getting claims.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.3
 *
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GetClaims extends ApiWikibase {

	// TODO: rights
	// TODO: conflict detection

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		//@todo validate
		//@todo check permissions

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		list( $id, $claimGuid ) = $this->getIdentifiers( $params );

		$entityId = EntityId::newFromPrefixedId( $id );
		$entity = $entityId ? $this->getEntity( $entityId ) : null;

		if ( !$entity ) {
			$this->dieUsage( "No entity found matching ID $id", 'no-such-entity' );
		}

		$this->outputClaims( $this->getClaims( $entity, $claimGuid ) );

		wfProfileOut( __METHOD__ );
	}

	protected function validateParameters( array $params ) {
		if ( !isset( $params['entity'] ) && !isset( $params['claim'] ) ) {
			$this->dieUsage( 'Either the entity parameter or the claim parameter need to be set', 'param-missing' );
		}
	}

	/**
	 * @see \ApiBase::getPossibleErrors()
	 * @return array
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'no-such-entity', 'info' => $this->msg( 'wikibase-api-no-such-entity' )->text()  ),
			array( 'code' => 'param-missing', 'info' => $this->msg( 'wikibase-api-param-missing' )->text() ),
			array( 'code' => 'param-illegal', 'info' => $this->msg( 'wikibase-api-param-illegal' )->text() ),
		) );
	}

	/**
	 * @since 0.3
	 *
	 * @param array $claims
	 * @param \Wikibase\Claim[] $claims
	 */
	protected function outputClaims( array $claims ) {
		$claims = new Claims( $claims );

		$serializerFactory = new SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $claims );

		// TODO: hold into account props parameter
		$serializer->getOptions()->setIndexTags( $this->getResult()->getIsRawMode() );

		$serializedClaims = $serializer->getSerialized( $claims );

		$this->getResult()->addValue(
			null,
			'claims',
			$serializedClaims
		);
	}

	/**
	 * @since 0.3
	 *
	 * @param EntityId $id
	 *
	 * @return Entity
	 */
	protected function getEntity( EntityId $id ) {
		$content = EntityContentFactory::singleton()->getFromId( $id );

		if ( $content === null ) {
			$this->dieUsage( 'The specified entity does not exist, so it\'s claims cannot be obtained', 'no-such-entity' );
		}

		return $content->getEntity();
	}

	/**
	 * @since 0.3
	 *
	 * @param Entity $entity
	 * @param null|string $claimGuid
	 *
	 * @return Claim[]
	 */
	protected function getClaims( Entity $entity, $claimGuid ) {
		$claimsList = new Claims( $entity->getClaims() );

		if ( $claimGuid !== null ) {
			return $claimsList->hasClaimWithGuid( $claimGuid ) ?
				array( $claimsList->getClaimWithGuid( $claimGuid ) ) : array();
		}

		$claims = array();

		/** @var \Wikibase\Claim $claim */
		foreach ( $claimsList as $claim ) {
			if ( $this->claimMatchesFilters( $claim ) ) {
				$claims[] = $claim;
			}
		}

		return $claims;
	}

	protected function claimMatchesFilters( Claim $claim ) {
		return $this->rankMatchesFilter( $claim->getRank() )
			&& $this->propertyMatchesFilter( $claim->getPropertyId() );
	}

	protected function rankMatchesFilter( $rank ) {
		if ( $rank === null ) {
			return true;
		}
		$params = $this->extractRequestParams();

		if( isset( $params['rank'] ) ){
			$unserializedRank = ClaimSerializer::unserializeRank( $params['rank'] );
			$matchFilter = $rank === $unserializedRank;
			return $matchFilter;
		}

		return true;
	}

	protected function propertyMatchesFilter( EntityId $propertyId ) {
		$params = $this->extractRequestParams();

		if ( isset( $params['property'] ) ){
			$parsedProperty = WikibaseRepo::getDefaultInstance()->getEntityIdParser()->parse( $params['property'] );
			$matchFilter = $propertyId->equals( $parsedProperty );
			return $matchFilter;
		}

		return true;
	}

	/**
	 * Obtains the id of the entity for which to obtain claims and the claim GUID
	 * in case it was also provided.
	 *
	 * @since 0.3
	 *
	 * @param $params
	 * @return array
	 * First element is a prefixed entity id
	 * Second element is either null or a claim GUID
	 */
	protected function getIdentifiers( $params ) {

		$claimGuid = null;

		// @todo handle the settings in a more generalized way for all the api modules
		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$entityPrefixes = $settings->getSetting( 'entityPrefixes' );
		$claimGuidValidator = new ClaimGuidValidator( $entityPrefixes );

		if ( isset( $params['claim'] ) && $claimGuidValidator->validateFormat( $params['claim'] ) === false ) {
			$this->dieUsage( 'Invalid claim guid' , 'invalid-guid' );
		}

		if ( isset( $params['entity'] ) && isset( $params['claim'] ) ) {
			$entityId = Entity::getIdFromClaimGuid( $params['claim'] );

			if ( $entityId !== $params['entity'] ) {
				$this->dieUsage( 'If both entity id and claim key are provided they need to point to the same entity', 'param-illegal' );
			}
		}
		else if ( isset( $params['entity'] ) ) {
			$entityId = $params['entity'];
		}
		else {
			$entityId = Entity::getIdFromClaimGuid( $params['claim'] );
			$claimGuid = $params['claim'];
		}

		return array( $entityId, $claimGuid );
	}

	/**
	 * @see ApiBase::getAllowedParams
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'entity' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'property' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'rank' => array(
				ApiBase::PARAM_TYPE => ClaimSerializer::getRanks(),
			),
			'props' => array(
				ApiBase::PARAM_TYPE => array(
					'references',
				),
				ApiBase::PARAM_DFLT => 'references',
			),
		);
	}

	/**
	 * @see \ApiBase::getParamDescription
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'entity' => 'Id of the entity from which to obtain claims. Required unless key is provided.',
			'property' => 'Optional filter to only return claims with a main snak that has the specified property.',
			'claim' => 'A GUID identifying the claim. Required unless entity is provided.',
			'rank' => 'Optional filter to return only the claims that have the specified rank',
			'props' => 'Some parts of the claim are returned optionally. This parameter controls which ones are returned.',
		);
	}

	/**
	 * @see \ApiBase::getDescription
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for getting Wikibase claims.'
		);
	}

	/**
	 * @see \ApiBase::getExamples
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			"api.php?action=wbgetclaims&entity=q42" => "Get claims for item with ID q42",
			"api.php?action=wbgetclaims&entity=q42&property=p2" => "Get claims for item with ID q42 and property with ID p2",
			"api.php?action=wbgetclaims&entity=q42&rank=normal" => "Get claims for item with ID q42 that are ranked as normal",
			'api.php?action=wbgetclaims&claim=q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F' => "Get claim with GUID of q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F",
		);
	}

}
