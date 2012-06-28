<?php

namespace Wikibase\Test;
use \Wikibase\ItemDeletionUpdate as ItemDeletionUpdate;
use \Wikibase\ItemContent as ItemContent;

/**
 *  Tests for the Wikibase\ItemDeletionUpdate class.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WUHA
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
class ItemDeletionUpdateTest extends \MediaWikiTestCase {

	public function testConstruct() {
		$this->assertInstanceOf( '\Wikibase\ItemDeletionUpdate', new ItemDeletionUpdate( \Wikibase\ItemContent::newEmpty() ) );
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
		$itemContent->save();
		$update = new ItemDeletionUpdate( $itemContent );
		$update->doUpdate();

		$id = $itemContent->getItem()->getId();

		$this->assertEquals( 0, $this->countRows( 'wb_items', array( 'item_id' => $id ) ) );
		$this->assertEquals( 0, $this->countRows( 'wb_items_per_site', array( 'ips_item_id' => $id ) ) );
		$this->assertEquals( 0, $this->countRows( 'wb_aliases', array( 'alias_item_id' => $id ) ) );
		$this->assertEquals( 0, $this->countRows( 'wb_texts_per_lang', array( 'tpl_item_id' => $id ) ) );
	}

	protected function countRows( $table, array $conds = array() ) {
		return wfGetDB( DB_SLAVE )->selectRow(
			$table,
			array( 'COUNT(*) AS rowcount' ),
			$conds
		)->rowcount;
	}

}