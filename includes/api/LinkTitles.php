<?php

namespace Wikibase\Api;

use ApiBase, Status;
use SiteSQLStore;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\EntityContent;
use Wikibase\ItemContent;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\StoreFactory;
use Wikibase\Summary;

/**
 * API module to associate two pages on two different sites with a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Adam Shorland
 */
class LinkTitles extends ApiWikibase {

	/**
	 * @var SiteLinkTargetProvider
	 */
	private $siteLinkTargetProvider;

	/**
	 * @since 0.5
	 *
	 * @var array
	 */
	protected $siteLinkGroups;

	public function __construct( $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->siteLinkTargetProvider = new SiteLinkTargetProvider( SiteSQLStore::newInstance() );
		$this->siteLinkGroups = WikibaseRepo::getDefaultInstance()->
			getSettings()->getSetting( 'siteLinkGroups' );
	}

	/**
	 * @see  \Wikibase\Api\ApiWikiBase::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( EntityContent $entityContent, array $params ) {
		$permissions = parent::getRequiredPermissions( $entityContent, $params );
		$permissions[] = 'linktitles-update';
		return $permissions;
	}

	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		// Sites are already tested through allowed params ;)
		$sites = $this->siteLinkTargetProvider->getSiteList( $this->siteLinkGroups );
		$fromSite = $sites->getSite( $params['fromsite'] );
		$toSite = $sites->getSite( $params['tosite'] );

		$fromPage = $fromSite->normalizePageName( $params['fromtitle'] );
		$this->validatePage( $fromPage, 'from' );
		$toPage = $toSite->normalizePageName( $params['totitle'] );
		$this->validatePage( $toPage, 'to' );

		$siteLinkCache = StoreFactory::getStore()->newSiteLinkCache();
		$fromId = $siteLinkCache->getItemIdForLink( $fromSite->getGlobalId(), $fromPage );
		$toId = $siteLinkCache->getItemIdForLink( $toSite->getGlobalId(), $toPage );

		$return = array();
		$flags = 0;
		$itemContent = null;

		$summary = new Summary( $this->getModuleName() );
		$summary->addAutoSummaryArgs(
			$fromSite->getGlobalId() . ':' . $fromPage,
			$toSite->getGlobalId() . ':' . $toPage );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		// Figure out which parts to use and what to create anew
		if ( !$fromId && !$toId ) {
			// create new item
			$itemContent = ItemContent::newEmpty();
			$toLink = new SiteLink( $toSite->getGlobalId(), $toPage );
			$itemContent->getItem()->addSiteLink( $toLink );
			$return[] = $toLink;
			$fromLink = new SiteLink( $fromSite->getGlobalId(), $fromPage );
			$itemContent->getItem()->addSiteLink( $fromLink );
			$return[] = $fromLink;

			$flags |= EDIT_NEW;
			$summary->setAction( 'create' ); //FIXME: i18n
		}
		elseif ( !$fromId && $toId ) {
			// reuse to-site's item
			/** @var ItemContent $itemContent */
			$itemContent = $entityContentFactory->getFromId( ItemId::newFromNumber( $toId ) );
			$fromLink = new SiteLink( $fromSite->getGlobalId(), $fromPage );
			$itemContent->getItem()->addSiteLink( $fromLink );
			$return[] = $fromLink;
			$summary->setAction( 'connect' );
		}
		elseif ( $fromId && !$toId ) {
			// reuse from-site's item
			/** @var ItemContent $itemContent */
			$itemContent = $entityContentFactory->getFromId( ItemId::newFromNumber( $fromId ) );
			$toLink = new SiteLink( $toSite->getGlobalId(), $toPage );
			$itemContent->getItem()->addSiteLink( $toLink );
			$return[] = $toLink;
			$summary->setAction( 'connect' );
		}
		elseif ( $fromId === $toId ) {
			// no-op
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Common item detected, sitelinks are both on the same item', 'common-item' );
		}
		else {
			// dissimilar items
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'No common item detected, unable to link titles' , 'no-common-item' );
		}

		$this->getResultBuilder()->addSiteLinks( $return, 'entity' );
		$status = $this->getAttemptSaveStatus( $itemContent, $summary, $flags );
		$this->buildResult( $itemContent, $status );
		wfProfileOut( __METHOD__ );
	}

	/**
	 * @param string $page
	 * @param string $label
	 */
	private function validatePage( $page, $label ) {
		if ( $page === false ) {
			$this->dieUsage(
				"The external client site did not provide page information for the {$label} page" ,
				'no-external-page'
			);
		}
	}

	/**
	 * @param ItemContent $itemContent
	 * @param Summary $summary
	 * @param int $flags
	 * @return Status
	 */
	private function getAttemptSaveStatus( ItemContent $itemContent, Summary $summary, $flags ) {
		if ( $itemContent === null ) {
			// to not have an ItemContent isn't really bad at this point
			return Status::newGood( true );
		}
		else {
			// Do the actual save, or if it don't exist yet create it.
			return $this->attemptSaveEntity( $itemContent,
				$summary,
				$flags );
		}
	}

	private function buildResult( ItemContent $itemContent, Status $status ) {
		if ( $itemContent !== null ) {
			$this->getResultBuilder()->addRevisionIdFromStatusToResult( $status, 'entity' );
			//FIXME: breaking change, remove forced numeric ids!!!
			$this->getResultBuilder()->addBasicEntityInformation( $itemContent->getItem()->getId(), 'entity', true );
		}

		$this->getResultBuilder()->markSuccess( $status->isOK() );
	}

	/**
	 * @see \Wikibase\Api\ModifyEntity::validateParameters()
	 */
	protected function validateParameters( array $params ) {
		if ( $params['fromsite'] === $params['tosite'] ) {
			$this->dieUsage( 'The from site can not match the to site' , 'param-illegal' );
		}

		if( !( strlen( $params['fromtitle'] ) > 0) || !( strlen( $params['totitle'] ) > 0) ){
			$this->dieUsage( 'The from title and to title must have a value' , 'param-illegal' );
		}
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'param-illegal', 'info' => $this->msg( 'wikibase-api-param-illegal' )->text() ),
			array( 'code' => 'no-external-page', 'info' => $this->msg( 'wikibase-api-no-external-page' )->text() ),
			array( 'code' => 'common-item', 'info' => $this->msg( 'wikibase-api-common-item' )->text() ),
			array( 'code' => 'no-common-item', 'info' => $this->msg( 'wikibase-api-no-common-item' )->text() ),
		) );
	}

	/**
	 * @see \ApiBase::isWriteMode()
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		$sites = $this->siteLinkTargetProvider->getSiteList( $this->siteLinkGroups );
		return array_merge( parent::getAllowedParams(), array(
			'tosite' => array(
				ApiBase::PARAM_TYPE => $sites->getGlobalIdentifiers(),
			),
			'totitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'fromsite' => array(
				ApiBase::PARAM_TYPE => $sites->getGlobalIdentifiers(),
			),
			'fromtitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'token' => null,
			'bot' => false,
		) );
	}

	/**
	 * Get final parameter descriptions, after hooks have had a chance to tweak it as
	 * needed.
	 *
	 * @return array|bool False on no parameter descriptions
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'tosite' => array( 'An identifier for the site on which the page resides.',
				"Use together with 'totitle' to make a complete sitelink."
			),
			'totitle' => array( 'Title of the page to associate.',
				"Use together with 'tosite' to make a complete sitelink."
			),
			'fromsite' => array( 'An identifier for the site on which the page resides.',
				"Use together with 'fromtitle' to make a complete sitelink."
			),
			'fromtitle' => array( 'Title of the page to associate.',
				"Use together with 'fromsite' to make a complete sitelink."
			),
			'token' => array( 'A "edittoken" token previously obtained through the token module (prop=info).',
				'Later it can be implemented a mechanism where a token can be returned spontaneously',
				'and the requester should then start using the new token from the next request, possibly when',
				'repeating a failed request.'
			),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),
		) );
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module to associate two articles on two different wikis with a Wikibase item.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wblinktitles&fromsite=enwiki&fromtitle=Hydrogen&tosite=dewiki&totitle=Wasserstoff'
			=> 'Add a link "Hydrogen" from the English page to "Wasserstoff" at the German page',
		);
	}

}
