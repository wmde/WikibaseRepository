<?php

namespace Wikibase\Test;
use Wikibase\Entity;
use Wikibase\Claim;

/**
 * Unit tests for the Wikibase\ApiRemoveClaims class.
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
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApiRemoveClaimsTest extends \ApiTestCase {

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

	public function entityProvider() {
		$property = \Wikibase\PropertyObject::newEmpty();
		$dataTypes = \Wikibase\Settings::get( 'dataTypes' );
		$property->setDataType( \DataTypes\DataTypeFactory::singleton()->getType( reset( $dataTypes ) ) );

		return $this->arrayWrap( array(
			$this->addClaimsAndSave( \Wikibase\ItemObject::newEmpty() ),
			$this->addClaimsAndSave( $property ),
		) );
	}

	/**
	 * @dataProvider entityProvider
	 *
	 * @param Entity $entity
	 */
	public function testValidRequestSingle( Entity $entity ) {
		/**
		 * @var Claim[] $claims
		 */
		$claims = iterator_to_array( $entity->getClaims() );

		while ( $claim = array_shift( $claims ) ) {
			$this->makeTheRequest( array( $claim->getGuid() ) );

			$content = \Wikibase\EntityContentFactory::singleton()->getFromId( $entity->getId() );
			$obtainedEntity = $content->getEntity();

			$this->assertFalse( $obtainedEntity->hasClaimWithGuid( $claim->getGuid() ) );

			$currentClaims = new \Wikibase\ClaimList( $claims );
			$this->assertTrue( $obtainedEntity->getClaims()->getHash() === $currentClaims->getHash() );
		}

		$this->assertFalse( $obtainedEntity->hasClaims() );
	}

	/**
	 * @dataProvider entityProvider
	 *
	 * @param Entity $entity
	 */
	public function testValidRequestMultiple( Entity $entity ) {
		$guids = array();

		/**
		 * @var Claim $claim
		 */
		foreach ( $entity->getClaims() as $claim ) {
			$guids[] = $claim->getGuid();
		}

		$this->makeTheRequest( $guids );

		$content = \Wikibase\EntityContentFactory::singleton()->getFromId( $entity->getId() );
		$obtainedEntity = $content->getEntity();

		$this->assertFalse( $obtainedEntity->hasClaims() );
	}

	protected function makeTheRequest( array $claimGuids ) {
		$params = array(
			'action' => 'wbremoveclaims',
			'key' => implode( '|', $claimGuids )
		);

		list( $resultArray, ) = $this->doApiRequest( $params );

		$this->assertInternalType( 'array', $resultArray, 'top level element is an array' );
		$this->assertArrayHasKey( 'claims', $resultArray, 'top level element has a claims key' );

		$claims = $resultArray['claims'];

		$this->assertInternalType( 'array', $claims, 'top claims element is an array' );

		$this->assertArrayEquals( $claimGuids, $claims );
	}

}
