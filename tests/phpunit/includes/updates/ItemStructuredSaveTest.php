<?php

namespace Wikibase\Test;
use \Wikibase\ItemStructuredSave as ItemStructuredSave;
use \Wikibase\ItemContent as ItemContent;
use \Wikibase\Sites as Sites;

/**
 *  Tests for the Wikibase\ItemStructuredSave class.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group DataUpdate
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ItemStructuredSaveTest extends \MediaWikiTestCase {

	public function testConstruct() {
		$update = new ItemStructuredSave( ItemContent::newEmpty() );
		$this->assertInstanceOf( '\Wikibase\ItemStructuredSave', $update );
		$this->assertInstanceOf( '\DataUpdate', $update );
	}


	public function itemProvider() {
		return array_map(
			function( ItemContent $itemContent ) { return array( $itemContent ); },
			\Wikibase\Test\TestItemContents::getItems()
		);
	}

	/**
	 * @dataProvider itemProvider
	 * @param ItemContent $itemContent
	 */
	public function testDoUpdate( ItemContent $itemContent ) {
		\Wikibase\Utils::insertSitesForTests();

		$itemContent->save();
		$update = new ItemStructuredSave( $itemContent );
		$update->doUpdate();

		$item = $itemContent->getItem();
		$id = $item->getId();

		$this->assertEquals( 1, $this->countRows( 'wb_items', array( 'item_id' => $id ) ) );

		$this->assertEquals(
			count( $item->getSiteLinks() ),
			$this->countRows( 'wb_items_per_site', array( 'ips_item_id' => $id ) )
		);

		$this->assertEquals(
			array_sum( array_map( 'count', $item->getAllAliases() ) ),
			$this->countRows( 'wb_aliases', array( 'alias_item_id' => $id ) )
		);

		// TODO: verify texts_per_lang

		$update = new \Wikibase\ItemDeletionUpdate( $itemContent );
		$update->doUpdate();
	}

	protected function countRows( $table, array $conds = array() ) {
		return wfGetDB( DB_SLAVE )->selectRow(
			$table,
			array( 'COUNT(*) AS rowcount' ),
			$conds
		)->rowcount;
	}

}
