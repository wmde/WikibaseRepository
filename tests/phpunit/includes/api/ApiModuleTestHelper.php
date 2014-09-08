<?php

namespace Wikibase\Test\Api;

use ApiBase;
use ApiMain;
use Exception;
use FauxRequest;
use UsageException;
use User;
use PHPUnit_Framework_Assert as Assert;

/**
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class ApiModuleTestHelper {

	/**
	 * Instantiates an API module.
	 *
	 * @param callable|string $instantiator A callback or class name for instantiating the module.
	 *        Will be called with two parameters, the ApiMain instance and $name.
	 * @param string $name
	 * @param array $params Request parameter. The 'token' parameter will be supplied automatically.
	 * @param User $user Defaults to the global user object
	 *
	 * @return ApiBase
	 */
	public function newApiModule( $instantiator, $name, $params, User $user = null ) {
		if ( !$user ) {
			$user = $GLOBALS['wgUser'];
		}

		if ( !array_key_exists( 'token', $params ) ) {
			$params['token'] = $user->getToken();
		}

		$request = new FauxRequest( $params, true );
		$main = new ApiMain( $request );
		$main->getContext()->setUser( $user );

		if ( is_string( $instantiator ) && class_exists( $instantiator ) ) {
			$module = new $instantiator( $main, $name );
		} else {
			$module = call_user_func( $instantiator, $main, $name );
		}

		return $module;
	}

	/**
	 * Asserts that the given API response represents a successful call.
	 *
	 * @param array $response
	 */
	public function assertResultSuccess( $response ) {
		Assert::assertArrayHasKey( 'success', $response, "Missing 'success' marker in response." );
	}

	/**
	 * Asserts that the given API response has the given error code.
	 *
	 * @param string $expectedCode
	 * @param array $response
	 */
	public function assertUsageException( $expected, Exception $ex  ) {
		Assert::assertInstanceOf( 'UsageException', $ex );
		/** @var UsageException $ex */

		if ( is_string( $expected ) ) {
			$expected = array( 'code' => $expected );
		}

		if ( isset( $expected['code'] ) ) {
			Assert::assertEquals( $expected['code'], $ex->getCodeString() );
		}

		if ( isset( $expected['message'] ) ) {
			Assert::assertContains( $expected['message'], $ex->getMessage() );
		}
	}

	/**
	 * Asserts the existence of some path in the result, represented by any additional parameters.
	 *
	 * @param array $path
	 * @param array $response
	 */
	public function assertResultHasKeyInPath( $path, $response ) {
		$obj = $response;
		$p = '/';

		foreach ( $path as $key ) {
			Assert::assertArrayHasKey( $key, $obj, "Expected key $key under path $p in the response." );

			$obj = $obj[ $key ];
			$p .= "/$key";
		}
	}

}
