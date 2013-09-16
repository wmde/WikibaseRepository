<?php

namespace Wikibase\Test;

use Language;
use Wikibase\SettingsArray;
use Wikibase\StoreFactory;
use Wikibase\EntityPerPageBuilder;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\EntityPerPageBuilder
 *
 * @file
 * @since 0.4
 *
 * @ingroup WikibaseRepoTest
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseStore
 * @group WikibaseEntityPerPage
 * @group Database
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityPerPageBuilderTest extends \MediaWikiTestCase {

	protected $entityPerPageTable;

	protected $entityPerPageRows;

	/**
	 * @var WikibaseRepo
	 */
	protected $wikibaseRepo;

	public function setUp() {
		parent::setUp();

		$settings = $this->getTestSettings();
		$store = StoreFactory::getStore( 'sqlstore' );
		$lang = Language::factory( 'en' );

		$this->wikibaseRepo = new WikibaseRepo(
			$settings,
			$store,
			$lang
		);

		$this->entityPerPageTable = $store->newEntityPerPage();

		$this->clearTables();
		$this->addItems();

		assert( $this->countPages() === 10 );

		$this->entityPerPageRows = $this->getEntityPerPageData();
	}

	/**
	 * @since 0.4
	 *
	 * @return \User
	 */
	protected function getUser() {
		$user = \User::newFromName( 'zombie1' );

		if ( $user->getId() === 0 ) {
			$user = \User::createNew( $user->getName() );
		}

		return $user;
	}

	protected function getTestSettings() {
		$settings = new SettingsArray( array(
			'entityNamespaces' => array(
				'wikibase-item' => 0,
				'wikibase-property' => 102
			)
		) );

		return $settings;
	}

	protected function clearTables() {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->delete( 'page', array( "1" ) );
		$this->entityPerPageTable->clear();

		assert( $this->countPages() === 0 );
		assert( $this->countEntityPerPageRows() === 0 );
	}

	protected function addItems() {
		$user = $this->getUser();

		$labels = array( 'Berlin', 'New York City', 'Tokyo', 'Jakarta', 'Nairobi',
			'Rome', 'Cairo', 'Santiago', 'Sydney', 'Toronto' );

		foreach( $labels as $label ) {
			$itemContent = \Wikibase\ItemContent::newEmpty();
			$itemContent->getEntity()->setLabel( 'en', $label );
			$itemContent->save( "added an item", $user, EDIT_NEW );
		}
	}

	protected function partialClearEntityPerPageTable( $pageId ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'wb_entity_per_page', array( 'epp_page_id > ' . $pageId ) );
	}

	/**
	 * @return int
	 */
	protected function getPageIdForPartialClear() {
		$offset = floor( $this->countPages() / 2 );

		$dbw = wfGetDB( DB_MASTER );
		$pageRow = $dbw->select(
			'page',
			'page_id',
			array(),
			__METHOD__,
			array(
				'LIMIT' =>  1,
				'OFFSET' => 5,
				'ORDER BY' => ' page_id ASC'
			)
		);

		foreach( $pageRow as $row ) {
			$pageId = (int)$row->page_id;
		}

		return $pageId;
	}

	/**
	 * @since 0.4
	 *
	 * @return int
	 */
	protected function countPages() {
		$dbw = wfGetDB( DB_MASTER );
		$pages = $dbw->select( 'page', array( 'page_id' ), array(), __METHOD__ );

		return $pages->numRows();
	}

	/**
	 * @since 0.4
	 *
	 * @return int
	 */
	protected function countEntityPerPageRows() {
		$dbw = wfGetDB( DB_MASTER );
		$eppRows = $dbw->select( 'wb_entity_per_page', array( 'epp_entity_id' ), array(), __METHOD__ );

		return $eppRows->numRows();
	}

	/**
	 * @since 0.4
	 *
	 * @return array
	 */
	protected function getEntityPerPageData() {
		$dbw = wfGetDB( DB_MASTER );
		$rows = $dbw->select( 'wb_entity_per_page', array( 'epp_entity_id', 'epp_page_id' ), array(), __METHOD__ );

		$pages = array();

		foreach ( $rows as $row ) {
			$pages[] = array( 'page_id' => $row->epp_page_id, 'entity_id' => $row->epp_entity_id );
		}

		return $pages;
	}

	public function testRebuildAll() {
		$this->entityPerPageTable->clear();

		assert( $this->countEntityPerPageRows() === 0 );

		$builder = new EntityPerPageBuilder(
			$this->entityPerPageTable,
			$this->wikibaseRepo->getEntityContentFactory(),
			$this->wikibaseRepo->getEntityIdParser()
		);

		$builder->setRebuildAll( true );
		$builder->rebuild();

		$this->assertEquals( $this->countEntityPerPageRows(), 10 );

		$dbw = wfGetDB( DB_MASTER );

		foreach( $this->entityPerPageRows as $row ) {
			$res = $dbw->selectRow( 'wb_entity_per_page', array( 'epp_entity_id', 'epp_page_id' ),
				array( 'epp_page_id' => $row['page_id'] ), __METHOD__ );
			$this->assertEquals( $res->epp_entity_id, $row['entity_id'] );
		}
	}

	public function testRebuildPartial() {
		$pageId = $this->getPageIdForPartialClear();
		$this->partialClearEntityPerPageTable( $pageId );

		assert( $this->countEntityPerPageRows() === 6 );

		$builder = new EntityPerPageBuilder(
			$this->entityPerPageTable,
			$this->wikibaseRepo->getEntityContentFactory(),
			$this->wikibaseRepo->getEntityIdParser()
		);

		$builder->rebuild();

		$this->assertEquals( 10, $this->countEntityPerPageRows() );

		$dbw = wfGetDB( DB_MASTER );

		foreach( $this->entityPerPageRows as $row ) {
			$res = $dbw->selectRow( 'wb_entity_per_page', array( 'epp_entity_id', 'epp_page_id' ),
				array( 'epp_page_id' => $row['page_id'] ), __METHOD__ );
			$this->assertEquals( $res->epp_entity_id, $row['entity_id'] );
		}
	}
}
