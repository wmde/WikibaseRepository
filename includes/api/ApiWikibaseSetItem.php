<?php

/**
 * Base class for API modules modifying a single item identified based on id xor a combination of site and page title.
 *
 * @since 0.1
 *
 * @file ApiWikibaseModifyItem.php
 * @ingroup Wikibase
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 */
class ApiWikibaseSetItem extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	/**
	 * Check the rights
	 * 
	 * @param $title Title object where the item is stored
	 * @param $user User doing the action
	 * @param $mod null|String name of the module, usually not set
	 * @param $op null|String operation that is about to be done, usually not set
	 * @return array of errors reported from the static getPermissionsError
	 */
	protected static function getPermissionsError( $user, $mod='item', $op='add' ) {
		if ( WBSettings::get( 'apiInDebug' ) ? !WBSettings::get( 'apiDebugWithRights', false ) : false ) {
			return null;
		}
		
		// Check permissions
		return !$user->isAllowed( is_string($mod) ? "{$mod}-{$op}" : $op);
		
	}
	
	/**
	 * Main method. Does the actual work and sets the result.
	 *
	 * @since 0.1
	 */
	public function execute() {
		// TODO: Rewrite as more fine grained permissions
		// note that we use permissions at the page level while we should use permissions
		// at a more fine grained level
		// especially note that we need the page for this specific implementation but that
		// we could get away with only isAllowed(/*right*/) for internal content within an
		// item
		$params = $this->extractRequestParams();
		$user = $this->getUser();

		if ( $params['gettoken'] ) {
			$res['setitemtoken'] = $user->getEditToken();
			$this->getResult()->addValue( null, $this->getModuleName(), $res );
			return;
		}
		
		// This is really already done with needTokens()
		if ( $this->needsToken() && !$user->matchEditToken( $params['token'] ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-no-token' ), 'no-token' );
		}
		
		if ( !$params['data'] ) {
			$this->dieUsage( wfMsg( 'wikibase-api-no-data' ), 'no-data' );
		}

		if ( !$user->isAllowed( 'edit' ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-cant-edit' ), 'cant-edit' );
		}
		
		// lacks error checking
		$item = WikibaseItem::newFromArray( json_decode( $params['data'] ) );
		
		// TODO: Change for more fine grained permissions
		$user = $this->getUser();
		if (self::getPermissionsError( $this->getUser() ) ) {
			$this->dieUsage( wfMsg( 'wikibase-api-no-permissions' ), 'no-permissions' );
		}
		
		$success = $item->save();

		if ( !$success ) {
			// TODO: throw error. Right now will have PHP fatal when accessing $item later on...
		}

		if ( !isset($params['summary']) ) {
			// TODO: make a proper summary
			//$params['summary'] = $item->getTextForSummary();
			$params['summary'] = 'dummy';
		}

		$languages = WikibaseUtils::getLanguageCodes();
		
		// because this is serialized and cleansed we can simply go for known values
		$this->getResult()->addValue(
			NULL,
			'item',
			array(
				'id' => $item->getId(),
				'sitelinks' => $item->getSiteLinks(),
				'descriptions' => $item->getDescriptions($languages),
				'labels' => $item->getLabels($languages)
			)
		);
		$this->getResult()->addValue(
			null,
			'success',
			(int)$success
		);
	}
	
	/**
	 * Returns a list of all possible errors returned by the module
	 * @return array in the format of array( key, param1, param2, ... ) or array( 'code' => ..., 'info' => ... )
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'no-token', 'info' => wfMsg( 'wikibase-api-no-token' ) ),
			array( 'code' => 'no-data', 'info' => wfMsg( 'wikibase-api-no-data' ) ),
			array( 'code' => 'cant-edit', 'info' => wfMsg( 'wikibase-api-cant-edit' ) ),
			array( 'code' => 'no-permissions', 'info' => wfMsg( 'wikibase-api-no-permissions' ) ),
		) );
	}

	/**
	 * Returns whether this module requires a Token to execute
	 * @return bool
	 */
	public function needsToken() {
		return WBSettings::get( 'apiInDebug' ) ? WBSettings::get( 'apiDebugWithTokens', false ) : true ;
	}

	/**
	 * Indicates whether this module must be called with a POST request
	 * @return bool
	 */
	public function mustBePosted() {
		return WBSettings::get( 'apiInDebug' ) ? WBSettings::get( 'apiDebugWithPost', false ) : true ;
	}

	/**
	 * Indicates whether this module requires write mode
	 * @return bool
	 */
	public function isWriteMode() {
		return WBSettings::get( 'apiInDebug' ) ? WBSettings::get( 'apiDebugWithWrite', false ) : true ;
	}
	
	/**
	 * Returns an array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 * @return array|bool
	 */
	public function getAllowedParams() {
		return array(
			'data' => array(
				ApiBase::PARAM_TYPE => 'string',
				//ApiBase::PARAM_REQUIRED => true,
			),
			'summary' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => __CLASS__, // TODO
			),
			'item' => array(
				ApiBase::PARAM_TYPE => array( 'add' ),
				ApiBase::PARAM_DFLT => 'add',
			),
			'token' => null,
			'gettoken' => false,
		);
	}

	/**
	 * Get final parameter descriptions, after hooks have had a chance to tweak it as
	 * needed.
	 *
	 * @return array|bool False on no parameter descriptions
	 */
	public function getParamDescription() {
		return array(
			'data' => array( 'The serialized object that is used as the data source.',
				"The newly created item will be assigned an item 'id'."
			),
			'item' => 'Indicates if you are changing the content of the item',
			'summary' => 'Summary for the edit.',
			'token' => 'A "setitem" token previously obtained through the gettoken parameter', // or prop=info,
			'gettoken' => 'If set, a "setitem" token will be returned, and no other action will be taken',
		);
	}

	/**
	 * Returns the description string for this module
	 * @return mixed string or array of strings
	 */
	public function getDescription() {
		return array(
			'API module to create a new Wikibase item and modify it with serialised information.'
		);
	}

	/**
	 * Returns usage examples for this module. Return false if no examples are available.
	 * @return bool|string|array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetitem&data=[{}]'
			=> 'Set an empty JSON structure for the item, it will be extended with an item id and the structure cleansed and completed',
			'api.php?action=wbsetitem&data=[{"label":{"de":{"language":"de","value":"de-value"},"en":{"language":"en","value":"en-value"}}}]'
			=> 'Set a more complete JSON structure for the item.',
		);
	}

	/**
	 * @return bool|string|array Returns a false if the module has no help url, else returns a (array of) string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetitem';
	}

	/**
	 * Returns a string that identifies the version of this class.
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
	
}