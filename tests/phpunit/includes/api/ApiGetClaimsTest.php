<?php

namespace Wikibase\Test;
use Wikibase\Entity;
use Wikibase\Claim;
use Wikibase\Claims;
use Wikibase\Statement;

/**
 * Unit tests for the Wikibase\ApiGetClaims class.
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
 * @ingroup WikibaseTest
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group ApiGetClaimsTest
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApiGetClaimsTest extends \ApiTestCase {

	/**
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	protected function addClaimsAndSave( Entity $entity ) {
		$content = \Wikibase\EntityContentFactory::singleton()->newFromEntity( $entity );
		$content->save( '', null, EDIT_NEW );

		$entity->addClaim( $entity->newClaim( new \Wikibase\PropertyNoValueSnak( 42 ) ) );
		$entity->addClaim( $entity->newClaim( new \Wikibase\PropertyNoValueSnak( 1 ) ) );
		$entity->addClaim( $entity->newClaim( new \Wikibase\PropertySomeValueSnak( 42 ) ) );
		$entity->addClaim( $entity->newClaim( new \Wikibase\PropertyValueSnak( 9001, new \DataValues\StringValue( 'o_O' ) ) ) );

		$content->save( '' );

		return $content->getEntity();
	}

	/**
	 * @return Entity[]
	 */
	protected function getNewEntities() {
		$property = \Wikibase\PropertyObject::newEmpty();
		$dataTypes = \Wikibase\Settings::get( 'dataTypes' );
		$property->setDataType( \DataTypes\DataTypeFactory::singleton()->getType( reset( $dataTypes ) ) );

		return array(
			$this->addClaimsAndSave( \Wikibase\ItemObject::newEmpty() ),
			$this->addClaimsAndSave( $property ),
		);
	}

	public function validRequestProvider() {
		$entities = $this->getNewEntities();

		$argLists = array();

		foreach ( $entities as $entity ) {
			$params = array(
				'action' => 'wbgetclaims',
				'entity' => $entity->getPrefixedId(),
			);

			$argLists[] = array( $params, $entity->getClaims() );

			/**
			 * @var Claim $claim
			 */
			foreach ( $entity->getClaims() as $claim ) {
				$params = array(
					'action' => 'wbgetclaims',
					'claim' => $claim->getGuid(),
				);

				$argLists[] = array( $params, array( $claim ) );
			}

			foreach ( array( Statement::RANK_DEPRECATED, Statement::RANK_NORMAL, Statement::RANK_PREFERRED ) as $rank ) {
				$params = array(
					'action' => 'wbgetclaims',
					'entity' => $entity->getPrefixedId(),
					'rank' => \Wikibase\ClaimSerializer::serializeRank( $rank ),
				);

				$claims = array();

				foreach ( $entity->getClaims() as $claim ) {
					if ( $claim instanceof Statement && $claim->getRank() === $rank ) {
						$claims[] = $claim;
					}
				}

				$argLists[] = array( $params, $claims );
			}
		}

		return $argLists;
	}

	public function testValidRequests() {
		foreach ( $this->validRequestProvider() as $argList ) {
			list( $params, $claims ) = $argList;

			$this->doTestValidRequest( $params, $claims );
		}
	}

	/**
	 * @param string[] $params
	 * @param Claims|Claim[] $claims
	 */
	public function doTestValidRequest( array $params, $claims ) {
		list( $resultArray, ) = $this->doApiRequest( $params );

		$this->assertInternalType( 'array', $resultArray, 'top level element is an array' );
		$this->assertArrayHasKey( 'claims', $resultArray, 'top level element has a claims key' );

		if ( is_array( $claims ) ) {
			$claims = new \Wikibase\ClaimList( $claims );
		}

		$serializer = new \Wikibase\ClaimsSerializer();
		$expected = $serializer->getSerialized( $claims );

		$byPropClaims = new \Wikibase\ByPropertyIdArray( $claims );
		$byPropClaims->buildIndex();

		// TODO: this is a rather simplistic test.
		// Would be nicer if we could deserialize the list and then use the equals method
		// or to serialize the expected value and have a recursive array compare on that
		foreach ( $expected as $propertyId => $claimsForProperty ) {
			$id = \Wikibase\EntityId::newFromPrefixedId( $propertyId );
			$this->assertEquals(
				count( $claimsForProperty ),
				count( $byPropClaims->getByPropertyId( $id->getNumericId() ) )
			);
		}
	}

}
