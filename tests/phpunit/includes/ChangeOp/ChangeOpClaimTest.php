<?php

namespace Wikibase\Test;

use Wikibase\ChangeOp\ChangeOpClaim;
use Wikibase\Claim;
use Wikibase\Claims;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Entity;
use Wikibase\Item;
use InvalidArgumentException;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\SnakObject;

/**
 * @covers Wikibase\ChangeOp\ChangeOpModifyClaim
 *
 * @since 0.4
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 * @group ChangeOpClaim
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpClaimTest extends \PHPUnit_Framework_TestCase {

	public function invalidConstructorProvider() {
		$validGuidGenerator = new ClaimGuidGenerator( new ItemId( 'q42' ) );

		$args = array();
		$args[] = array( array(), $validGuidGenerator );

		return $args;
	}

	/**
	 * @dataProvider invalidConstructorProvider
	 * @expectedException InvalidArgumentException
	 *
	 * @param Claim $claim
	 * @param ClaimGuidGenerator $guidGenerator
	 */
	public function testInvalidConstruct( $claim, $guidGenerator ) {
		new ChangeOpClaim( $claim, $guidGenerator );
	}

	public function provideTestApply() {
		$itemEmpty = Item::newEmpty();
		$itemEmpty->setId( new ItemId( 'q888' ) );
		$item777 = self::provideNewItemWithClaim( 777, new PropertyNoValueSnak( 45 ) );
		$item666 = self::provideNewItemWithClaim( 666, new PropertySomeValueSnak( 44 ) );

		$item777Claims = $item777->getClaims();
		$item666Claims = $item666->getClaims();

		$claim777 = reset( $item777Claims );
		$claim666 = reset( $item666Claims );

		//claims that exist on the given entities
		$claims[0] = new Claim( new PropertyNoValueSnak( 43 ) );
		$claims[777] = clone $claim777;
		$claims[666] = clone $claim666;
		//claims with a null guid
		$claims[7770] = clone $claim777;
		$claims[7770]->setGuid( null );
		$claims[6660] = clone $claim666;
		$claims[6660]->setGuid( null );
		//new claims not yet on the entity
		$claims[7777] = clone $claim777;
		$claims[7777]->setGuid( 'Q777$D8404CDA-25E4-4334-AF13-A3290BC77777' );
		$claims[6666] = clone $claim666;
		$claims[6666]->setGuid( 'Q666$D8404CDA-25E4-4334-AF13-A3290BC66666' );

		$args = array();
		//test adding claims with guids from other items(these shouldn't be added)
		$args[] = array( $itemEmpty, $claims[666], false );
		$args[] = array( $itemEmpty, $claims[777], false );
		$args[] = array( $item666, $claims[777], false );
		$args[] = array( $item777, $claims[666], false );
		//test adding the same claims with a null guid (a guid should be created)
		$args[] = array( $item777, $claims[7770], array( $claims[777], $claims[7770] ) );
		$args[] = array( $item666, $claims[6660], array( $claims[666], $claims[6660] ) );
		//test adding the same claims with a correct but different guid (these should be added)
		$args[] = array( $item777, $claims[7777], array( $claims[777], $claims[7770], $claims[7777] ) );
		$args[] = array( $item666, $claims[6666], array( $claims[666], $claims[6660], $claims[6666] ) );
		//test adding the same claims with and id that already exists (these shouldn't be added)
		$args[] = array( $item777, $claims[7777], array( $claims[777], $claims[7770], $claims[7777] ) );
		$args[] = array( $item666, $claims[6666], array( $claims[666], $claims[6660], $claims[6666] ) );
		// test adding a claim at a specific index
		$args[] = array( $item777, $claims[0], array( $claims[0], $claims[777], $claims[7770], $claims[7777] ), 0 );
		// test moving a claim
		$args[] = array( $item666, $claims[6666], array( $claims[666], $claims[6666], $claims[6660] ), 1 );

		return $args;
	}

	/**
	 * @dataProvider provideTestApply
	 *
	 * @param Entity $entity
	 * @param Claim $claim
	 * @param Claim[]|bool $expected
	 * @param int|null $index
	 */
	public function testApply( $entity, $claim, $expected, $index = null ) {
		if( $expected === false ){
			$this->setExpectedException( '\Wikibase\ChangeOp\ChangeOpException' );
		}

		$changeOpClaim = new ChangeOpClaim(
			$claim,
			new ClaimGuidGenerator( $entity->getId() ),
			$index
		);
		$changeOpClaim->apply( $entity );

		if( $expected === false ){
			$this->fail( 'Failed to throw a ChangeOpException' );
		}

		$entityClaims = new Claims( $entity->getClaims() );
		$entityClaimHashSet = array_flip( $entityClaims->getHashes() );
		$i = 0;

		foreach( $expected as $expectedClaim ){
			$guid = $expectedClaim->getGuid();
			$hash = $expectedClaim->getHash();

			if ( $guid !== null ) {
				$this->assertEquals( $i++, $entityClaims->indexOf( $expectedClaim ) );
			}

			$this->assertArrayHasKey( $hash, $entityClaimHashSet );
		}

		$this->assertEquals( count( $expected ), $entityClaims->count() );
	}

	/**
	 * @param integer $itemId
	 * @param $snak
	 * @return Item
	 */
	protected function provideNewItemWithClaim( $itemId, $snak ) {
		$entity = Item::newEmpty();
		$entity->setId( ItemId::newFromNumber( $itemId ) );

		$claim = $entity->newClaim( $snak );
		$guidGenerator = new ClaimGuidGenerator( $entity->getId() );
		$claim->setGuid( $guidGenerator->newGuid() );

		$claims = new Claims();
		$claims->addClaim( $claim );
		$entity->setClaims( $claims );

		return $entity;
	}

}
