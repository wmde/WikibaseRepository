<?php

namespace Wikibase\Test;

use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOpSiteLink;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * @covers Wikibase\ChangeOp\ChangeOpSiteLink
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Michał Łazowik
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ChangeOpSiteLinkTest extends \PHPUnit_Framework_TestCase {

	private function applySettings() {
		// Allow some badges for testing
		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting( 'badgeItems', array(
			'Q42' => '',
			'Q149' => '',
		) );
	}

	public function invalidConstructorProvider() {
		$this->applySettings();

		$argLists = array();

		$argLists[] = array( 'enwiki', 1234 );
		$argLists[] = array( 1234, 'Berlin' );
		$argLists[] = array( 'enwiki', 'Berlin', 'Nyan Certified' );
		$argLists[] = array( 'plwiki', 'Warszawa', array( 'FA', 'GA' ) );
		$argLists[] = array( 'plwiki', 'Warszawa', array( new ItemId( 'Q42' ), 'FA' ) );
		$argLists[] = array( 'plwiki', 'Warszawa', array( new PropertyId( 'P42' ) ) );
		$argLists[] = array( 'plwiki', 'Warszawa', array( new ItemId( 'Q3552127832535' ) ) );

		return $argLists;
	}

	/**
	 * @dataProvider invalidConstructorProvider
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructorWithInvalidArguments( $siteId, $linkPage, $badges = null ) {
		new ChangeOpSiteLink( $siteId, $linkPage, $badges );
	}

	public function changeOpSiteLinkProvider() {
		$this->applySettings();

		$deSiteLink = new SiteLink( 'dewiki', 'Berlin' );
		$enSiteLink = new SiteLink( 'enwiki', 'Berlin', array( new ItemId( 'Q149' ) ) );
		$plSiteLink = new SiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q42' ) ) );

		$existingSiteLinks = array(
			$deSiteLink,
			$plSiteLink
		);

		$item = Item::newEmpty();

		foreach ( $existingSiteLinks as $siteLink ) {
			$item->addSiteLink( $siteLink );
		}

		$args = array();

		// adding sitelink with badges
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'enwiki', 'Berlin', array( new ItemId( 'Q149' ) ) ),
			array_merge( $existingSiteLinks, array ( $enSiteLink ) )
		);

		// deleting sitelink
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'dewiki', null ),
			array( $plSiteLink )
		);

		// setting badges on existing sitelink
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q42' ), new ItemId( 'Q149' ) ) ),
			array(
				$deSiteLink,
				new SiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q42' ), new ItemId( 'Q149' ) ) )
			)
		);

		// changing sitelink without modifying badges
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'plwiki', 'Test' ),
			array(
				$deSiteLink,
				new SiteLink( 'plwiki', 'Test', array( new ItemId( 'Q42' ) ) )
			)
		);

		// change badges without modifying title
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'plwiki', null, array( new ItemId( 'Q149' ) ) ),
			array(
				$deSiteLink,
				new SiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q149' ) ) )
			)
		);

		// add duplicate badges
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'plwiki', null, array( new ItemId( 'q42' ), new ItemId( 'Q149' ), new ItemId( 'Q42' ) ) ),
			array(
				$deSiteLink,
				new SiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q42' ), new ItemId( 'Q149' ) ) )
			)
		);

		return $args;
	}

	/**
	 * @dataProvider changeOpSiteLinkProvider
	 *
	 * @param Item $entity
	 * @param ChangeOpSiteLink $changeOpSiteLink
	 * @param SiteLink[] $expectedSiteLinks
	 */
	public function testApply( Item $entity, ChangeOpSiteLink $changeOpSiteLink, array $expectedSiteLinks ) {
		$changeOpSiteLink->apply( $entity );

		$this->assertEquals(
			$expectedSiteLinks,
			$entity->getSiteLinks()
		);
	}

	public function invalidChangeOpSiteLinkProvider() {
		$this->applySettings();

		$deSiteLink = new SiteLink( 'dewiki', 'Berlin' );
		$plSiteLink = new SiteLink( 'plwiki', 'Berlin', array( new ItemId( 'Q42' ) ) );

		$existingSiteLinks = array(
			$deSiteLink,
			$plSiteLink
		);

		$item = Item::newEmpty();

		foreach ( $existingSiteLinks as $siteLink ) {
			$item->addSiteLink( $siteLink );
		}

		$args = array();

		// cannot change badges of non-existing sitelink
		$args[] = array(
			$item->copy(),
			new ChangeOpSiteLink( 'enwiki', null, array( new ItemId( 'Q149' ) ) ),
		);

		return $args;
	}

	/**
	 * @dataProvider invalidChangeOpSiteLinkProvider
	 *
	 * @param Item $entity
	 * @param ChangeOpSiteLink $changeOpSiteLink
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testApplyWithInvalidData( Item $entity, ChangeOpSiteLink $changeOpSiteLink ) {
		$changeOpSiteLink->apply( $entity );
	}

	public function summaryTestProvider() {
		$this->applySettings();

		$item = Item::newEmpty();
		$item->getSiteLinkList()->addNewSiteLink( 'dewiki', 'Berlin' );
		$item->getSiteLinkList()->addNewSiteLink( 'ruwiki', 'Берлин', array( new ItemId( 'Q42' ) ) );

		$cases = array();
		$badge = new ItemId( 'Q149' );

		// Add sitelink without badges
		$cases['add-sitelink-without-badges'] = array(
			'add',
			array( 'Berlin' ),
			$item->copy(),
			new ChangeOpSiteLink( 'enwiki', 'Berlin', array() )
		);

		// Add sitelink with badges
		$cases['add-sitelink-with-badges'] = array(
			'add-both',
			array( 'Berlin', array( $badge ) ),
			$item->copy(),
			new ChangeOpSiteLink( 'enwiki', 'Berlin', array( $badge ) )
		);

		// Set page name only for existing sitelink
		$cases['set-pagename-existing-sitelink'] = array(
			'set',
			array( 'London' ),
			$item->copy(),
			new ChangeOpSiteLink( 'ruwiki', 'London' )
		);

		// Add badge to existing sitelink
		$cases['add-badges-to-existing-sitelink'] = array(
			'set-badges',
			array( array( $badge ) ),
			$item->copy(),
			new ChangeOpSiteLink( 'dewiki', null, array( $badge ) )
		);

		// Set page name and badges for existing sitelink
		$cases['set-pagename-badges-existing-sitelink'] = array(
			'set-both',
			array( 'London', array( $badge ) ),
			$item->copy(),
			new ChangeOpSiteLink( 'dewiki', 'London', array( $badge ) ),
		);

		// Changes badges for existing sitelink
		$cases['change-badges-for-existing-sitelink'] = array(
			'set-badges',
			array( array( $badge ) ),
			$item->copy(),
			new ChangeOpSiteLink( 'ruwiki', null, array( $badge ) )
		);

		return $cases;
	}

	/**
	 * @dataProvider summaryTestProvider
	 *
	 * @param string $expectedAction
	 * @param array $expectedArguments
	 * @param Item $entity
	 * @param ChangeOpSiteLink $changeOpSiteLink
	 */
	public function testApplySummary(
		$expectedAction,
		array $expectedArguments,
		Item $entity,
		ChangeOpSiteLink $changeOpSiteLink
	) {
		$summary = new Summary();
		$changeOpSiteLink->apply( $entity, $summary );

		$this->assertSame(
			$expectedAction,
			$summary->getActionName()
		);

		$this->assertEquals(
			$expectedArguments,
			$summary->getAutoSummaryArgs()
		);
	}
}