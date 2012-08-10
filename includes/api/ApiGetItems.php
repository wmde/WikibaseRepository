<?php

namespace Wikibase;
use ApiBase;

/**
 * API module to get the data for one or more Wikibase items.
 *
 * @since 0.1
 *
 * @file ApiWikibaseGetItem.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiGetItems extends Api {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	/**
	 * @see ApiBase::execute()
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		if ( !( isset( $params['ids'] ) XOR ( isset( $params['sites'] ) && isset( $params['titles'] ) ) ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-id-xor-wikititle' ), 'id-xor-wikititle' );
		}

		$missing = 0;

		if ( !isset( $params['ids'] ) ) {
			$params['ids'] = array();
			$numSites = count( $params['sites'] );
			$numTitles = count( $params['titles'] );
			$max = max( $numSites, $numTitles );

			if ( $numSites === 0 || $numTitles === 0 ) {
				$this->dieUsage( wfMsg( 'wikibase-api-id-xor-wikititle' ), 'id-xor-wikititle' );
			}
			else {
				$idxSites = 0;
				$idxTitles = 0;

				for ( $k = 0; $k < $max; $k++ ) {
					$siteId = $params['sites'][$idxSites++];
					$title = Utils::squashToNFC( $params['titles'][$idxTitles++] );

					$id = ItemHandler::singleton()->getIdForSiteLink( $siteId, $title );

					if ( $id ) {
						$params['ids'][] = intval( $id );
					}
					else {
						$this->getResult()->addValue( 'items', (string)(--$missing),
							array( 'site' => $siteId, 'title' => $title, 'missing' => "" )
						);
					}

					if ( $idxSites === $numSites ) {
						$idxSites = 0;
					}

					if ( $idxTitles === $numTitles ) {
						$idxTitles = 0;
					}
				}
			}
		}

		$params['ids'] = array_unique( $params['ids'], SORT_NUMERIC );

		$languages = $params['languages'];

		$this->setUsekeys( $params );

		// This really needs a more generic solution as similar tricks will be
		// done to other props as well, for example variants for the language
		// attributes. It would also be nice to write something like */urls for
		// all props that can supply full urls.
		if ( in_array( 'sitelinks/urls', $params['props'] ) ) {
			$siteLinkOptions = array( 'url' );
			$props = array_flip( array_values( $params['props'] ) );
			unset( $props['sitelinks/urls'] );
			$props['sitelinks'] = true;
			$props = array_keys( $props );
		}
		else {
			$siteLinkOptions = null;
			$props = $params['props'];
		}

		// loop over all items
		foreach ($params['ids'] as $id) {

			$itemPath = array( 'items', $id );
			$res = $this->getResult();

			$res->addValue( $itemPath, 'id', $id );

			// later we do a getContent but only if props are defined
			if ( $params['props'] !== array() ) {
				$page = ItemHandler::singleton()->getWikiPageForId( $id );

				if ( $page->exists() ) {
					// as long as getWikiPageForId only returns ids for legal items this holds
					/**
					 * @var $itemContent ItemContent
					 */
					$itemContent = $page->getContent();

					if ( is_null( $itemContent ) ) {
						continue;
					}

					$item = $itemContent->getItem();

					// loop over all props
					foreach ( $props as $key ) {
						switch ( $key ) {
						case 'info':
							$res->addValue( $itemPath, 'pageid', intval( $page->getId() ) );
							$title = $page->getTitle();
							$res->addValue( $itemPath, 'ns', intval( $title->getNamespace() ) );
							$res->addValue( $itemPath, 'title', $title->getPrefixedText() );
							$revision = $page->getRevision();
							if ( $revision !== null ) {
								$res->addValue( $itemPath, 'lastrevid', intval( $revision->getId() ) );
								$res->addValue( $itemPath, 'touched', wfTimestamp( TS_ISO_8601, $revision->getTimestamp() ) );
								$res->addValue( $itemPath, 'length', intval( $revision->getSize() ) );
							}
							$res->addValue( $itemPath, 'count', intval( $page->getCount() ) );
							break;
						case 'aliases':
							$this->addAliasesToResult( $item->getAllAliases( $languages ), $itemPath );
							break;
						case 'sitelinks':
							$this->addSiteLinksToResult( $item->getSiteLinks(), $itemPath, 'sitelinks', 'sitelink', $siteLinkOptions );
							break;
						case 'descriptions':
							$this->addDescriptionsToResult( $item->getDescriptions( $languages ), $itemPath );
							break;
						case 'labels':
							$this->addLabelsToResult( $item->getLabels( $languages ), $itemPath );
							break;
						default:
							// should never be here, because it should be something for the earlyer cases
							$this->dieUsage( wfMsg( 'wikibase-api-not-recognized' ), 'not-recognized' );
						}
					}
				}
				else {
					$this->getResult()->addValue( $itemPath, 'missing', "" );
				}
			}
		}
		$this->getResult()->setIndexedTagName_internal( array( 'items' ), 'item' );

		$success = true;

		if ( $success && isset( $params['gettoken'] ) ) {
			$user = $this->getUser();
			$this->addTokenToResult( $user->getEditToken() );
		}

		$this->getResult()->addValue(
			null,
			'success',
			(int)$success
		);
	}

	/**
	 * @see ApiBase::getAllowedParams()
	 */
	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'ids' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			),
			'sites' => array(
				ApiBase::PARAM_TYPE => Sites::singleton()->getGroup( SITE_GROUP_WIKIPEDIA )->getGlobalIdentifiers(),
				ApiBase::PARAM_ISMULTI => true,
			),
			'titles' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'props' => array(
				ApiBase::PARAM_TYPE => array( 'info', 'sitelinks', 'aliases', 'labels', 'descriptions', 'sitelinks/urls' ),
				ApiBase::PARAM_DFLT => 'info|sitelinks|aliases|labels|descriptions',
				ApiBase::PARAM_ISMULTI => true,
			),
			'languages' => array(
				ApiBase::PARAM_TYPE => Utils::getLanguageCodes(),
				ApiBase::PARAM_ISMULTI => true,
			),
		) );
	}

	/**
	 * @see ApiBase::getParamDescription()
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'ids' => 'The IDs of the items to get the data from',
			'sites' => array( 'Identifier for the site on which the corresponding page resides',
				"Use together with 'title', but only give one site for several titles or several sites for one title."
			),
			'titles' => array( 'The title of the corresponding page',
				"Use together with 'sites', but only give one site for several titles or several sites for one title."
			),
			'props' => array( 'The names of the properties to get back from each item.',
				"Will be further filtered by any languages given."
			),
			'languages' => array( 'By default the internationalized values are returned in all available languages.',
				'This parameter allows filtering these down to one or more languages by providing one or more language codes.'
			),
		) );
	}

	/**
	 * @see ApiBase::getDescription()
	 */
	public function getDescription() {
		return array(
			'API module to get the data for multiple Wikibase items.'
		);
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'wrong-class', 'info' => wfMsg( 'wikibase-api-wrong-class' ) ),
			array( 'code' => 'id-xor-wikititle', 'info' => wfMsg( 'wikibase-api-id-xor-wikititle' ) ),
			array( 'code' => 'no-such-item', 'info' => wfMsg( 'wikibase-api-no-such-item' ) ),
			array( 'code' => 'not-recognized', 'info' => wfMsg( 'wikibase-api-not-recognized' ) ),
		) );
	}

	/**
	 * @see ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbgetitems&ids=42'
			=> 'Get item number 42 with language attributes in all available languages',
			'api.php?action=wbgetitems&ids=42&languages=en'
			=> 'Get item number 42 with language attributes in English language',
			'api.php?action=wbgetitems&sites=en&titles=Berlin&languages=en'
			=> 'Get the item for page "Berlin" on the site "en", with language attributes in English language',
		);
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbgetitems';
	}

	/**
	 * @see ApiBase::getVersion()
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
