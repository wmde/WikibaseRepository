<?php

namespace Wikibase\Test\Api;
use Wikibase\Entity;
use Wikibase\Claim;

/**
 * @covers Wikibase\Api\RemoveClaims
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
 * @ingroup WikibaseRepoTest
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group RemoveClaimsTest
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class RemoveClaimsTest extends WikibaseApiTestCase {

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
		$entity->addClaim( $entity->newClaim( new \Wikibase\PropertyValueSnak(
			$this->getNewProperty( 'string' )->getId(),
			new \DataValues\StringValue( 'o_O' ) ) )
		);

		$content->save( '' );

		return $content->getEntity();
	}

	public function entityProvider() {
		$property = \Wikibase\Property::newEmpty();
		$property->setDataTypeId( 'string' );

		return array(
			$this->addClaimsAndSave( \Wikibase\Item::newEmpty() ),
			$this->addClaimsAndSave( $property ),
		);
	}

	public function testValidRequests() {
		foreach ( $this->entityProvider() as $entity ) {
			$this->doTestValidRequestSingle( $entity );
		}

		foreach ( $this->entityProvider() as $entity ) {
			$this->doTestValidRequestMultiple( $entity );
		}
	}

	/**
	 * @param Entity $entity
	 */
	public function doTestValidRequestSingle( Entity $entity ) {
		/**
		 * @var Claim[] $claims
		 */
		$claims = $entity->getClaims();
		while ( $claim = array_shift( $claims ) ) {
			$this->makeTheRequest( array( $claim->getGuid() ) );

			$content = \Wikibase\EntityContentFactory::singleton()->getFromId( $entity->getId() );
			$obtainedClaims = new \Wikibase\Claims( $content->getEntity()->getClaims() );

			$this->assertFalse( $obtainedClaims->hasClaimWithGuid( $claim->getGuid() ) );

			$currentClaims = new \Wikibase\Claims( $claims );

			$this->assertTrue( $obtainedClaims->getHash() === $currentClaims->getHash() );
		}

		$this->assertTrue( $obtainedClaims->isEmpty() );
	}

	/**
	 * @param Entity $entity
	 */
	public function doTestValidRequestMultiple( Entity $entity ) {
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
			'claim' => implode( '|', $claimGuids ),
		);

		list( $resultArray, ) = $this->doApiRequestWithToken( $params );

		$this->assertInternalType( 'array', $resultArray, 'top level element is an array' );
		$this->assertArrayHasKey( 'claims', $resultArray, 'top level element has a claims key' );

		$claims = $resultArray['claims'];

		$this->assertInternalType( 'array', $claims, 'top claims element is an array' );

		$this->assertArrayEquals( $claimGuids, $claims );
	}

	/**
	 * @expectedException \UsageException
	 *
	 * @dataProvider invalidClaimProvider
	 */
	public function testRemoveInvalidClaims( $claimGuids ) {
		$params = array(
			'action' => 'wbremoveclaims',
			'claim' => is_array( $claimGuids ) ? implode( '|', $claimGuids ) : $claimGuids,
		);

		$this->doApiRequestWithToken( $params );
	}

	public function invalidClaimProvider() {
		return array(
			array( 'xyz' ), //wrong guid
			array( 'x$y$z' ), //wrong guid
		);
	}

	/**
	 * @param string $type
	 */
	protected function getNewProperty( $type ) {
		$property = \Wikibase\Property::newFromType( $type );
		$content = new \Wikibase\PropertyContent( $property );
		$content->save( '', null, EDIT_NEW );

		return $content->getEntity();
	}

}
