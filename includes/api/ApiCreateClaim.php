<?php

namespace Wikibase;
use ApiBase, MWException;

/**
 * API module for creating claims.
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
 * @since 0.2
 *
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApiCreateClaim extends Api implements ApiAutocomment {

	// TODO: automcomment
	// TODO: example
	// TODO: rights
	// TODO: conflict detection

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.2
	 */
	public function execute() {
		wfProfileIn( "Wikibase-" . __METHOD__ );

		$this->checkParameterRequirements();

		$claim = $this->addClaim();

		$this->outputClaim( $claim );

		wfProfileOut( "Wikibase-" . __METHOD__ );
	}

	/**
	 * Constructs a new Claim based on the arguments provided to the API,
	 * adds it to the Entity and saves it.
	 *
	 * On success, the added Claim is returned. Else null is returned.
	 *
	 * @since 0.2
	 *
	 * @return Claim
	 * @throws MWException
	 */
	protected function addClaim() {
		wfProfileIn( "Wikibase-" . __METHOD__ );

		$entityContent = $this->getEntityContent();

		if ( $entityContent === null ) {
			$this->dieUsage( 'Entity not found, snak not created', 'entity-not-found' );
		}

		/**
		 * @var Entity $entity
		 */
		$entity = $entityContent->getEntity();

		// It is possible we get an exception from this method because of specifying
		// a non existing-property, specifying an entity id for an entity with wrong
		// entity type or providing an invalid DataValue.
		try {
			$snak = $this->getSnakInstance();
		}
		catch ( \Exception $ex ) {
			wfProfileOut( "Wikibase-" . __METHOD__ );
			$this->dieUsageMsg( $ex->getMessage() );
		}

		$claim = $entity->newClaim( $snak );

		$entity->addClaim( $claim );

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$baseRevisionId = $baseRevisionId > 0 ? $baseRevisionId : false;
		$editEntity = new EditEntity( $entityContent, $this->getUser(), $baseRevisionId );

		$editEntity->attemptSave(
			Autocomment::buildApiSummary( $this, null, $entityContent ),
			EDIT_UPDATE,
			isset( $params['token'] ) ? $params['token'] : false
		);

		wfProfileOut( "Wikibase-" . __METHOD__ );
		return $claim;
	}

	/**
	 * Checks if the required parameters are set and the ones that make no sense given the
	 * snaktype value are not set.
	 *
	 * @since 0.2
	 */
	protected function checkParameterRequirements() {
		$params = $this->extractRequestParams();

		if ( $params['snaktype'] == 'value' XOR isset( $params['value'] ) ) {
			if ( $params['snaktype'] == 'value' ) {
				$this->dieUsage( 'A value needs to be provided when creating a claim with PropertyValueSnak snak', 'claim-value-missing' );
			}
			else {
				$this->dieUsage( 'You cannot provide a value when creating a claim with no PropertyValueSnak as main snak', 'claim-value-set' );
			}
		}

		if ( !isset( $params['property'] ) ) {
			$this->dieUsage( 'A property ID needs to be provided when creating a claim with a Snak', 'claim-property-id-missing' );
		}
	}

	/**
	 * @since 0.2
	 *
	 * @return EntityContent|null
	 */
	protected function getEntityContent() {
		$params = $this->extractRequestParams();

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$entityTitle = EntityContentFactory::singleton()->getTitleForId( EntityId::newFromPrefixedId( $params['entity'] ) );

		return $entityTitle === null ? null : $this->loadEntityContent( $entityTitle, $baseRevisionId );
	}

	/**
	 * @since 0.2
	 *
	 * @return Snak
	 * @throws MWException
	 */
	protected function getSnakInstance() {
		$params = $this->extractRequestParams();

		$propertyId = EntityId::newFromPrefixedId( $params['property'] );

		if ( $propertyId->getEntityType() !== Property::ENTITY_TYPE ) {
			throw new MWException( 'Expected an EntityId of a property' );
		}

		switch ( $params['snaktype'] ) {
			case 'value':
				$snak = PropertyValueSnak::newFromPropertyValue( $propertyId, $params['value'] );
				break;
			case 'novalue':
				$snak = new PropertyNoValueSnak( $propertyId );
				break;
			case 'somevalue':
				$snak = new PropertySomeValueSnak( $propertyId );
				break;
		}

		if ( !isset( $snak ) ) {
			throw new MWException( '$snak was not set to an instance of Snak' );
		}

		return $snak;
	}

	/**
	 * @since 0.3
	 *
	 * @param Claim $claim
	 */
	protected function outputClaim( Claim $claim ) {
		$serializer = new ClaimSerializer();
		$serializer->getOptions()->setIndexTags( $this->getResult()->getIsRawMode() );

		$this->getResult()->addValue(
			null,
			'claim',
			$serializer->getSerialized( $claim )
		);
	}

	/**
	 * @see ApiBase::getAllowedParams
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'entity' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'snaktype' => array(
				ApiBase::PARAM_TYPE => array( 'value', 'novalue', 'somevalue' ),
				ApiBase::PARAM_REQUIRED => true,
			),
			'property' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			),
			'value' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			),
			'token' => null,
			'baserevid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
		);
	}

	/**
	 * @see ApiBase::getParamDescription
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'entity' => 'Id of the entity you are adding the claim to',
			'property' => 'Id of the property when creating a claim with a Snak',
			'value' => 'Value of the snak when creating a claim with a snak that has a value',
			'snaktype' => 'The type of the snak',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
		);
	}

	/**
	 * @see ApiBase::getDescription
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for creating Wikibase claims.'
		);
	}

	/**
	 * @see ApiBase::getExamples
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			// 'ex' => 'desc'
		);
	}

	/**
	 * @see ApiBase::getHelpUrls
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbcreateclaim';
	}

	/**
	 * @see ApiBase::getVersion
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . '-' . WB_VERSION;
	}

	/**
	 * @see  ApiAutocomment::getTextForComment()
	 */
	public function getTextForComment( array $params, $plural = 1 ) {
		return Autocomment::formatAutoComment(
			'wbcreateclaim-' . $params['snaktype'],
			array(
				/*plural */ (int)isset( $params['value'] ) + (int)isset( $params['property'] )
			)
		);
	}

	/**
	 * @see  ApiAutocomment::getTextForSummary()
	 */
	public function getTextForSummary( array $params ) {
		return Autocomment::formatAutoSummary(
			Autocomment::pickValuesFromParams( $params, 'property', 'value' )
		);
	}

}
