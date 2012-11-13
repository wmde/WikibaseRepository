<?php

namespace Wikibase\Test;
use Wikibase\EntityId;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\ClaimObject;
use Wikibase\StatementObject;

/**
 * Tests for the Wikibase\ClaimsSerializer class.
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
class ClaimsSerializerTest extends ApiSerializerBaseTest {

	/**
	 * @see ApiSerializerBaseTest::getClass
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\ClaimsSerializer';
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

		$propertyId = new EntityId( \Wikibase\Property::ENTITY_TYPE, 42 );

		$claims = array(
			new ClaimObject( new PropertyNoValueSnak( $propertyId ) ),
			new ClaimObject( new PropertySomeValueSnak( new EntityId( \Wikibase\Property::ENTITY_TYPE, 1 ) ) ),
			new StatementObject( new PropertyNoValueSnak( $propertyId ) ),
		);

		$claimSerializer = new \Wikibase\ClaimSerializer( new \ApiResult( new \ApiMain() ) );

		$validArgs[] = array(
			new \Wikibase\ClaimList( $claims ),
			array(
				'p42' => array(
					$claimSerializer->getSerialized( $claims[0] ),
					$claimSerializer->getSerialized( $claims[2] ),
				),
				'p1' => array(
					$claimSerializer->getSerialized( $claims[1] ),
				),
			),
		);

		return $validArgs;
	}

}
