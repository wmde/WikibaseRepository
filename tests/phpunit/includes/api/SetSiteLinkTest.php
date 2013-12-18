<?php

namespace Wikibase\Test\Api;

use ApiTestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\ItemContent;

/**
 * @covers Wikibase\Api\SetSiteLink
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 * @author Adam Shorland
 * @author Michał Łazowik
 * @author Bene* < benestar.wikimedia@gmail.com >
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group SetSiteLinkTest
 * @group BreakingTheSlownessBarrier
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefore longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 */
class SetSiteLinkTest extends WikibaseApiTestCase {

	private static $hasSetup;

	public static function provideData() {
		return array(
			array( //0 set new link using id
				'p' => array( 'handle' => 'Leipzig', 'linksite' => 'dewiki', 'linktitle' => 'leipzig', 'badges' => 'Q42|Q149' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Leipzig', 'badges' => array( 'Q42', 'Q149' ) ) ) ) ),
			array( //1 set new link using sitelink
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'nowiki', 'linktitle' => 'berlin' ),
				'e' => array( 'value' => array( 'nowiki' => array( 'title' => 'Berlin', 'badges' => array() ) ), 'indb' => 5 ) ),
			array( //2 modify link using id
				'p' => array( 'handle' => 'Leipzig', 'linksite' => 'dewiki', 'linktitle' => 'Leipzig_Two', 'badges' => '' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Leipzig Two', 'badges' => array() ) ) ) ),
			array( //3 modify link using sitelink
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'nowiki', 'linktitle' => 'Berlin_Two' ),
				'e' => array( 'value' => array( 'nowiki' => array( 'title' => 'Berlin Two', 'badges' => array() ) ), 'indb' => 5 ) ),
			array( //4 remove link using id (with a summary)
				'p' => array( 'handle' => 'Leipzig', 'linksite' => 'dewiki', 'linktitle' => '', 'summary' => 'WooSummary' ),
				'e' => array( 'value' => array() ) ),
			array( //5 remove link using sitelink
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'nowiki', 'linktitle' => '' ),
				'e' => array( 'value' => array(), 'indb' => 4 ) ),
			array( //6 add badges to existing sitelink
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'dewiki', 'linktitle' => 'Berlin', 'badges' => 'Q149|Q42' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin', 'badges' => array( 'Q149', 'Q42' ) ) ), 'indb' => 4 ) ),
			array( //7 add duplicate badges to existing sitelink
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'dewiki', 'linktitle' => 'Berlin', 'badges' => 'Q42|q149|Q149|Q42' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin', 'badges' => array( 'Q42', 'Q149' ) ) ), 'indb' => 4 ) ),
			array( //8 no change
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'dewiki', 'linktitle' => 'Berlin', 'badges' => 'Q42|Q149' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin', 'badges' => array( 'Q42', 'Q149' ) ) ), 'warning' => 'edit-no-change', 'indb' => 4 ) ),
			array( //9 change only title, badges should be intact
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'dewiki', 'linktitle' => 'Berlin_Two' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin Two', 'badges' => array( 'Q42', 'Q149' ) ) ), 'indb' => 4 ) ),
			array( //10 change both title and badges
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin Two', 'linksite' => 'dewiki', 'linktitle' => 'Berlin', 'badges' => 'Q42' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin', 'badges' => array( 'Q42' ) ) ), 'indb' => 4 ) ),
			array( //11 change only badges, title intact
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'dewiki', 'badges' => 'Q42|Q149' ),
				'e' => array( 'value' => array( 'dewiki' => array( 'title' => 'Berlin', 'badges' => array( 'Q42', 'Q149' ) ) ), 'indb' => 4 ) ),
			array( //12 set new link using id (without badges)
				'p' => array( 'handle' => 'Berlin', 'linksite' => 'svwiki', 'linktitle' => 'Berlin' ),
				'e' => array( 'value' => array( 'svwiki' => array( 'title' => 'Berlin', 'badges' => array() ) ), 'indb' => 5 ) ),
			array( //13 delete link by not providing neither title nor badges
				'p' => array( 'handle' => 'Berlin', 'linksite' => 'svwiki' ),
				'e' => array( 'value' => array(), 'indb' => 4 ) ),
		);
	}

	public static function provideExceptionData() {
		return array(
			array( //0 badtoken
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'svwiki', 'linktitle' => 'testSetLiteLinkWithNoToken' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'badtoken', 'message' => 'loss of session data' ) ) ),
			array( //1 testSetLiteLinkWithNoId
				'p' => array( 'linksite' => 'enwiki', 'linktitle' => 'testSetLiteLinkWithNoId' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException' ) ) ),
			array( //2 testSetLiteLinkWithBadId
				'p' => array( 'id' => 123456789, 'linksite' => 'enwiki', 'linktitle' => 'testSetLiteLinkWithNoId' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException' ) ) ),
			array( //3 testSetLiteLinkWithBadSite
				'p' => array( 'site' => 'dewiktionary', 'title' => 'Berlin', 'linksite' => 'enwiki', 'linktitle' => 'Berlin' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException' ) ) ),
			array( //4 testSetLiteLinkWithBadTitle
				'p' => array( 'site' => 'dewiki', 'title' => 'BadTitle_de', 'linksite' => 'enwiki', 'linktitle' => 'BadTitle_en' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException' ) ) ),
			array( //5 testSetLiteLinkWithBadTargetSite
				'p' => array( 'site' => 'dewiki', 'title' => 'Berlin', 'linksite' => 'enwiktionary', 'linktitle' => 'Berlin' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException' ) ) ),
			array( //6 bad badge id
				'p' => array( 'site' => 'enwiki', 'title' => 'Berlin', 'linksite' => 'enwiki', 'linktitle' => 'Berlin', 'badges' => 'abc|Q149' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'no-such-entity-id' ) ) ),
			array( //7 badge id is not an item id
				'p' => array( 'site' => 'enwiki', 'title' => 'Berlin', 'linksite' => 'enwiki', 'linktitle' => 'Berlin', 'badges' => 'P2|Q149' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'not-item' ) ) ),
			array( //8 badge item does not exist
				'p' => array( 'site' => 'enwiki', 'title' => 'Berlin', 'linksite' => 'enwiki', 'linktitle' => 'Berlin', 'badges' => 'Q99999|Q149' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'no-such-entity' ) ) ),
			array( //9 no sitelink - cannot change badges
				'p' => array( 'site' => 'enwiki', 'title' => 'Berlin', 'linksite' => 'svwiki', 'badges' => 'Q42|Q149' ),
				'e' => array( 'exception' => array( 'type' => 'UsageException', 'code' => 'no-such-sitelink' ) ) ),
		);
	}

	public function setup() {
		parent::setup();

		$GA = new ItemId( "Q42" );
		$FA = new ItemId( "Q149" );

		if ( !isset( self::$hasSetup ) ) {
			$this->initTestEntities( array( 'Leipzig', 'Berlin' ) );

			$badge = ItemContent::newEmpty();
			$badge->getEntity()->setId( $GA );
			$badge->save( 'SetSiteLinkTestQ42' );

			$badge = ItemContent::newEmpty();
			$badge->getEntity()->setId( $FA );
			$badge->save( 'SetSiteLinkTestQ149' );
		}
		self::$hasSetup = true;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testSetLiteLink( $params, $expected ) {
		// -- set any defaults ------------------------------------
		if ( array_key_exists( 'handle', $params ) ) {
			$params['id'] = EntityTestHelper::getId( $params['handle'] );
			unset( $params['handle'] );
		}
		$params['action'] = 'wbsetsitelink';

		// -- do the request --------------------------------------------------
		list( $result, , ) = $this->doApiRequestWithToken( $params );

		//@todo all of the below is very similar to the code in ModifyTermTestCase
		//This might be able to go in the same place

		// -- check the result ------------------------------------------------
		$this->assertArrayHasKey( 'success', $result, "Missing 'success' marker in response." );
		$this->assertResultHasEntityType( $result );
		$this->assertArrayHasKey( 'entity', $result, "Missing 'entity' section in response." );
		$this->assertArrayHasKey( 'lastrevid', $result['entity'], 'entity should contain lastrevid key' );

		// -- check the result only has our changed data (if any)  ------------
		$linkSite = $params['linksite'];
		$sitelinks = $result['entity']['sitelinks'];

		$this->assertEquals( 1, count( $sitelinks ),
			"Entity return contained more than a single site"
		);

		$this->assertArrayHasKey( $linkSite, $sitelinks,
			"Entity doesn't return expected site"
		);

		$sitelink = $sitelinks[$linkSite];

		$this->assertEquals( $linkSite, $sitelink['site'],
			"Returned incorrect site"
		);

		if ( array_key_exists( $linkSite, $expected['value'] ) ) {
			$expSitelink = $expected['value'][ $linkSite ];

			$this->assertArrayHasKey( 'url', $sitelink );
			$this->assertEquals( $expSitelink['title'], $sitelink['title'],
				"Returned incorrect title"
			);

			$this->assertArrayHasKey( 'badges', $sitelink );
			$this->assertEquals( $expSitelink['badges'], $sitelink['badges'],
				"Returned incorrect badges"
			);
		} else if ( empty( $expected['value'] ) ) {
			$this->assertArrayHasKey( 'removed', $sitelink,
				"Entity doesn't return expected 'removed' marker"
			);
		}

		// -- check any warnings ----------------------------------------------
		if ( array_key_exists( 'warning', $expected ) ) {
			$this->assertArrayHasKey( 'warnings', $result, "Missing 'warnings' section in response." );
			$this->assertEquals( $expected['warning'], $result['warnings']['messages']['0']['name'] );
			$this->assertArrayHasKey( 'html', $result['warnings']['messages'] );
		}

		// -- check item in database -------------------------------------------
		$dbEntity = $this->loadEntity( $result['entity']['id'] );
		$expectedInDb = count( $expected['value'] );
		if ( array_key_exists( 'indb', $expected ) ) {
			$expectedInDb = $expected['indb'];
		}
		if ( $expectedInDb ) {
			$this->assertArrayHasKey( 'sitelinks', $dbEntity );

			foreach ( array( 'title', 'badges' ) as $prop ) {
				$dbSitelinks = self::flattenArray( $dbEntity['sitelinks'], 'site', $prop );
				$this->assertEquals( $expectedInDb, count( $dbSitelinks ) );
				foreach ( $expected['value'] as $valueSite => $value ) {
					$this->assertArrayHasKey( $valueSite, $dbSitelinks );
					$this->assertEquals( $value[$prop], $dbSitelinks[$valueSite],
						"'$prop' value is not correct"
					);
				}
			}
		} else {
			$this->assertArrayNotHasKey( 'sitelinks', $dbEntity );
		}

		// -- check the edit summary --------------------------------------------
		if ( ! array_key_exists( 'warning', $expected ) || $expected['warning'] != 'edit-no-change' ) {
			$this->assertRevisionSummary( array( 'wbsetsitelink', $params['linksite'] ), $result['entity']['lastrevid'] );
			if ( array_key_exists( 'summary', $params ) ) {
				$this->assertRevisionSummary( "/{$params['summary']}/", $result['entity']['lastrevid'] );
			}
		}
	}

	/**
	 * @dataProvider provideExceptionData
	 */
	public function testSetSiteLinkExceptions( $params, $expected ) {
		// -- set any defaults ------------------------------------
		$params['action'] = 'wbsetsitelink';
		$this->doTestQueryExceptions( $params, $expected['exception'] );
	}
}

