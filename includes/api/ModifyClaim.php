<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use Wikibase\DataModel\Claim\ClaimGuidParser;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Base class for modifying claims.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class ModifyClaim extends ApiWikibase {

	/**
	 * @since 0.4
	 *
	 * @var ClaimModificationHelper
	 */
	protected $claimModificationHelper;

	/**
	 * @since 0.5
	 *
	 * @var ClaimGuidParser
	 */
	protected $claimGuidParser;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$this->claimModificationHelper = new ClaimModificationHelper(
			WikibaseRepo::getDefaultInstance()->getSnakConstructionService(),
			WikibaseRepo::getDefaultInstance()->getEntityIdParser(),
			WikibaseRepo::getDefaultInstance()->getClaimGuidValidator(),
			$this->getErrorReporter()
		);

		$this->claimGuidParser = WikibaseRepo::getDefaultInstance()->getClaimGuidParser();
	}

	/**
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param Summary $summary
	 */
	public function saveChanges( Entity $entity, Summary $summary ) {
		$status = $this->attemptSaveEntity(
			$entity,
			$summary,
			$this->getFlags()
		);

		//@todo this doesnt belong here!...
		$this->getResultBuilder()->addRevisionIdFromStatusToResult( $status, 'pageinfo' );
	}

	/**
	 * @see ApiBase::isWriteMode
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @since 0.4
	 *
	 * @return integer
	 */
	protected function getFlags() {
		$flags = EDIT_UPDATE;

		$params = $this->extractRequestParams();
		$flags |= ( $this->getUser()->isAllowed( 'bot' ) && $params['bot'] ) ? EDIT_FORCE_BOT : 0;

		return $flags;
	}

	/**
	 * @see \ApiBase::getAllowedParams
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'summary' => array( ApiBase::PARAM_TYPE => 'string' ),
				'token' => null,
				'baserevid' => array(
					ApiBase::PARAM_TYPE => 'integer',
				),
				'bot' => false,
			)
		);
	}

	/**
	 * @see ApiBase::getParamDescription()
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			array(
				'summary' => array(
					'Summary for the edit.',
					"Will be prepended by an automatically generated comment. The length limit of the
					autocomment together with the summary is 260 characters. Be aware that everything above that
					limit will be cut off."
				),
				'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
				'baserevid' => array(
					'The numeric identifier for the revision to base the modification on.',
					"This is used for detecting conflicts during save."
				),
				'bot' => array(
					'Mark this edit as bot',
					'This URL flag will only be respected if the user belongs to the group "bot".'
				),
			)
		);
	}

}
