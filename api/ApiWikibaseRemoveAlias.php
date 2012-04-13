<?php

/**
 * API module to remove an associated string alias with a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseRemoveAlias.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 */

class ApiWikibaseRemoveAlias extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	/**
	 * @var ApiResult
	 */
	private $result;
	
	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		
		$this->result = $this->getResult();
		
		// TODO: implement
		
		// If we are testing we add some dummy data
		// TODO: Remove this when we go into production
		if ( WBSettings::get( 'apiInTest' ) && isset($params['test']) ) {
			$this->result->addValue( array( 'wbremovealias' ), 'result', 'Success', true );
			$this->result->addValue( array( 'wbremovealias' ), 'pageid', 12, true );
			$this->result->addValue( array( 'wbremovealias' ), 'title', 'q7', true );
			$this->result->addValue( array( 'wbremovealias' ), 'oldrevid', 123, true );
			$this->result->addValue( array( 'wbremovealias' ), 'newrevid', 456, true );
		}
	}

	public function needsToken() {
		return !WBSettings::get( 'apiInDebug' );
	}

	public function mustBePosted() {
		return !WBSettings::get( 'apiInDebug' );
	}

	public function getAllowedParams() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
			),
			'language' => array(
				ApiBase::PARAM_TYPE => WikibaseUtils::getLanguageCodes(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'alias' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'test' => array( // TODO: Remove this when we go into production
			),
		);
	}

	public function getParamDescription() {
		return array(
			'id' => 'The ID of the item to associate the page with',
			'language' => 'Language code of the wikipedia on which the page resides',
			'alias' => 'String used as an alternate title of the page',
			'test' => 'Add some dummy data for testing purposes', // TODO: Remove this when we go into production
		);
	}

	public function getDescription() {
		return array(
			'API module to remove the associated alias on a string form from a Wikibase item.'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
		) );
	}

	protected function getExamples() {
		return array(
			'api.php?action=wbremovealias&id=42&language=en&alias=Wikimedia'
				=> 'Removes the string "Wikimedia" for page with id "42" as an alias in English language',
			'api.php?action=wbremovealias&id=42&language=en&alias=Wikimedia&test'
				=> 'Fake a remove of the alias, always returns the same values',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikidata/API#wbremovealias';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
