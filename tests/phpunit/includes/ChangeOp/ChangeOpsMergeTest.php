<?php

namespace Wikibase\Test;

use Wikibase\ChangeOp\ChangeOpsMerge;
use Wikibase\Claims;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Internal\ObjectComparer;
use Wikibase\DataModel\SimpleSiteLink;
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

	public function testCanConstruct(){
		$from = $this->getItemContent( 'Q111' );
		$to = $this->getItemContent( 'Q222' );
		$changeOps = new ChangeOpsMerge( $from, $to );
		$this->assertInstanceOf( '\Wikibase\ChangeOp\ChangeOpsMerge', $changeOps );
	}

	public function getItemContent( $id, $data = array() ){
		$item = new Item( $data );
		$item->setId( new ItemId( $id ) );
		$itemContent = new ItemContent( $item );
		return $itemContent;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testCanApply( $fromData, $toData, $expectedFromData, $expectedToData ){
		$from = $this->getItemContent( 'Q111', $fromData );
		$to = $this->getItemContent( 'Q222', $toData );
		$changeOps = new ChangeOpsMerge( $from, $to );

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

	public static function provideData(){
		return array(
			//check all elements move individually
			array(
				array( 'label' => array( 'en' => 'foo' ) ),
				array(),
				array(),
				array( 'label' => array( 'en' => 'foo' ) ),
			),
			array(
				array( 'description' => array( 'en' => 'foo' ) ),
				array(),
				array(),
				array( 'description' => array( 'en' => 'foo' ) ),
			),
			array(
				array( 'aliases' => array( 'en' => array( 'foo', 'bar' ) ) ),
				array(),
				array(),
				array( 'aliases' => array( 'en' =>  array( 'foo', 'bar' ) ) ),
			),
			array(
				array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
				array(),
				array(),
				array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			),
			array(
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
			),
			array(
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
			),
			array(
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
			),
		);
	}

}