<?php

namespace Wikibase;
use ApiBase, User;

/**
 * API module to set the aliases for a Wikibase item.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file ApiWikibaseAliases.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiSetAliases extends ApiModifyItem {

	/**
	 * @see  ApiModifyItem::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Item $item, array $params ) {
		$permissions = parent::getRequiredPermissions( $item, $params );

		if ( isset( $params['add'] ) ) {
			$permissions[] = 'alias-add';
		}
		if ( isset( $params['set'] ) ) {
			$permissions[] = 'alias-set';
		}
		if ( isset( $params['remove'] ) ) {
			$permissions[] = 'alias-remove';
		}
		return $permissions;
	}

	/**
	 * Make sure the required parameters are provided and that they are valid.
	 * This overrides the base class
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 */
	protected function validateParameters( array $params ) {
		parent::validateParameters( $params );

		if ( !( ( isset( $params['add'] ) || isset( $params['remove'] ) ) XOR isset( $params['set'] ) ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-aliases-invalid-list' ), 'aliases-invalid-list' );
		}
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
	 * Actually modify the item.
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
		if ( isset( $params['set'] ) ) {
			$itemContent->getItem()->setAliases(
				$params['language'],
				array_map(
					function( $str ) { return Utils::squashToNFC( $str ); },
					$params['set']
				)
			);
		}

		if ( isset( $params['remove'] ) ) {
			$itemContent->getItem()->removeAliases(
				$params['language'],
				array_map(
					function( $str ) { return Utils::squashToNFC( $str ); },
					$params['remove']
				)
			);
		}

		if ( isset( $params['add'] ) ) {
			$itemContent->getItem()->addAliases(
				$params['language'],
				array_map(
					function( $str ) { return Utils::squashToNFC( $str ); },
					$params['add']
				)
			);
		}

		$aliases = $itemContent->getItem()->getAliases( $params['language'] );
		if ( count( $aliases ) ) {
			$this->addAliasesToResult( array( $params['language'] => $aliases ), 'item' );
		}

		return true;
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'aliases-invalid-list', 'info' => wfMsg( 'wikibase-api-aliases-invalid-list' ) ),
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
		return array_merge( parent::getAllowedParams(), array(
			'add' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'remove' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'set' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'language' => array(
				ApiBase::PARAM_TYPE => Utils::getLanguageCodes(),
				ApiBase::PARAM_REQUIRED => true,
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
			'add' => 'List of aliases to add',
			'remove' => 'List of aliases to remove',
			'set' => 'A list of aliases that will replace the current list',
			'language' => 'The language of which to set the aliases',
		) );
	}

	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getDescription() {
		return array(
			'API module to set the aliases for a Wikibase item.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetaliases&language=en&id=1&set=Foo|Bar'
				=> 'Set the English labels for the item with id 1 to Foo and Bar',

			'api.php?action=wbsetaliases&language=en&id=1&add=Foo|Bar'
				=> 'Add Foo and Bar to the list of English labels for the item with id 1',

			'api.php?action=wbsetaliases&language=en&id=1&remove=Foo|Bar'
				=> 'Remove Foo and Bar from the list of English labels for the item with id 1',
		);
	}

	/**
	 * @return bool|string|array Returns a false if the module has no help url, else returns a (array of) string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetaliases';
	}


	/**
	 * Returns a string that identifies the version of this class.
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
