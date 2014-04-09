<?php

namespace Wikibase\Test;

use DataValues\DataValue;
use DataValues\NumberValue;
use DataValues\StringValue;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpMainSnak;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\ItemContent;
use InvalidArgumentException;

/**
 * @covers Wikibase\ChangeOp\ChangeOpMainSnak
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 * @group ChangeOpMainSnak
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpMainSnakTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var ClaimTestMockProvider
	 */
	protected $mockProvider;

	/**
	 * @param null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->mockProvider = new ClaimTestMockProvider( $this );
	}

	public function invalidArgumentProvider() {
		$item = ItemContent::newFromArray( array( 'entity' => 'q42' ) )->getEntity();
		$validClaimGuid = $this->mockProvider->getGuidGenerator()->newGuid( $item->getId() );
		$validSnak = new PropertyValueSnak( 7201010, new StringValue( 'o_O' ) );

		$args = array();
		$args[] = array( 123, $validSnak );
		$args[] = array( 123, null );
		$args[] = array( $validClaimGuid, 'notASnak' );
		$args[] = array( '', 'notASnak' );
		$args[] = array( '', null );

		return $args;
	}

	/**
	 * @dataProvider invalidArgumentProvider
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidConstruct( $claimGuid, $snak ) {
		new ChangeOpMainSnak(
			$claimGuid,
			$snak,
			$this->mockProvider->getGuidGenerator(),
			$this->mockProvider->getMockSnakValidator()
		);
	}

	/**
	 * @param string $claimGuid
	 * @param Snak $snak
	 *
	 * @return ChangeOpMainSnak
	 */
	protected function newChangeOpMainSnak( $claimGuid, Snak $snak ) {
		return new ChangeOpMainSnak(
			$claimGuid,
			$snak,
			$this->mockProvider->getGuidGenerator(),
			$this->mockProvider->getMockSnakValidator()
		);
	}

	public function provideChangeOps() {
		$snak =  $this->makeSnak( 'P5', 'test' );
		$args = array();

		// add a new claim
		$item = $this->makeNewItemWithClaim( 'Q123', $snak );
		$newSnak =  $this->makeSnak( 'P8', 'newSnak' );
		$claimGuid = '';
		$changeOp = $this->newChangeOpMainSnak( $claimGuid, $newSnak );
		$expected = $newSnak->getDataValue();
		$args['add new claim'] = array ( $item, $changeOp, $expected );

		// update an existing claim with a new main snak value
		$item = $this->makeNewItemWithClaim( 'Q234', $snak );
		$newSnak =  $this->makeSnak( 'P5', 'changedSnak' );
		$claims = $item->getClaims();
		$claim = reset( $claims );

		$claimGuid = $claim->getGuid();
		$changeOp = $this->newChangeOpMainSnak( $claimGuid, $newSnak );
		$expected = $newSnak->getDataValue();
		$args['update claim by guid'] = array ( $item, $changeOp, $expected );

		return $args;
	}

	/**
	 * @dataProvider provideChangeOps
	 *
	 * @param Entity $item
	 * @param ChangeOpMainSnak $changeOp
	 * @param DataValue|null $expected
	 */
	public function testApply( Entity $item, $changeOp, $expected ) {
		$this->assertTrue( $changeOp->apply( $item ), "Applying the ChangeOp did not return true" );
		$this->assertNotEmpty( $changeOp->getClaimGuid() );
		$claims = new Claims( $item->getClaims() );
		if ( $expected === null ) {
			$this->assertEquals( $expected, $claims->getClaimWithGuid( $changeOp->getClaimGuid() ) );
		} else {
			$this->assertEquals( $expected, $claims->getClaimWithGuid( $changeOp->getClaimGuid() )->getMainSnak()->getDataValue() );
		}
	}

	public function provideInvalidApply() {
		$snak =  $this->makeSnak( 'P11', 'test' );
		$item = $this->makeNewItemWithClaim( 'Q777', $snak );
		$claims = $item->getClaims();
		$claim = reset( $claims );
		$claimGuid = $claim->getGuid();

		// apply change to the wrong item
		$wrongItem = Item::newEmpty();
		$wrongItem->setId( new ItemId( "Q888" ) );
		$newSnak =  $this->makeSnak( 'P12', 'newww' );
		$args['wrong entity'] = array ( $wrongItem, $this->newChangeOpMainSnak( $claimGuid, $newSnak ) );

		// apply change to an unknown claim
		$wrongClaimId = $item->getId()->getPrefixedId() . '$DEADBEEF-DEAD-BEEF-DEAD-BEEFDEADBEEF';
		$args['unknown claim'] = array ( $item, $this->newChangeOpMainSnak( $wrongClaimId, $newSnak ) );

		// update an existing claim with wrong main snak property
		$newSnak =  $this->makeSnak( 'P13', 'changedSnak' );
		$claims = $item->getClaims();
		$claim = reset( $claims );

		$claimGuid = $claim->getGuid();
		$changeOp = $this->newChangeOpMainSnak( $claimGuid, $newSnak );
		$args['wrong main snak property'] = array ( $item, $changeOp );

		// apply invalid main snak
		$badSnak =  $this->makeSnak( 'P12', new NumberValue( 5 ) );
		$args['bad value type'] = array ( $wrongItem, $this->newChangeOpMainSnak( $claimGuid, $badSnak ) );

		// apply invalid main snak
		// NOTE: the mock validator considers "INVALID" to be invalid.
		$badSnak = $this->makeSnak( 'P12', 'INVALID' );
		$args['invalid value'] = array ( $wrongItem, $this->newChangeOpMainSnak( $claimGuid, $badSnak ) );

		return $args;
	}

	/**
	 * @dataProvider provideInvalidApply
	 */
	public function testInvalidApply( Entity $item, ChangeOp $changeOp ) {
		$this->setExpectedException( 'Wikibase\ChangeOp\ChangeOpException' );

		$changeOp->apply( $item );
	}

	protected function makeNewItemWithClaim( $itemId, $snak ) {
		$entity = Item::newFromArray( array( 'entity' => $itemId ) );
		$claim = $entity->newClaim( $snak );
		$claim->setGuid( $this->mockProvider->getGuidGenerator()->newGuid( $entity->getId() ) );
		$claims = new Claims();
		$claims->addClaim( $claim );
		$entity->setClaims( $claims );

		return $entity;
	}

	protected function makeSnak( $propertyId, $value ) {
		if ( is_string( $propertyId ) ) {
			$propertyId = new PropertyId( $propertyId );
		}

		if ( is_string( $value ) ) {
			$value = new StringValue( $value );
		}

		if ( $value === null ) {
			return new PropertyNoValueSnak( $propertyId );
		} else {
			return new PropertyValueSnak( $propertyId, $value );
		}
	}
}
