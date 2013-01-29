<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use MWException;

use Wikibase\EntityContent;
use Wikibase\EntityId;
use Wikibase\Entity;
use Wikibase\EntityContentFactory;
use Wikibase\EditEntity;
use Wikibase\Claim;

/**
 * API module for removing qualifiers from a claim.
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
class RemoveQualifiers extends \Wikibase\Api {

	// TODO: automcomment
	// TODO: example
	// TODO: rights
	// TODO: conflict detection
	// TODO: claim uniqueness

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$content = $this->getEntityContent();

		$this->doRemoveQualifiers( $content->getEntity() );

		$this->saveChanges( $content );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @since 0.3
	 *
	 * @return EntityContent
	 */
	protected function getEntityContent() {
		$params = $this->extractRequestParams();

		$entityId = EntityId::newFromPrefixedId( Entity::getIdFromClaimGuid( $params['claim'] ) );
		$entityTitle = EntityContentFactory::singleton()->getTitleForId( $entityId );

		if ( $entityTitle === null ) {
			$this->dieUsage( 'No such entity', 'removequalifiers-entity-not-found' );
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;

		return $this->loadEntityContent( $entityTitle, $baseRevisionId );
	}

	/**
	 * @since 0.3
	 *
	 * @param Entity $entity
	 */
	protected function doRemoveQualifiers( Entity $entity ) {
		$params = $this->extractRequestParams();

		$claim = $this->getClaim( $entity, $params['claim'] );

		$qualifiers = $claim->getQualifiers();

		foreach ( array_unique( $params['qualifiers'] ) as $qualifierHash ) {
			if ( !$qualifiers->hasSnakHash( $qualifierHash ) ) {
				// TODO: does $qualifierHash need to be escaped?
				$this->dieUsage( 'There is no qualifier with hash ' . $qualifierHash, 'removequalifiers-qualifier-not-found' );
			}

			$qualifiers->removeSnakHash( $qualifierHash );
		}
	}

	/**
	 * @since 0.3
	 *
	 * @param Entity $entity
	 * @param string $claimGuid
	 *
	 * @return Claim
	 */
	protected function getClaim( Entity $entity, $claimGuid ) {
		if ( !$entity->getClaims()->hasClaimWithGuid( $claimGuid ) ) {
			$this->dieUsage( 'No such claim', 'removequalifiers-claim-not-found' );
		}

		$claim = $entity->getClaims()->getClaimWithGuid( $claimGuid );

		assert( $claim instanceof Claim );

		return $claim;
	}

	/**
	 * @since 0.3
	 *
	 * @param EntityContent $content
	 */
	protected function saveChanges( EntityContent $content ) {
		$params = $this->extractRequestParams();

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$baseRevisionId = $baseRevisionId > 0 ? $baseRevisionId : false;
		$editEntity = new EditEntity( $content, $this->getUser(), $baseRevisionId, $this->getContext() );

		$status = $editEntity->attemptSave(
			'', // TODO: automcomment
			EDIT_UPDATE,
			isset( $params['token'] ) ? $params['token'] : false
		);

		if ( !$status->isOk() ) {
			$this->dieUsage( 'Failed to save the change', 'save-failed' );
		}

		$statusValue = $status->getValue();

		if ( isset( $statusValue['revision'] ) ) {
			$this->getResult()->addValue(
				'pageinfo',
				'lastrevid',
				(int)$statusValue['revision']->getId()
			);
		}
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
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'qualifiers' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => true,
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
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'claim' => 'A GUID identifying the claim from which to remove qualifiers',
			'qualifiers' => 'Snak hashes of the querliers to remove',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array(
				'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
		);
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
			'API module for removing a qualifier from a claim.'
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
	 * @see ApiBase::getHelpUrls
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbremovequalifiers';
	}

	/**
	 * @see ApiBase::getVersion
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . '-' . WB_VERSION;
	}

	/**
	 * @see ApiBase::needsToken
	 *
	 * @return bool true
	 */
	public function needsToken() {
		return true;
	}

	/**
	 * @see ApiBase::isWriteMode
	 *
	 * @return bool true
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::mustBePosted
	 *
	 * @return bool true
	 */
	public function mustBePosted() {
		return true;
	}

}
