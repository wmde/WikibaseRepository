<?php

namespace Wikibase\Api;

use ApiBase;
use MWException;

use Wikibase\EntityContent;
use Wikibase\EntityId;
use Wikibase\Entity;
use Wikibase\EntityContentFactory;
use Wikibase\EditEntity;
use Wikibase\Statement;
use Wikibase\References;

/**
 * API module for removing one or more references of the same statement.
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
class RemoveReferences extends \Wikibase\Api {

	// TODO: automcomment
	// TODO: example
	// TODO: rights
	// TODO: conflict detection

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( "Wikibase-" . __METHOD__ );

		$content = $this->getEntityContent();
		$params = $this->extractRequestParams();

		$this->removeReferences(
			$content->getEntity(),
			$params['statement'],
			array_unique( $params['references'] )
		);

		$this->saveChanges( $content );

		wfProfileOut( "Wikibase-" . __METHOD__ );
	}

	/**
	 * @since 0.3
	 *
	 * @return EntityContent
	 */
	protected function getEntityContent() {
		$params = $this->extractRequestParams();

		$entityId = EntityId::newFromPrefixedId( Entity::getIdFromClaimGuid( $params['statement'] ) );
		$entityTitle = EntityContentFactory::singleton()->getTitleForId( $entityId );

		if ( $entityTitle === null ) {
			$this->dieUsage( 'No such entity', 'removereferences-entity-not-found' );
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;

		return $this->loadEntityContent( $entityTitle, $baseRevisionId );
	}

	/**
	 * @since 0.3
	 *
	 * @param Entity $entity
	 * @param string $statementGuid
	 * @param string[] $refHash
	 */
	protected function removeReferences( Entity $entity, $statementGuid, array $refHashes ) {
		if ( !$entity->getClaims()->hasClaimWithGuid( $statementGuid ) ) {
			$this->dieUsage( 'No such statement', 'removereferences-statement-not-found' );
		}

		$statement = $entity->getClaims()->getClaimWithGuid( $statementGuid );

		if ( ! ( $statement instanceof Statement ) ) {
			$this->dieUsage(
				'The referenced claim is not a statement and thus cannot have references',
				'removereferences-not-a-statement'
			);
		}

		/**
		 * @var References $references
		 */
		$references = $statement->getReferences();

		foreach ( $refHashes as $refHash ) {
			// TODO: perhaps we do not want to fail like this, as client cannot easily find which ref is not there
			if ( $references->hasReferenceHash( $refHash ) ) {
				$references->removeReferenceHash( $refHash );
			}
			else {
				$this->dieUsage(
					// TODO: does $refHash need to be escaped somehow?
					'The statement does not have any associated reference with the provided reference hash "' . $refHash . '"',
					'removereferences-no-such-reference'
				);
			}
		}
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
		$editEntity = new EditEntity( $content, $this->getUser(), $baseRevisionId );

		$status = $editEntity->attemptSave(
			'', // TODO: automcomment
			EDIT_UPDATE,
			isset( $params['token'] ) ? $params['token'] : false
		);

		if ( !$status->isGood() ) {
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
			'statement' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'references' => array(
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
			'statement' => 'A GUID identifying the statement for which a reference is being set',
			'references' => 'The hashes of the references that should be removed',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
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
			'API module for removing one or more references of the same statement.'
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
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetclaimvalue';
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

}