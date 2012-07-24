<?php

namespace Wikibase;
use ApiBase, User, Http;

/**
 * API module to associate a page on a site with a Wikibase item or remove an already made such association.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseLinkSite.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
class ApiSetSiteLink extends ApiModifyItem {

	/**
	 * @see  ApiModifyItem::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Item $item, array $params ) {
		$permissions = parent::getRequiredPermissions( $item, $params );

		$permissions[] = 'sitelink-' . ( strlen( $params['linktitle'] ) ? 'update' : 'remove' );
		return $permissions;
	}

	/**
	 * Make sure the required parameters are provided and that they are valid.
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 */
	protected function validateParameters( array $params ) {
		parent::validateParameters( $params );

		// Note that linksite should always exist as a prerequisite for this
		// call to succeede. The param linktitle will not always exist because
		// that signals a sitelink to remove.
	}

	/**
	 * Create the item if its missing.
	 *
	 * @since    0.1
	 *
	 * @param array       $params
	 *
	 * @internal param \Wikibase\ItemContent $itemContent
	 * @return ItemContent Newly created item
	 */
	protected function createItem( array $params ) {
		$this->dieUsage( wfMsg( 'wikibase-api-no-such-item' ), 'no-such-item' );
	}

	/**
	 * @see ApiModifyItem::modifyItem()
	 *
	 * @since 0.1
	 *
	 * @param ItemContent $itemContent
	 * @param array $params
	 *
	 * @return boolean Success indicator
	 */
	protected function modifyItem( ItemContent &$itemContent, array $params ) {

		if ( isset( $params['linktitle'] ) ) {
			$params['linktitle'] = Utils::squashToNFC( $params['linktitle'] );
		}

		if ( isset( $params['linksite'] ) && ( $params['linktitle'] === '' ) ) {
			$link = $itemContent->getItem()->getSiteLink( $params['linksite'] );

			if ( !$link ) {
				$this->dieUsage( wfMsg( 'wikibase-api-remove-sitelink-failed' ), 'remove-sitelink-failed' );
			}

			$itemContent->getItem()->removeSiteLink( $params['linksite'] );
			$this->addSiteLinksToResult( array( $link ), 'item' );
			return true;
		}
		else {
			$site = Sites::singleton()->getSiteByGlobalId( $params['linksite'] );

			if ( $site === false ) {
				$this->dieUsage( wfMsg( 'wikibase-api-not-recognized-siteid' ), 'add-sitelink-failed' );
			}

			$page = $site->normalizePageName( $params['linktitle'] );

			if ( $page === false ) {
				$this->dieUsage( wfMsg( 'wikibase-api-no-external-page' ), 'add-sitelink-failed' );
			}

			$link = new SiteLink( $site, $page );
			$ret = $itemContent->getItem()->addSiteLink( $link, 'set' );

			if ( $ret === false ) {
				$this->dieUsage( wfMsg( 'wikibase-api-add-sitelink-failed' ), 'add-sitelink-failed' );
			}

			$this->addSiteLinksToResult( array( $ret ), 'item' );
			return $ret !== false;
		}
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'empty-link-title', 'info' => wfMsg( 'wikibase-api-empty-link-title' ) ),
			array( 'code' => 'link-exists', 'info' => wfMsg( 'wikibase-api-link-exists' ) ),
			array( 'code' => 'database-error', 'info' => wfMsg( 'wikibase-api-database-error' ) ),
			array( 'code' => 'add-sitelink-failed', 'info' => wfMsg( 'wikibase-api-add-sitelink-failed' ) ),
			array( 'code' => 'remove-sitelink-failed', 'info' => wfMsg( 'wikibase-api-remove-sitelink-failed' ) ),
		) );
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		$allowedParams = parent::getAllowedParams();
		$allowedParams['item'][ApiBase::PARAM_DFLT] = 'set';
		return array_merge( $allowedParams, array(
			'linksite' => array(
				ApiBase::PARAM_TYPE => Sites::singleton()->getGlobalIdentifiers(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'linktitle' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
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
			'linksite' => 'The identifier of the site on which the article to link resides',
			'linktitle' => 'The title of the article to link',
		) );
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module to associate an artiile on a wiki with a Wikibase item or remove an already made such association.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetsitelink&id=42&linksite=en&linktitle=Wikimedia'
			=> 'Add title "Wikimedia" for English page with id "42" if the site link does not exist',
			'api.php?action=wbsetsitelink&id=42&linksite=en&linktitle=Wikimedia&summary=World%20domination%20will%20be%20mine%20soon!'
			=> 'Add title "Wikimedia" for English page with id "42", if the site link does not exist',
		);
	}

	/**
	 * @return bool|string|array Returns a false if the module has no help url, else returns a (array of) string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetsitelink';
	}

	/**
	 * Returns a string that identifies the version of this class.
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
