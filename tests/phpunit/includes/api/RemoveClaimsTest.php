<?php

namespace Wikibase\Test\Api;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\DataModel\Entity\Property;
use Wikibase\PropertyContent;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Api\RemoveClaims

 * @since 0.3
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

	private static $propertyId;

	/**
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	protected function addClaimsAndSave( Entity $entity ) {
		$content = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->newFromEntity( $entity );
		$content->save( '', null, EDIT_NEW );

		if ( !isset( self::$propertyId ) ) {
			self::$propertyId = $this->getNewProperty( 'string' )->getId();
		}

		/** @var $claims Claim[] */
		$claims[0] = $entity->newClaim( new PropertyNoValueSnak( self::$propertyId ) );
		$claims[1] = $entity->newClaim( new PropertyNoValueSnak( self::$propertyId ) );
		$claims[2] = $entity->newClaim( new PropertySomeValueSnak( self::$propertyId ) );
		$claims[3] = $entity->newClaim(
			new PropertyValueSnak( self::$propertyId, new StringValue( 'o_O' ) )
		);

		foreach( $claims as $key => $claim ){
			$guidGenerator = new ClaimGuidGenerator( $entity->getId() );
			$claim->setGuid( $guidGenerator->newGuid() );
			$entity->addClaim( $claim );
		}

		$content->save( '' );

		return $content->getEntity();
	}

	public function entityProvider() {
		$property = Property::newEmpty();
		$property->setDataTypeId( 'string' );

		return array(
			$this->addClaimsAndSave( Item::newEmpty() ),
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

			$content = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromId( $entity->getId() );
			$obtainedClaims = new Claims( $content->getEntity()->getClaims() );

			$this->assertFalse( $obtainedClaims->hasClaimWithGuid( $claim->getGuid() ) );

			$currentClaims = new Claims( $claims );

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

		$content = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromId( $entity->getId() );
		$obtainedEntity = $content->getEntity();

		$this->assertFalse( $obtainedEntity->hasClaims() );
	}

	protected function makeTheRequest( array $claimGuids ) {
		$params = array(
			'action' => 'wbremoveclaims',
			'claim' => implode( '|', $claimGuids ),
		);

		list( $resultArray, ) = $this->doApiRequestWithToken( $params );

		$this->assertResultSuccess( $resultArray );
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
		$property = Property::newFromType( $type );
		$content = new PropertyContent( $property );
		$content->save( '', null, EDIT_NEW );

		return $content->getEntity();
	}

}
