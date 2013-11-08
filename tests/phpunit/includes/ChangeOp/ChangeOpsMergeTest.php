<?php

namespace Wikibase\Test;

use Wikibase\ChangeOp\ChangeOpsMerge;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Internal\ObjectComparer;
use Wikibase\Item;
use Wikibase\ItemContent;

/**
 * @covers Wikibase\ChangeOp\ChangeOpsMerge
 *
 * @since 0.5
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpsMergeTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideValidConstruction
	 */
	public function testCanConstruct( $from, $to, $ignoreConflicts ) {
		$changeOps = new ChangeOpsMerge( $from, $to, $ignoreConflicts );
		$this->assertInstanceOf( '\Wikibase\ChangeOp\ChangeOpsMerge', $changeOps );
	}

	public static function provideValidConstruction(){
		$from = self::getItemContent( 'Q111' );
		$to = self::getItemContent( 'Q222' );
		return array(
			array( $from, $to, array() ),
			array( $from, $to, array( 'label' ) ),
			array( $from, $to, array( 'description' ) ),
			array( $from, $to, array( 'description', 'label' ) ),
		);
	}

	/**
	 * @dataProvider provideInvalidConstruction
	 */
	public function testInvalidIgnoreConflicts( $from, $to, $ignoreConflicts ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		new ChangeOpsMerge( $from, $to, $ignoreConflicts );
	}

	public static function provideInvalidConstruction(){
		$from = self::getItemContent( 'Q111' );
		$to = self::getItemContent( 'Q222' );
		return array(
			array( $from, $to, 'foo' ),
			array( $from, $to, array( 'foo' ) ),
			array( $from, $to, array( 'label', 'foo' ) ),
			array( $from, $to, null ),
		);
	}

	public static function getItemContent( $id, $data = array() ) {
		$item = new Item( $data );
		$item->setId( new ItemId( $id ) );
		$itemContent = new ItemContent( $item );
		return $itemContent;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testCanApply( $fromData, $toData, $expectedFromData, $expectedToData, $ignoreConflicts = array() ) {
		$from = self::getItemContent( 'Q111', $fromData );
		$to = self::getItemContent( 'Q222', $toData );
		$changeOps = new ChangeOpsMerge( $from, $to, $ignoreConflicts );

		$this->assertTrue( $from->getEntity()->equals( new Item( $fromData ) ), 'FromItem was not filled correctly' );
		$this->assertTrue( $to->getEntity()->equals( new Item( $toData ) ), 'ToItem was not filled correctly' );

		$changeOps->apply();


		$fromData = $from->getItem()->toArray();
		$toData = $to->getItem()->toArray();

		//Cycle through the old claims and set the guids to null (we no longer know what they should be)
		$fromClaims = array();
		foreach( $fromData['claims'] as $claim ) {
			unset( $claim['g'] );
			$fromClaims[] = $claim;
		}

		$toClaims = array();
		foreach( $toData['claims'] as $claim ) {
			unset( $claim['g'] );
			$toClaims[] = $claim;
		}

		$fromData['claims'] = $fromClaims;
		$toData['claims'] = $toClaims;

		$fromData = array_intersect_key( $fromData, $expectedFromData );
		$toData = array_intersect_key( $toData, $expectedToData );

		$comparer = new ObjectComparer();
		$this->assertTrue( $comparer->dataEquals( $expectedFromData, $fromData, array( 'entity' ) ) );
		$this->assertTrue( $comparer->dataEquals( $expectedToData, $toData, array( 'entity' ) ) );
	}

	/**
	 * @return array 1=>fromData 2=>toData 3=>expectedFromData 4=>expectedToData
	 */
	public static function provideData() {
		$testCases = array();
		$testCases['labelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array(),
			array(),
			array( 'label' => array( 'en' => 'foo' ) ),
		);
		$testCases['identicalLabelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'foo' ) ),
			array(),
			array( 'label' => array( 'en' => 'foo' ) ),
		);
		$testCases['ignoreConflictLabelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'bar' ) ),
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'bar' ) ),
			array( 'label' )
		);
		$testCases['descriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array(),
			array(),
			array( 'description' => array( 'en' => 'foo' ) ),
		);
		$testCases['identicalDescriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'foo' ) ),
			array(),
			array( 'description' => array( 'en' => 'foo' ) ),
		);
		$testCases['ignoreConflictDescriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'bar' ) ),
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'bar' ) ),
			array( 'description' )
		);
		$testCases['aliasMerge'] = array(
			array( 'aliases' => array( 'en' => array( 'foo', 'bar' ) ) ),
			array(),
			array(),
			array( 'aliases' => array( 'en' =>  array( 'foo', 'bar' ) ) ),
		);
		$testCases['duplicateAliasMerge'] = array(
			array( 'aliases' => array( 'en' => array( 'foo', 'bar' ) ) ),
			array( 'aliases' => array( 'en' => array( 'foo', 'bar', 'baz' ) ) ),
			array(),
			array( 'aliases' => array( 'en' =>  array( 'foo', 'bar', 'baz' ) ) ),
		);
		$testCases['linkMerge'] = array(
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array(),
			array(),
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
		);
		$testCases['claimMerge'] = array(
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( ),
					'g' => 'Q111$D8404CDA-25E4-4334-AF13-A390BCD9C556' )
			),
			),
			array(),
			array(),
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( ) )
			),
			),
		);
		$testCases['claimWithQualifierMerge'] = array(
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( array(  'novalue', 56  ) ),
					'g' => 'Q111$D8404CDA-25E4-4334-AF13-A3290BCD9C0F' )
			),
			),
			array(),
			array(),
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( array(  'novalue', 56  ) ) )
			),
			),
		);
		$testCases['itemMerge'] = array(
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo' ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc'  ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ),
						'g' => 'Q111$D8404CDA-25E4-4334-AF88-A3290BCD9C0F' )
				),
			),
			array(),
			array(),
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo'  ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc' ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ) )
				),
			),
		);
		$testCases['ignoreConflictItemMerge'] = array(
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo' ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc'  ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ),
						'g' => 'Q111$D8404CDA-25E4-4334-AF88-A3290BCD9C0F' )
				),
			),
			array(
				'label' => array( 'en' => 'toLabel' ),
				'description' => array( 'pl' => 'toLabel' )
			),
			array(
				'label' => array( 'en' => 'foo' ),
				'description' => array( 'pl' => 'pldesc' )
			),
			array(
				'label' => array( 'en' => 'toLabel', 'pt' => 'ptfoo'  ),
				'description' => array( 'en' => 'foo', 'pl' => 'toLabel' ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ) )
				),
			),
			array( 'label', 'description' )
		);
		return $testCases;
	}

}