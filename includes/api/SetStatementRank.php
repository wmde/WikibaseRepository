<?php

namespace Wikibase\Api;

use ApiBase, MWException;

use Wikibase\EntityId;
use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\EntityContentFactory;
use Wikibase\Statement;
use Wikibase\Settings;

use Wikibase\Lib\Serializers\ClaimSerializer;

/**
 * API module for setting the rank of a statement
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
class SetStatementRank extends ApiWikibase {

	// TODO: automcomment
	// TODO: example
	// TODO: rights
	// TODO: conflict detection

	public function __construct( $mainModule, $moduleName, $modulePrefix = '' ) {
		//NOTE: need to declare this constructor, so old PHP versions don't use the
		//      setStatementRank() function as the constructor.
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$content = $this->getEntityContent();
		$params = $this->extractRequestParams();

		$statement = $this->setStatementRank(
			$content->getEntity(),
			$params['statement'],
			$params['rank']
		);

		$this->saveChanges( $content );

		$this->outputStatement( $statement );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @since 0.3
	 *
	 * @return \Wikibase\EntityContent
	 */
	protected function getEntityContent() {
		$params = $this->extractRequestParams();

		$entityId = EntityId::newFromPrefixedId( Entity::getIdFromClaimGuid( $params['statement'] ) );
		$entityTitle = EntityContentFactory::singleton()->getTitleForId( $entityId );

		if ( $entityTitle === null ) {
			$this->dieUsage( 'No such entity', 'setstatementrank-entity-not-found' );
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;

		return $this->loadEntityContent( $entityTitle, $baseRevisionId );
	}

	/**
	 * @since 0.3
	 *
	 * @param Entity $entity
	 * @param string $statementGuid
	 * @param string $rank
	 *
	 * @return \Wikibase\Statement
	 */
	protected function setStatementRank( Entity $entity, $statementGuid, $rank ) {
		$claims = new \Wikibase\Claims( $entity->getClaims() );

		if ( !$claims->hasClaimWithGuid( $statementGuid ) ) {
			$this->dieUsage( 'No such statement', 'setstatementrank-statement-not-found' );
		}

		$statement = $claims->getClaimWithGuid( $statementGuid );

		if ( ! ( $statement instanceof Statement ) ) {
			$this->dieUsage(
				'The referenced claim is not a statement and thus does not have a rank',
				'setstatementrank-not-a-statement'
			);
		}

		$statement->setRank( ClaimSerializer::unserializeRank( $rank ) );

		$entity->setClaims( $claims );

		return $statement;
	}

	/**
	 * @since 0.3
	 *
	 * @param \Wikibase\EntityContent $content
	 */
	protected function saveChanges( EntityContent $content ) {
		$params = $this->extractRequestParams();

		$user = $this->getUser();
		$flags = 0;
		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$baseRevisionId = $baseRevisionId > 0 ? $baseRevisionId : false;
		$flags |= ( $user->isAllowed( 'bot' ) && $params['bot'] ) ? EDIT_FORCE_BOT : 0;
		$flags |= EDIT_UPDATE;
		$editEntity = new \Wikibase\EditEntity( $content, $user, $baseRevisionId, $this->getContext() );

		$status = $editEntity->attemptSave(
			'', // TODO: automcomment
			$flags,
			isset( $params['token'] ) ? $params['token'] : ''
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
	 * @since 0.3
	 *
	 * @param \Wikibase\Statement $statement
	 */
	protected function outputStatement( Statement $statement ) {
		$serializerFactory = new \Wikibase\Lib\Serializers\SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $statement );

		$serializer->getOptions()->setIndexTags( $this->getResult()->getIsRawMode() );

		$this->getResult()->addValue(
			null,
			'statement',
			$serializer->getSerialized( $statement )
		);
	}

	/**
	 * @see \ApiBase::getAllowedParams
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
			'rank' => array(
				ApiBase::PARAM_TYPE => ClaimSerializer::getRanks(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'token' => null,
			'baserevid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'bot' => null,
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
			'statement' => 'A GUID identifying the statement for which to set the rank',
			'rank' => 'The new value to set for the rank',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),
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
			'API module for setting the rank of a Wikibase statement.'
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
			// TODO
			// 'ex' => 'desc'
		);
	}

	/**
	 * @see \ApiBase::getHelpUrls
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetstatementrank';
	}

	/**
	 * @see \ApiBase::getVersion
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . '-' . WB_VERSION;
	}

	/**
	 * @see \ApiBase::needsToken()
	 */
	public function needsToken() {
		return Settings::get( 'apiInDebug' ) ? Settings::get( 'apiDebugWithTokens' ) : true;
	}

	/**
	 * @see \ApiBase::mustBePosted()
	 */
	public function mustBePosted() {
		return Settings::get( 'apiInDebug' ) ? Settings::get( 'apiDebugWithPost' ) : true;
	}

	/**
	 * @see \ApiBase::isWriteMode()
	 */
	public function isWriteMode() {
		return true;
	}

}
