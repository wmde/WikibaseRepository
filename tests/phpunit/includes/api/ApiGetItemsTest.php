<?php

namespace Wikibase\Test;
use ApiTestCase, ApiTestUser;
use Wikibase\Settings as Settings;

/**
 * Tests for the ApiWikibase class.
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
 * @group ApiGetItemsTest
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 */
class ApiGetItemTest extends ApiModifyItemBase {


	/**
	 * @group API
	 */
	function testGetToken() {
		if ( !self::$usetoken ) {
			$this->markTestSkipped( "tokens disabled" );
			return;
		}

		$data = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'ids' => '23',
				'gettoken' => '' ),
			null,
			false,
			self::$users['wbeditor']->user
		);

		// this should always hold for a logged in user
		// unless we do some additional tricks with the token
		$this->assertEquals(
			34, strlen( $data[0]["wbgetitems"]["itemtoken"] ),
			"The length of the token is not 34 chars"
		);
		$this->assertRegExp(
			'/\+\\\\$/', $data[0]["wbgetitems"]["itemtoken"],
			"The final chars of the token is not '+\\'"
		);
	}

	/**
	 * @dataProvider provideItemHandles
	 */
	function testGetItemById( $handle ) {
		$this->createItems();

		$item = $this->getItemOutput( $handle );
		$id = $item['id'];

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'ids' => $id )
		);

		$this->assertSuccess( $res, 'items', $id );
		$this->assertItemEquals( $item,  $res['items'][$id] );
		$this->assertEquals( 1, count( $res['items'] ), "requesting a single item should return exactly one item entry" );
	}

	public function provideGetItemByTitle() {
		$calls = array();
		$handles = $this->getItemHandles();

		foreach ( $handles as $handle ) {
			$item = $this->getItemInput( $handle );

			if ( !isset( $item['sitelinks'] ) ) {
				continue;
			}

			foreach ( $item['sitelinks'] as $site => $title ) {
				$calls[] = array( $handle, $site, $title );
			}
		}

		return $calls;
	}

	/**
	 * Test basic lookup of items to get the id.
	 * This is really a fast lookup without reparsing the stringified item.
	 *
	 * @dataProvider provideGetItemByTitle
	 */
	public function testGetItemByTitle( $handle, $site, $title ) {
		list($res,,) = $this->doApiRequest( array(
			'action' => 'wbgetitems',
			'sites' => $site,
			'titles' => $title,
		) );

		$item = $this->getItemOutput( $handle );
		$id = $item['id'];

		$this->assertSuccess( $res, 'items', $id );
		$this->assertItemEquals( $item,  $res['items'][$id] );
		$this->assertEquals( 1, count( $res['items'] ), "requesting a single item should return exactly one item entry" );
	}

	/**
	 * Testing if we can get missing items if we do lookup with single fake ids.
	 * Note that this makes assumptions about which ids have been assigned.
	 *
	 * @group API
	 */
	public function testGetItemsByBadId( ) {
		$badid =  123456789;
		list( $res,, ) = $this->doApiRequest( array(
			'action' => 'wbgetitems',
			'ids' => $badid,
		) );

		$this->assertSuccess( $res, 'items', $badid, 'missing' );
		$this->assertEquals( 1, count( $res['items'] ), "requesting a single item should return exactly one item entry" );
	}

	/**
	 * Testing if we can get missing items if we do lookup with failing titles.
	 * Note that this makes assumptions about which ids they have been assigned.
	 *
	 * @group API
	 */
	public function testGetItemsByBadTitle( ) {
		list( $res,, ) = $this->doApiRequest( array(
			'action' => 'wbgetitems',
			'sites' => 'enwiki',
			'titles' => 'klaijehrnqowienxcqopweiu',
		) );

		$this->assertSuccess( $res, 'items' );

		$keys = array_keys( $res['items'] );
		$this->assertEquals( 1, count( $keys ), "requesting a single item should return exactly one item entry" );

		$this->assertSuccess( $res, 'items', $keys[0], 'missing' );
	}

	/**
	 * Testing if we can get all the complete stringified items if we do lookup with multiple ids.
	 *
	 * @group API
	 */
	public function testGetItemsMultipleIds() {
		$handles = $this->getItemHandles();
		$ids = array_map( array( $this, 'getItemId' ), $handles );

		list( $res,, ) = $this->doApiRequest( array(
			'action' => 'wbgetitems',
			'ids' => join( '|', $ids ),
		) );

		$this->assertSuccess( $res, 'items' );
		$this->assertEquals( count( $ids ), count( $res['items'] ), "the actual number of items differs from the number of requested items" );

		foreach ( $ids as $id ) {
			$this->assertArrayHasKey( $id, $res['items'], "missing item" );
			$this->assertEquals( $id, $res['items'][$id]['id'], "bad ID" );
		}
	}

	/**
	 * Testing if we can get all the complete stringified items if we do lookup with multiple site-title pairs.
	 *
	 * @group API
	 */
	public function testGetItemsMultipleSiteLinks() {
		$handles = array( 'Berlin', 'London', 'Oslo' );
		$sites = array( 'dewiki', 'enwiki', 'nlwiki' );
		$titles = array( 'Berlin', 'London', 'Oslo' );

		list( $res,, ) = $this->doApiRequest( array(
			'action' => 'wbgetitems',
			'sites' => join( '|', $sites ),
			'titles' => join( '|', $titles )
		) );

		$this->assertSuccess( $res, 'items' );
		$this->assertEquals( count( $titles ), count( $res['items'] ), "the actual number of items differs from the number of requested items" );

		foreach ( $handles as $handle ) {
			$id = $this->getItemId( $handle );

			$this->assertArrayHasKey( $id, $res['items'], "missing item" );
			$this->assertEquals( $id, $res['items'][$id]['id'], "bad ID" );
		}
	}

	function provideLanguages() {
		return array(
			array( 'Berlin', array( 'en', 'de' ) ),
			array( 'Leipzig', array( 'en', 'de' ) ),
			array( 'Leipzig', array( 'fr', 'nl' ) ),
			array( 'London', array( 'nl' ) ),
			array( 'Empty', array( 'nl' ) ),
		);
	}

	/**
	 * @dataProvider provideLanguages
	 */
	function testLanguages( $handle, $languages ) {
		$this->createItems();

		$id = $this->getItemId( $handle );

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'languages' => join( '|', $languages ),
				'ids' => $id )
		);

		$this->assertSuccess( $res, 'items', $id );

		$item = $res['items'][$id];

		foreach ( $item as $prop => $values ) {
			if ( !is_array( $values ) ) {
				continue;
			}

			if ( $prop === 'sitelinks' ) {
				continue;
			}

			$values = static::flattenArray( $values, 'language', 'value' );

			$this->assertEmpty( array_diff( array_keys( $values ), $languages ),
								"found unexpected language in property $prop: " . var_export( $values, true ) );
		}
	}

	function provideProps() {
		return array(
			array( 'Berlin', '', array( 'id' ) ),
			array( 'Berlin', 'labels', array( 'id', 'labels' ) ),
			array( 'Berlin', 'labels|descriptions', array( 'id', 'labels', 'descriptions' ) ),
			array( 'Berlin', 'aliases|sitelinks', array( 'id', 'aliases', 'sitelinks' ) ),

			array( 'Leipzig', '', array( 'id' ) ),
			array( 'Leipzig', 'labels|descriptions', array( 'id', 'labels', 'descriptions' ) ),
			array( 'Leipzig', 'labels|aliases', array( 'id', 'labels' ) ),
			array( 'Leipzig', 'sitelinks|descriptions', array( 'id', 'descriptions' ) ),

			array( 'Berlin', 'xyz', false ),
		);
	}

	/**
	 * @dataProvider provideProps
	 */
	function testProps( $handle, $props, $expectedProps ) {
		$this->createItems();

		$id = $this->getItemId( $handle );

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'props' => $props,
				'ids' => $id )
		);

		$this->assertSuccess( $res, 'items', $id );

		if ( $expectedProps === false ) {
			$this->assertArrayHasKey( 'warnings', $res );
			$this->assertArrayHasKey( 'wbgetitems', $res['warnings'] );
		} else {
			$this->assertArrayEquals( $expectedProps, array_keys( $res['items'][$id] ) );
		}
	}

	function provideSitelinkUrls() {
		return array(
			array( 'Berlin' ),
			array( 'London' ),
		);
	}

	/**
	 * @dataProvider provideSitelinkUrls
	 */
	function testSitelinkUrls( $handle ) {
		$this->markTestSkipped( "enable once the sitelinks/urls options has been implemented" ); //FIXME: pending change I55ce5375
		return;

		$this->createItems();
		$id = $this->getItemId( $handle );

		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'props' => 'sitelinks',
				'ids' => $id )
		);

		$this->assertSuccess( $res, 'items', $id, 'sitelinks' );

		foreach ( $res['items'][$id]['sitelinks'] as $link ) {
			$this->assertArrayNotHasKey( 'url', $link );
		}

		// -------------------------------------------
		list($res,,) = $this->doApiRequest(
			array(
				'action' => 'wbgetitems',
				'props' => 'sitelinks|sitelinks/urls',
				'ids' => $id )
		);

		$this->assertSuccess( $res, 'items', $id, 'sitelinks' );

		foreach ( $res['items'][$id]['sitelinks'] as $link ) {
			$this->assertArrayHasKey( 'url', $link );
		}
	}
}

