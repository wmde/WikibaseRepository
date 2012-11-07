<?php

namespace Wikibase\Test;
use Wikibase\ItemObject, Wikibase\Item;

/**
 * Tests for the Wikibase\ItemSerializer class.
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
 * @since 0.2
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseApiSerialization
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ItemSerializerTest extends EntitySerializerBaseTest {

	/**
	 * @see ApiSerializerBaseTest::getClass
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\ItemSerializer';
	}

	/**
	 * @see EntitySerializerBaseTest::getEntityInstance
	 *
	 * @since 0.2
	 *
	 * @return Item
	 */
	protected function getEntityInstance() {
		$item = ItemObject::newEmpty();
		$item->setId( 42 );
		return $item;
	}

	/**
	 * @see ApiSerializerBaseTest::validProvider
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function validProvider() {
		$validArgs = array();

		$validArgs = $this->arrayWrap( $validArgs );

		$item = $this->getEntityInstance();

		$validArgs[] = array(
			$item,
			array(
				'id' => $item->getPrefixedId(),
				'type' => $item->getType(),
			),
		);

		$options = new \Wikibase\EntitySerializationOptions();
		$options->setProps( array( 'info', 'sitelinks', 'aliases', 'labels', 'descriptions', 'sitelinks/urls' ) );

		$validArgs[] = array(
			$item,
			array(
				'id' => $item->getPrefixedId(),
				'type' => $item->getType(),
				// Commented out because empty structures should
				// not be reported by the API, so can't be tested
				//'aliases' => array(),
				//'labels' => array(),
				//'descriptions' => array(),
				//'sitelinks' => array(),
			),
			$options,
		);

		foreach ( $this->semiValidProvider() as $argList ) {
			//$argList[1]['required'] = null;

			$validArgs[] = $argList;
		}

		return $validArgs;
	}

}
