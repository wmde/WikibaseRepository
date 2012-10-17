<?php

namespace Wikibase\Test;
use ApiTestCase;
use Wikibase\Settings as Settings;

/**
 * Tests for the ApiWikibase class.
 *
 * This testset only checks the validity of the calls and correct handling of tokens and users.
 * Note that we creates an empty database and then starts manipulating testusers.
 *
 * BE WARNED: the tests depend on execution order of the methods and the methods are interdependent,
 * so stuff will fail if you move methods around or make changes to them without matching changes in
 * all methods depending on them.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group ApiSetItemTest
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group large
 */
class ApiSetItemTest extends ApiModifyItemBase {

	static public $id = null;

	static public $item = array(
		"sitelinks" => array(
			"enwiki" => array( "site" => "enwiki", "title" => "Bar" ),
			"dewiki" => array( "site" => "dewiki", "title" => "Foo" ),
		),
		"labels" => array(
			"de" => array( "language" => "de", "value" => "Foo" ),
			"en" => array( "language" => "en", "value" => "Bar" ),
		),
		"aliases" => array(
			"de"  => array( "language" => "de", "value" => "Fuh" ),
			"en"  => array( "language" => "en", "value" => "Baar" ),
		),
		"descriptions" => array(
			"de"  => array( "language" => "de", "value" => "Metasyntaktische Veriable" ),
			"en"  => array( "language" => "en", "value" => "Metasyntactic variable" ),
		)
	);

	static public $expect = array(
		"sitelinks" => array(
			"enwiki" => "Bar",
			"dewiki" => "Foo",
		),
		"labels" => array(
			"de" => "Foo",
			"en" => "Bar",
		),
		"aliases" => array(
			"de"  => array( "Fuh" ),
			"en"  => array( "Baar" ),
		),
		"descriptions" => array(
			"de"  => "Metasyntaktische Veriable",
			"en"  => "Metasyntactic variable",
		)
	);


	/**
	 * Check if an item can not be created whithout a token
	 */
	function testSetItemNoToken() {
		$this->login();

		if ( self::$usetoken ) {
			try {
				$this->doApiRequest(
					array(
						'action' => 'wbsetitem',
						'reason' => 'Some reason',
						'data' => json_encode( self::$item ),
					),
					null,
					false,
					self::$users['wbeditor']->user
				);

				$this->fail( "Adding an item without a token should have failed" );
			}
			catch ( \UsageException $e ) {
				$this->assertTrue( ($e->getCodeString() == 'session-failure'), "Expected session-failure, got unexpected exception: $e" );
			}
		}
	}

	/**
	 * Check if an item can be created when a token is supplied
	 * note that upon completion the id will be stored for later reuse
	 */
	function testSetItemWithToken() {
		$token = $this->getItemToken();

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbsetitem',
				'reason' => 'Some reason',
				'data' => json_encode( self::$item ),
				'token' => $token,
			),
			null,
			false,
			self::$users['wbeditor']->user
		);

		$this->assertSuccess( $res, 'entity', 'id' );
		$this->assertItemEquals( self::$expect, $res['entity'] );

		// Oh my God, its an ugly secondary effect!
		self::$id = $res['entity']['id']; // this will be with the prefix
	}

	/**
	 * Check failure to set the same item again, without id
	 */
	function testSetItemNoId() {
		$token = $this->getItemToken();

		$data = array( 'labels' => array(
				"de" => array( "language" => "de", "value" => "Foo X" ),
				"en" => array( "language" => "en", "value" => "Bar Y" ),
			) );

		try {
			$this->doApiRequest(
				array(
					'action' => 'wbsetitem',
					'reason' => 'Some reason',
					'data' => json_encode( array_merge( self::$item, $data ) ),
					'token' => $token,
				),
				null,
				false,
				self::$users['wbeditor']->user
			);

			$this->fail( "Adding another item with the same sitelinks should have failed" );
		}
		catch ( \UsageException $e ) {
			$this->assertTrue( ($e->getCodeString() == 'save-failed'), "Expected set-sitelink-failed, got unexpected exception: $e" );
		}
	}

	/**
	 * Check success of item update with a valid id
	 */
	function testSetItemWithId() {
		$token = $this->getItemToken();

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbsetitem',
				'reason' => 'Some reason',
				'data' => json_encode( self::$item ),
				'token' => $token,
				'id' => self::$id,
			),
			null,
			false,
			self::$users['wbeditor']->user
		);

		$this->assertSuccess( $res, 'entity', 'id' );
		$this->assertSuccess( $res, 'entity', 'lastrevid' );
		$this->assertItemEquals( self::$expect, $res['entity'] );
	}

	/**
	 * Check success when the item is set again, with fields in the json that should be ignored
	 */
	function testSetItemWithIgnoredData() {
		$token = $this->getItemToken();

		// these sets of failing data must be merged with an existing item
		$ignoredData = array(
			array( 'length' => 999999 ), // always ignored
			array( 'count' => 999999 ), // always ignored
			array( 'touched' => '2000-01-01T18:05:01Z' ), // always ignored
			array( 'pageid' => 999999 ),
			array( 'ns' => 200 ),
			array( 'title' => 'does-not-exist' ),
			array( 'lastrevid' => 99999999 ),
		);
		foreach ( $ignoredData as $data ) {
			try {
				list($res,,) = $this->doApiRequest(
					array(
						'action' => 'wbsetitem',
						'reason' => 'Some reason',
						'data' => json_encode( array_merge( self::$item, $data ) ),
						'token' => $token,
						'id' => self::$id, // will now use default type
						'exclude' => 'pageid|ns|title|lastrevid'
					),
					null,
					false,
					self::$users['wbeditor']->user
				);
				$this->assertSuccess( $res, 'entity', 'id' );
				$this->assertItemEquals( self::$expect, $res['entity'] );
			}
			catch ( \UsageException $e ) {
				$this->fail( "Got unexpected exception: $e" );
			}
		}
	}

	/**
	 * Check failure to set the same item again, with illegal field values in the json
	 */
	function testSetItemWithIllegalData() {
		$token = $this->getItemToken();

		// these sets of failing data must be merged with an existing item
		$failingData = array( //@todo: check each of these separately, so we know that each one fails!
			array( 'pageid' => 999999 ),
			array( 'ns' => 200 ),
			array( 'title' => 'does-not-exist' ),
			array( 'lastrevid' => 99999999 ),
		);
		foreach ( $failingData as $data ) {
			try {
				list($res,,) = $this->doApiRequest(
					array(
						'action' => 'wbsetitem',
						'reason' => 'Some reason',
						'data' => json_encode( array_merge( self::$item, $data ) ),
						'token' => $token,
						'id' => self::$id,
						//'exclude' => '' // make sure all critical values are checked per default
					),
					null,
					false,
					self::$users['wbeditor']->user
				);
				$this->fail( "Updating the item with wrong data should have failed" );
			}
			catch ( \UsageException $e ) {
				$this->assertTrue( ($e->getCodeString() == 'illegal-field'), "Expected illegal-field, got unexpected exception: $e" );
			}
		}
	}

	/**
	 * Check success to set the same item again, with legal field values in the json
	 */
	function testSetItemWithLegalData() {
		$token = $this->getItemToken();

		// request the test data from the item itself
		list($query,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetentities',
				'props' => 'info',
				'ids' => self::$id
			),
			null,
			false,
			self::$users['wbeditor']->user
		);
		$this->assertSuccess( $query, 'entities', self::$id, 'id' );

		// these sets of failing data must be merged with an existing item
		$goodData = array(
			array( 'pageid' => $query['entities'][self::$id]['pageid'] ),
			array( 'ns' => $query['entities'][self::$id]['ns'] ),
			array( 'title' => $query['entities'][self::$id]['title'] ),
			array( 'lastrevid' => $query['entities'][self::$id]['lastrevid'] ),
		);

		foreach ( $goodData as $data ) {
			try {
				list($res,,) = $this->doApiRequest(
					array(
						'action' => 'wbsetitem',
						'reason' => 'Some reason',
						'data' => json_encode( array_merge( $data, self::$item ) ),
						'token' => $token,
						'id' => self::$id,
						//'exclude' => '' // make sure all critical values are checked per default
					),
					null,
					false,
					self::$users['wbeditor']->user
				);
				$this->assertSuccess( $res, 'entity', 'id' );
				$this->assertItemEquals( self::$expect, $res['entity'] );
			}
			catch ( \UsageException $e ) {
				$this->fail( "Got unexpected exception: $e" );
			}
		}
	}

	/**
	 * Check if it possible to clear out the content of the object
	 */
	function testSetItemEmptyData() {
		$token = $this->getItemToken();

		try {
			list($res,,) = $this->doApiRequest(
				array(
					'action' => 'wbsetitem',
					'reason' => 'Some reason',
					'data' => json_encode( array() ),
					'token' => $token,
					'id' => self::$id,
					'clear' => true,
					//'exclude' => '' // make sure all critical values are checked per default
				),
				null,
				false,
				self::$users['wbeditor']->user
			);
			$this->assertSuccess( $res, 'entity', 'id' );
			$this->assertItemEquals( array( 'id' => self::$id ), $res['entity'] );
		}
		catch ( \UsageException $e ) {
			$this->fail( "Got unexpected exception: $e" );
		}
	}

	function provideBadData() {
		return array(
			// explicit ID is invalid
			array(
				array(
					"id" => 1234567
				),
				"not-recognized"
			),

			// random stuff is invalid
			array(
				array(
					"foo" => "xyz"
				),
				"not-recognized"
			),

			//-----------------------------------------------
/*
			// aliases have to be one list per language
			array(
				array(
					"aliases" => array(
						array( "language" => "de", "value" => "foo" ),
					)
				),
				"not-recognized-array"
			),

			// labels have to be one value per language
			array(
				array(
					"labels" => array(
						array( "language" => "de", "value" => "foo" ),
					)
				),
				"not-recognized-string"
			),

			// descriptions have to be one value per language
			array(
				array(
					"descriptions" => array(
						array( "language" => "de", "value" => "foo" ),
					)
				),
				"not-recognized-string"
			),
*/
			//-----------------------------------------------

			// aliases have to use valid language codes
			array(
				array(
					"aliases" => array(
						array( "language" => "*", "value" => "foo" ),
					)
				),
				"not-recognized-language"
			),

			// labels have to use valid language codes
			array(
				array(
					"labels" => array(
						array( "language" => "*", "value" => "foo" ),
					)
				),
				"not-recognized-language"
			),

			// descriptions have to use valid language codes
			array(
				array(
					"descriptions" => array(
						array( "language" => "*", "value" => "foo" ),
					)
				),
				"not-recognized-language"
			),

			//-----------------------------------------------

			// aliases have to be an array
			array(
				array(
					"aliases" => 15
				),
				"not-recognized-array"
			),

			// labels have to be an array
			array(
				array(
					"labels" => 15
				),
				"not-recognized-array"
			),

			// descriptions be an array
			array(
				array(
					"descriptions" => 15
				),
				"not-recognized-array"
			),

			//-----------------------------------------------

			// json must be valid
			array(
				'',
				"json-invalid"
			),

			// json must be an object
			array(
				'123', // json_decode *will* decode this as an int!
				"not-recognized-array"
			),

			// json must be an object
			array(
				'"foo"', // json_decode *will* decode this as a string!
				"not-recognized-array"
			),

			// json must be an object
			array(
				'[ "xyz" ]', // json_decode *will* decode this as an indexed array.
				"not-recognized-string"
			),
		);
	}

	/**
	 * @dataProvider provideBadData
	 */
	function testSetItemBadData( $data, $expectedErrorCode ) {
		$token = $this->getItemToken();

		try {
			$this->doApiRequest(
				array(
					'action' => 'wbsetitem',
					'reason' => 'Some reason',
					'data' => is_string( $data ) ? $data : json_encode( $data ),
					'token' => $token,
				),
				null,
				false,
				self::$users['wbeditor']->user
			);

			$this->fail( "Adding item should have failed: " . ( is_string( $data ) ? $data : json_encode( $data ) ) );
		}
		catch ( \UsageException $e ) {
			$this->assertTrue( ($e->getCodeString() == $expectedErrorCode), "Expected $expectedErrorCode, got unexpected exception: $e" );
		}
	}

	function provideSetItemData() {
		return array(
			array( //0: labels
				'Berlin', // handle
				array(    // input
					'labels' => array(
						array( "language" => 'de', "value" => '' ), // remove existing
						array( "language" => 'ru', "value" => '' ), // remove non-existing
						array( "language" => 'en', "value" => 'Stuff' ),  // change existing
						array( "language" => 'fr', "value" => 'Berlin' ), // add new
					)
				),
				array(    // expected
					'labels' => array(
						"en" => "Stuff",
						"no" => "Berlin",
						"nn" => "Berlin",
						"fr" => "Berlin",
					)
				),
			),

			array( //1: descriptions
				'Berlin', // handle
				array(    // input
					'descriptions' => array(
						array( "language" => 'de', "value" => '' ), // remove existing
						array( "language" => 'ru', "value" => '' ), // remove non-existing
						array( "language" => 'en', "value" => 'Stuff' ),   // change existing
						array( "language" => 'fr', "value" => 'Bla bla' ), // add new
					)
				),
				array(    // expected
					'descriptions' => array(
						"en"  => "Stuff",
						"no"  => "Hovedsted og delstat og i Forbundsrepublikken Tyskland.",
						"nn"  => "Hovudstad og delstat i Forbundsrepublikken Tyskland.",
						"fr"  => "Bla bla",
					)
				),
			),

			array( //2: aliases
				'Berlin', // handle
				array(    // input
					'aliases' => array(
						// TODO: It should be possible to unconditionally clear the list
						//"de" => array(), // remove existing
						//"ru" => array(), // remove non-existing
						array( "language" => "de", "value" => "Dickes B", "remove" => "" ), // remove existing
						array( "language" => "en", "value" => "Bla bla" ), // change existing
						array( "language" => "fr", "value" => "Bla bla" ), // add new
					)
				),
				array(    // expected
					'aliases' => array(
						"en"  => array( "Bla bla" ),
						"nl"  => array( "Dickes B" ),
						"fr"  => array( "Bla bla" ),
					)
				),
			),

			array( //3: sitelinks
				'Berlin', // handle
				array(    // input
					'sitelinks' => array(
						array( 'site' => 'dewiki', 'title' => '' ), // remove existing
						array( 'site' => 'srwiki', 'title' => '' ), // remove non-existing
						array( 'site' => "nnwiki", 'title' => "Berlin X" ), // change existing
						array( 'site' => "svwiki", 'title' => "Berlin X" ), // add new
					)
				),
				array(    // expected
					'sitelinks' => array(
						"enwiki" => "Berlin",
						"nlwiki" => "Berlin",
						"nnwiki" => "Berlin X",
						"svwiki" => "Berlin X",
					)
				),
			),
		);
	}

	/**
	 * @dataProvider provideSetItemData
	 */
	function testSetItemData( $handle, $data, $expected = null ) {
		$id = $this->getItemId( $handle );
		$token = $this->getItemToken();

		// wbsetitem ------------------------------------------------------
		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbsetitem',
				'reason' => 'Some reason',
				'data' => json_encode( $data ),
				'token' => $token,
				'id' => $id,
			),
			null,
			false,
			self::$users['wbeditor']->user
		);

		// check returned keys -------------------------------------------
		$this->assertSuccess( $res, 'entity' );
		$item = $res['entity'];
		$this->assertSuccess( $res, 'entity', 'id' );
		$this->assertSuccess( $res, 'entity', 'lastrevid' );

		// check return value -------------------------------------------
		$this->assertEquals( \Wikibase\Item::ENTITY_TYPE,  $res['entity']['type'] );

		// check relevant entries
		foreach ( $expected as $key => $exp ) {
			$this->assertArrayHasKey( $key, $item );
			$this->assertArrayEquals( $exp, static::flattenValues( $key, $item[$key] ) );
		}

		// check item in database -------------------------------------------
		$item = $this->loadItem( $id );

		// check relevant entries
		foreach ( $expected as $key => $exp ) {
			$this->assertArrayHasKey( $key, $item );
			$this->assertArrayEquals( $exp, static::flattenValues( $key, $item[$key] ) );
		}

		// cleanup ------------------------------------------------------
		$this->resetItem( $handle );
	}

	static function flattenValues( $prop, $values ) {
		if ( !is_array( $values ) ) {
			return $values;
		} elseif ( $prop == 'sitelinks' ) {
			return self::flattenArray( $values, 'site', 'title' );
		} elseif ( $prop == 'aliases' ) {
			return self::flattenArray( $values, 'language', 'value', true );
		} else {
			return self::flattenArray( $values, 'language', 'value' );
		}
	}


	function testProtectedItem() { //TODO
		$this->markTestIncomplete();
	}

	function testBlockedUser() { //TODO
		$this->markTestIncomplete();
	}

	function testEditPermission() { //TODO
		$this->markTestIncomplete();
	}

}
