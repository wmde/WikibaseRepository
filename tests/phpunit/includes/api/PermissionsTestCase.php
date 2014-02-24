<?php

namespace Wikibase\Test\Api;

use UsageException;
use Wikibase\Test\PermissionsHelper;

/**
 * Base class for permissions tests
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 * @author Adam Shorland
 */
class PermissionsTestCase extends WikibaseApiTestCase {

	protected $permissions;
	protected $old_user;

	private static $hasSetup;

	public function setUp() {
		global $wgGroupPermissions, $wgUser;

		parent::setUp();

		if( !isset( self::$hasSetup ) ){
			$this->initTestEntities( array( 'Oslo', 'Empty' ) );
		}
		self::$hasSetup = true;

		$this->permissions = $wgGroupPermissions;
		$this->old_user = clone $wgUser;
	}

	protected function tearDown() {
		global $wgGroupPermissions;
		global $wgUser;

		$wgGroupPermissions = $this->permissions;

		if ( $this->old_user ) { // should not be null, but sometimes, it is
			$wgUser = $this->old_user;
		}

		parent::tearDown();
	}

	protected function doPermissionsTest( $action, $params, $permissions = array(), $expectedError = null ) {
		global $wgUser;

		PermissionsHelper::applyPermissions( $permissions );

		try {
			$params[ 'action' ] = $action;
			$this->doApiRequestWithToken( $params, null, $wgUser );

			if ( $expectedError !== null ) {
				$this->fail( 'API call should have failed with a permission error!' );
			} else {
				// the below is to avoid the tests being marked incomplete
				$this->assertTrue( true );
			}
		} catch ( UsageException $ex ) {
			if ( $expectedError !== true ) {
				$this->assertEquals( $expectedError, $ex->getCodeString(), 'API did not return expected error code. Got error message ' . $ex );
			}
		}
	}

} 