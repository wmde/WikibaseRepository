<?php

namespace Wikibase\Test;
use Wikibase\PropertyObject, Wikibase\Property;

/**
 * Tests for the Wikibase\PropertySerializer class.
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
 * @since 0.3
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
class PropertySerializerTest extends EntitySerializerBaseTest {

	/**
	 * @see ApiSerializerBaseTest::getClass
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\PropertySerializer';
	}

	/**
	 * @see EntitySerializerBaseTest::getEntityInstance
	 *
	 * @since 0.3
	 *
	 * @return Property
	 */
	protected function getEntityInstance() {
		$property = PropertyObject::newEmpty();
		$property->setId( 42 );
		return $property;
	}

	/**
	 * @see ApiSerializerBaseTest::validProvider
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function validProvider() {
		$validArgs = array();

		$validArgs = $this->arrayWrap( $validArgs );

		$property = $this->getEntityInstance();

		$dataTypes = \Wikibase\Settings::get( 'dataTypes' );

		$property->setDataTypeById( array_shift( $dataTypes ) );

		$validArgs[] = array(
			$property,
			array(
				'id' => $property->getPrefixedId(),
				'type' => $property->getType(),
				'datatype' => $property->getDataType()->getId()
			),
		);

		return $validArgs;
	}

}
