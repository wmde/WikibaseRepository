<?php

namespace Wikibase;
use ApiBase;

/**
 * API module to delete the language attributes for a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseSetDescription.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiDeleteLanguageAttribute extends ApiModifyItem {

	/**
	 * @see  ApiModifyItem::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Item $item, array $params ) {
		$permissions = parent::getRequiredPermissions( $item, $params );

		$permissions[] = 'lang-attr-remove';
		return $permissions;
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
		$language = $params['language'];
		$labels = $itemContent->getItem()->getLabels( (array)$language );
		$descriptions = $itemContent->getItem()->getDescriptions( (array)$language );

		$success = false;

		foreach ( $params['attribute'] as $attr ) {

			switch ( $attr ) {
				case 'label':
					if ( !count($labels) ) {
						$this->dieUsage( wfMsg( 'wikibase-api-label-not-found' ), 'label-not-found' );
					}
					// TODO: should probably be some kind of status from the remove operation
					$itemContent->getItem()->removeLabel( $language );
					$this->addDeletedLabelsToResult( array( $language ), 'item' );
					$success = $success || true;
					break;

				case 'description':
					if ( !count($descriptions) ) {
						$this->dieUsage( wfMsg( 'wikibase-api-description-not-found' ), 'description-not-found' );
					}
					// TODO: should probably be some kind of status from the remove operation
					$itemContent->getItem()->removeDescription( $language );
					$this->addDeletedDescriptionsToResult( array( $language ), 'item' );
					$success = $success || true;
					break;

				default:
					// should never be here
					$this->dieUsage( wfMsg( 'wikibase-api-not-recognized' ), 'not-recognized' );
			}

		}

		return $success;
	}

	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'language' => array(
				ApiBase::PARAM_TYPE => Utils::getLanguageCodes(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'attribute' => array(
				ApiBase::PARAM_TYPE => array( 'label', 'description'),
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => true,
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
			'language' => 'Language the description is in',
			'attribute' => array('The type of attribute to delete',
				'One of ("label", "description")')
		) );
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module to delete a label or description for a single Wikibase item.'
		);
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'label-not-found', 'info' => wfMsg( 'wikibase-api-label-not-found' ) ),
			array( 'code' => 'description-not-found', 'info' =>  wfMsg( 'wikibase-api-description-not-found' ) ),
			array( 'code' => 'not-recognized', 'info' => wfMsg( 'wikibase-api-not-recognized' ) ),
		) );
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbdeletelanguageattribute&id=42&language=en&attribute=label'
			=> 'Delete whatever is stored in the attribute "label" in english language.',
		);
	}

	/**
	 * @return bool|string|array Returns a false if the module has no help url, else returns a (array of) string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbdeletelanguageattribute';
	}


	/**
	 * Returns a string that identifies the version of this class.
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
