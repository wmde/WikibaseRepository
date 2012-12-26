<?php

namespace Wikibase\Test;
use Wikibase\ItemContent;
use Wikibase\Item;
use Wikibase\Utils;
use Wikibase\ItemView;

/**
 * Test WikibaseItemView.
 *
 * The tests are using "Database" to get its own set of temporal tables.
 * This is nice so we avoid poisoning an existing database.
 *
 * The tests are using "medium" so they are able to run alittle longer before they are killed.
 * Without this they will be killed after 1 second, but the setup of the tables takes so long
 * time that the first few tests get killed.
 *
 * The tests are doing some assumptions on the id numbers. If the database isn't empty when
 * when its filled with test items the ids will most likely get out of sync and the tests will
 * fail. It seems impossible to store the item ids back somehow and at the same time not being
 * dependant on some magically correct solution. That is we could use GetItemId but then we
 * would imply that this module in fact is correct.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseRepoTest
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseItemView
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
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
class ItemViewTest extends \MediaWikiTestCase {

	//@todo: make this a baseclass to use with all types of entities.

	protected static $num = -1;

	public function setUp() {
		parent::setUp();

		static $hasSites = false;

		if ( !$hasSites ) {
			\TestSites::insertIntoDb();
			$hasSites = true;
		}
	}

	/**
	 * @dataProvider providerGetHtml
	 */
	public function testGetHtml( $itemData, $expected ) {
		self::$num++;
		$view = new ItemView();

		if ( is_string( $expected ) ) {
			$expected = ( array )$expected;
		}
		$expected[] = '/class\s*=\s*"wb-entity wb-' . Item::ENTITY_TYPE . '"/';

		if( $itemData === false ) {
			$itemContent = ItemContent::newEmpty();
			$expected[] = '/id\s*=\s*"wb-' . Item::ENTITY_TYPE . '-new"/';
		} else {
			$itemData += array( 'entity' => Item::getIdPrefix() . '123' );
			$itemContent = ItemContent::newFromArray( $itemData );
			$expected[] = '/id\s*=\s*"wb-' . Item::ENTITY_TYPE . '-' . $itemContent->getEntity()->getPrefixedId() . '"/';
		}

		$itemContent->getEntity()->setLabel( 'de', 'Stockholm' );

		$this->assertTrue(
			!is_null( $itemContent ) && $itemContent !== false,
			"Could not find an item"
		);

		$this->assertTrue(
			!is_null( $view ) && $view !== false,
			"Could not find a view"
		);

		$html = $view->getHtml( $itemContent );

		foreach ( $expected as $that ) {
			$this->assertRegExp(
				$that,
				$html,
				"Could not find the marker '{$that}'"
			);
		}

	}

	/**
	 * @todo move this to an EntityViewTest class at some point
	 * @dataProvider providerNewForEntityContent
	 */
	public function testNewForEntityContent( $entityContent ) {
		// test whether we get the right EntityView from an EntityContent
		$view = ItemView::newForEntityContent( $entityContent );
		$this->assertType(
			ItemView::$typeMap[ $entityContent->getEntity()->getType() ],
			$view
		);
	}

	public function providerNewForEntityContent() {
		return array(
			array( ItemContent::newEmpty() ),
			array( \Wikibase\PropertyContent::newEmpty() )
		);
	}

	// Should use proper abstraction and not create items from arrays
	public function providerGetHtml() {
		return array(
			array(
				false,
				'/"wb-sitelinks"/'
			),
			array(
				array(
					'links'=> array(
						'enwiki' => 'Oslo',
					)
				),
				array(
					'/"wb-sitelinks"/',
					'/"wb-sitelinks-en uneven"/',
				)
			),
			array(
				array(
					'links'=> array(
						'dewiki' => 'Stockholm',
						'enwiki' => 'Oslo',
					)
				),
				array(
					'/"wb-sitelinks"/',
					'/"wb-sitelinks-de uneven"/',
					'/"wb-sitelinks-en even"/',
				)
			),
			array(
				array(
					'description'=> array(
						'en' => 'Capitol of Norway'
					),
					'links'=> array(
						'enwiki' => 'Oslo',
					),
				),
				array(
					'/"wb-sitelinks"/',
					'/<span class="wb-value ">\s*Capitol of Norway\s*<\/span>/',
				)
			),
		);
	}

}
