<?php

namespace Wikibase;
use ApiBase, MWException;

/**
 * API module for removing claims.
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
 * @author Daniel Kinzler
 */
class ApiRemoveClaims extends ApiModifyClaim {

	// TODO: example
	// TODO: rights
	// TODO: conflict detection

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$guids = $this->getGuidsByEntity();

		$removedClaimKeys = $this->removeClaims(
			$this->getEntityContents( array_keys( $guids ) ),
			$guids
		);

		$this->outputResult( $removedClaimKeys );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Parses the key parameter and returns it as an array with as keys
	 * prefixed entity ids and as values arrays with the claim GUIDs for
	 * the specific entity.
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	protected function getGuidsByEntity() {
		$params = $this->extractRequestParams();

		$guids = array();

		foreach ( $params['claim'] as $guid ) {
			$entityId = Entity::getIdFromClaimGuid( $guid );

			if ( !array_key_exists( $entityId, $guids ) ) {
				$guids[$entityId] = array();
			}

			$guids[$entityId][] = $guid;
		}

		return $guids;
	}

	/**
	 * Does the claim removal and returns a list of claim keys for
	 * the claims that actually got removed.
	 *
	 * @since 0.3
	 *
	 * @param EntityContent[] $entityContents
	 * @param array $guids
	 *
	 * @return string[]
	 */
	protected function removeClaims( $entityContents, array $guids ) {
		$removedClaims = array();

		foreach ( $entityContents as $entityContent ) {
			$entity = $entityContent->getEntity();

			$claims = new Claims( $entity->getClaims() );

			$removedClaims = array_merge(
				$removedClaims,
				$this->removeClaimsFromList( $claims, $guids[$entity->getPrefixedId()] )
			);

			$entity->setClaims( $claims );
			$this->saveChanges( $entityContent );
		}

		return $removedClaims;
	}

	/**
	 * @since 0.3
	 *
	 * @param string[] $ids
	 *
	 * @return EntityContent[]
	 */
	protected function getEntityContents( array $ids ) {
		$contents = array();

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;

		// TODO: use proper batch select
		foreach ( $ids as $id ) {
			$entityId = EntityId::newFromPrefixedId( $id );

			if ( $entityId === null ) {
				$this->dieUsage( 'Invalid entity id provided', 'removeclaims-invalid-entity-id' );
			}

			$entityTitle = EntityContentFactory::singleton()->getTitleForId( $entityId );

			$content = $this->loadEntityContent( $entityTitle, $baseRevisionId );

			if ( $content === null ) {
				$this->dieUsage( "The specified entity does not exist, so it's claims cannot be obtained", 'removeclaims-entity-not-found' );
			}

			$contents[] = $content;
		}

		return $contents;
	}

	/**
	 * @since 0.3
	 *
	 * @param Claims $claims
	 * @param string[] $guids
	 *
	 * @return string[]
	 */
	protected function removeClaimsFromList( Claims &$claims, array $guids ) {
		$removedGuids = array();

		foreach ( $guids as $guid ) {
			if ( $claims->hasClaimWithGuid( $guid ) ) {
				$claims->removeClaimWithGuid( $guid );
				$removedGuids[] = $guid;
			}
		}

		return $removedGuids;
	}

	/**
	 * @since 0.3
	 *
	 * @param string[] $removedClaimGuids
	 */
	protected function outputResult( $removedClaimGuids ) {
		$this->getResult()->addValue(
			null,
			'success',
			1
		);

		$this->getResult()->setIndexedTagName( $removedClaimGuids, 'claim' );

		$this->getResult()->addValue(
			null,
			'claims',
			$removedClaimGuids
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
		return array_merge( parent::getAllowedParams(), array(
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_REQUIRED => true,
			),
		) );
	}

	/**
	 * @see ApiBase::getParamDescription
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'claim' => 'A GUID identifying the claim',
		) );
	}

	/**
	 * @see ApiBase::getDescription
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for removing Wikibase claims.'
		);
	}

	/**
	 * @see ApiBase::getExamples
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			// TODO
			// 'ex' => 'desc'
		);
	}

	/**
	 * @see  ApiAutocomment::getTextForComment()
	 */
	public function getTextForComment( array $params, $plural = 1 ) {
		$guids = $params['claim'];

		return Autocomment::formatAutoComment(
			$this->getModuleName(),
			array(
				/*plural */ count( $guids ),
			)
		);
	}

	/**
	 * @see  ApiAutocomment::getTextForSummary()
	 */
	public function getTextForSummary( array $params ) {
		return Autocomment::formatAutoSummary(
			Autocomment::pickValuesFromParams( $params, 'claim' )
		);
	}
}
