<?php

namespace Wikibase\Test;

/**
 * Tests for the Wikibase\ClaimSerializer class.
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
class ClaimSerializerTest extends ApiSerializerBaseTest {

	/**
	 * @see ApiSerializerBaseTest::getClass
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\ClaimSerializer';
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

		$id = new \Wikibase\EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$validArgs[] = new \Wikibase\ClaimObject( new \Wikibase\PropertyNoValueSnak( $id ) );

		$validArgs[] = new \Wikibase\ClaimObject( new \Wikibase\PropertySomeValueSnak( $id ) );

		$validArgs = $this->arrayWrap( $validArgs );

		$claim = new \Wikibase\ClaimObject( new \Wikibase\PropertyNoValueSnak( $id ) );

		$validArgs[] = array(
			$claim,
			array(
				'id' => $claim->getGuid(),
				'mainsnak' => array(
					'snaktype' => 'novalue',
					'property' => 'p42',
				),
				'qualifiers' => array(),
				'type' => 'claim',
			),
		);

		$statement = new \Wikibase\StatementObject( new \Wikibase\PropertyNoValueSnak( $id ) );

		$validArgs[] = array(
			$statement,
			array(
				'id' => $statement->getGuid(),
				'mainsnak' => array(
					'snaktype' => 'novalue',
					'property' => 'p42',
				),
				'qualifiers' => array(),
				'references' => array(),
				'rank' => $statement->getRank(),
				'type' => 'statement',
			),
		);

		return $validArgs;
	}

}
