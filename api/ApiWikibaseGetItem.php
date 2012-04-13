<?php

/**
 * API module to get the data for a single Wikibase item.
 *
 * @since 0.1
 *
 * @file ApiWikibaseGetItem.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApiWikibaseGetItem extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}


	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		if ( !( isset( $params['id'] ) XOR ( isset( $params['site'] ) && isset( $params['title '] ) ) ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-id-xor-wikititle' ), 'id-xor-wikititle' );
		}

//		if ( !isset( $params['id'] ) ) {
//			$params['id'] = WikibaseItem::getIdForSiteLink( $params['wiki'], $params['title '] );
//		}

		// TODO: implement
	}

	public function getAllowedParams() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			),
			'site' => array(
				ApiBase::PARAM_TYPE => WikibaseUtils::getSiteIdentifiers(),
			),
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'language' => array(
				ApiBase::PARAM_TYPE => WikibaseUtils::getLanguageCodes(),
				ApiBase::PARAM_ISMULTI => true,
			),
		);
	}

	public function getParamDescription() {
		return array(
			'id' => 'The ID of the item to get the data from',
			'language' => 'By default the internationalized values are returned in all available languages.
						This parameter allows filtering these down to one or more languages by providing their language codes.',
			'title' => 'The title of the corresponding page',
			'site' => 'Identifier for the site on which the corresponding page resides',
		);
	}

	public function getDescription() {
		return array(
			'API module to get the data for a single Wikibase item.'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'id-xor-wikititle', 'info' => 'You need to either provide the item id or the title of a corresponding page and the identifier for the wiki this page is on' ),
		) );
	}

	protected function getExamples() {
		return array(
			'api.php?action=wbgetitem&id=42'
				=> 'Get item number 42 with default (user?) language',
			'api.php?action=wbgetitem&id=42&language=en'
				=> 'Get item number 42 with english language',
			'api.php?action=wbgetitem&id=4|2'
				=> 'Get item number 4 and 2 with default (user?) language',
			'api.php?action=wbgetitem&id=4|2&language=en'
				=> 'Get item number 4 and 2 with enlish language',

			'api.php?action=wbgetitem&site=en&title=Berlin&language=en'
				=> 'Get the item associated to page Berlin on the site identified by "en"',
			'api.php?action=wbgetitem&site=en&title=Berlin|Foobar&language=en'
				=> 'Get the items associated to pages Berlin and Foobar on the site identified by "en"',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikidata/API#wbgetitem';
	}
	
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
