<?php

namespace Wikibase;
use ApiBase;

/**
 * API module for the language attributes for a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseSetDescription.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiSetDescription extends ApiModifyLangAttribute {

	/**
	 * @see  ApiModifyItem::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Item $item, array $params ) {
		$permissions = parent::getRequiredPermissions( $item, $params );

		$permissions[] = ( isset( $params['value'] ) && 0<strlen( $params['value'] ) )
			? 'description-update'
			: 'description-remove';
		return $permissions;
	}

	/**
	 * @see ApiModifyItem::modifyItem()
	 */
	protected function modifyItem( ItemContent &$itemContent, array $params ) {
		$status = parent::modifyItem( $itemContent, $params );

		if ( $status && isset( $params['value'] ) ) {
			$description = Utils::squashToNFC( $params['value'] );
			$language = $params['language'];
			if ( 0 < strlen( $description ) ) {
				$descriptions = array( $language => $itemContent->getItem()->setDescription( $language, $description ) );
			}
			else {
				$itemContent->getItem()->removeDescription( $language );
				$descriptions = array( $language => '' );
			}
			$this->addDescriptionsToResult( $descriptions, 'item' );
		}

		return $status;
	}

	/**
	 * @see ApiBase::getDescription()
	 */
	public function getDescription() {
		return array(
			'API module to set a description for a single Wikibase item.'
		);
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
		) );
	}

	/**
	 * @see ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetdescription&id=42&language=en&value=An%20encyclopedia%20that%20everyone%20can%20edit'
				=> 'Set the string "An encyclopedia that everyone can edit" for page with id "42" as a decription in English language',
		);
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetdescription';
	}

	/**
	 * @see ApiBase::getVersion()
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
