<?php

/**
 * API module to set a label for a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseSetLabel.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiWikibaseSetLabel extends ApiWikibaseModifyItem {

	/**
	 * Actually modify the item.
	 *
	 * @since 0.1
	 *
	 * @param WikibaseItem $item
	 * @param array $params
	 *
	 * @return boolean Success indicator
	 */
	protected function modifyItem( WikibaseItem &$item, array $params ) {
		$item->setLabel( $params['language'], $params['label'] );

		return true;
	}

	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'language' => array(
				ApiBase::PARAM_TYPE => WikibaseUtils::getLanguageCodes(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'label' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'test' => array( // TODO: Remove this when we go into production
			),
		) );
	}

	public function getParamDescription() {
		return array_merge( parent::getAllowedParams(), array(
			'id' => 'The ID of the item to set a label for',
			'language' => 'Language the label is in',
			'label' => 'The value to set for the label',
			'test' => 'Add some dummy data for testing purposes', // TODO: Remove this when we go into production
		) );
	}

	public function getDescription() {
		return array(
			'API module to set a label for a Wikibase item.'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
		) );
	}

	protected function getExamples() {
		return array(
			'api.php?action=wbsetlabel&id=42&language=en&label=Wikimedia'
				=> 'Set the string "Wikimedia" for page with id "42" as a label in English language',
			'api.php?action=wbsetlabel&id=42&language=en&label=Wikimedia&test'
				=> 'Fake a set description, always returns the same values',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikidata/API#wbsetlabel';
	}
	
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
