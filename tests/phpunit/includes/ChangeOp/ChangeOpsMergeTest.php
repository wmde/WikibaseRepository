<?php

namespace Wikibase\Test;

use Wikibase\ChangeOp\ChangeOpFactory;
use Wikibase\ChangeOp\ChangeOpsMerge;
use Wikibase\DataModel\Claim\ClaimGuidParser;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Internal\ObjectComparer;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Lib\ClaimGuidValidator;

/**
 * @covers Wikibase\ChangeOp\ChangeOpsMerge
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
	 * @var ClaimTestMockProvider
	 */
	protected $mockProvider;

	/**
	 * @param string|null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->mockProvider = new ClaimTestMockProvider( $this );
	}

	private function getMockLabelDescriptionDuplicateDetector( $callTimes, $returnValue = array() ) {
		$mock = $this->getMockBuilder( '\Wikibase\LabelDescriptionDuplicateDetector' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->exactly( $callTimes ) )
			->method( 'getConflictingTerms' )
			->will( $this->returnValue( $returnValue ) );
		return $mock;
	}

	private function getMockSitelinkCache( $callTimes, $returnValue = array() ) {
		$mock = $this->getMock( '\Wikibase\SiteLinkCache' );
		$mock->expects( $this->exactly( $callTimes ) )
			->method( 'getConflictsForItem' )
			->will( $this->returnValue( $returnValue ) );
		return $mock;
	}

	protected function makeChangeOpsMerge(
		Item $fromItem,
		Item $toItem,
		$ignoreConflicts,
		$termCalls, $termConflicts,
		$linkCalls, $linkConflicts
	) {
		$duplicateDetector = $this->getMockLabelDescriptionDuplicateDetector( $termCalls, $termConflicts );
		$linkCache = $this->getMockSitelinkCache( $linkCalls, $linkConflicts );

		$idParser = new BasicEntityIdParser();
		$changeOpFactory = new ChangeOpFactory(
			$duplicateDetector,
			$linkCache,
			new ClaimGuidGenerator(),
			new ClaimGuidValidator( $idParser ),
			new ClaimGuidParser( $idParser ),
			$this->mockProvider->getMockSnakValidator()
		);

		return new ChangeOpsMerge(
			$fromItem,
			$toItem,
			$ignoreConflicts,
			$duplicateDetector,
			$linkCache,
			$changeOpFactory
		);
	}

	/**
	 * @dataProvider provideValidConstruction
	 */
	public function testCanConstruct( $from, $to, $ignoreConflicts ) {
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			$ignoreConflicts,
			0, null,
			0, null
		);
		$this->assertInstanceOf( '\Wikibase\ChangeOp\ChangeOpsMerge', $changeOps );
	}

	public static function provideValidConstruction() {
		$from = self::getItem( 'Q111' );
		$to = self::getItem( 'Q222' );
		return array(
			array( $from, $to, array() ),
			array( $from, $to, array( 'label' ) ),
			array( $from, $to, array( 'description' ) ),
			array( $from, $to, array( 'description', 'label' ) ),
			array( $from, $to, array( 'description', 'label', 'sitelink' ) ),
		);
	}

	/**
	 * @dataProvider provideInvalidConstruction
	 */
	public function testInvalidIgnoreConflicts( $from, $to, $ignoreConflicts ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		$this->makeChangeOpsMerge(
			$from,
			$to,
			$ignoreConflicts,
			0, null,
			0, null
		);
	}

	public static function provideInvalidConstruction() {
		$from = self::getItem( 'Q111' );
		$to = self::getItem( 'Q222' );
		return array(
			array( $from, $to, 'foo' ),
			array( $from, $to, array( 'foo' ) ),
			array( $from, $to, array( 'label', 'foo' ) ),
			array( $from, $to, null ),
		);
	}

	/**
	 * @param string $id
	 * @param array $data
	 *
	 * @return Item
	 */
	public static function getItem( $id, $data = array() ) {
		$item = new Item( $data );
		$item->setId( new ItemId( $id ) );
		return $item;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testCanApply( $fromData, $toData, $expectedFromData, $expectedToData, $ignoreConflicts = array() ) {
		$from = self::getItem( 'Q111', $fromData );
		$to = self::getItem( 'Q222', $toData );
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			$ignoreConflicts,
			1, array(),
			1, array()
		);

		$this->assertTrue( $from->equals( new Item( $fromData ) ), 'FromItem was not filled correctly' );
		$this->assertTrue( $to->equals( new Item( $toData ) ), 'ToItem was not filled correctly' );

		$changeOps->apply();


		$fromData = $from->toArray();
		$toData = $to->toArray();

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
		$testCases['ignoreConflictLinkMerge'] = array(
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'bar', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'bar', 'badges' => array() ) ) ),
			array( 'sitelink' ),
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
				'links' => array(
					'dewiki' => array( 'name' => 'foo', 'badges' => array() ),
					'plwiki' => array( 'name' => 'bar', 'badges' => array() ),
				),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ),
						'g' => 'Q111$D8404CDA-25E4-4334-AF88-A3290BCD9C0F' )
				),
			),
			array(
				'label' => array( 'en' => 'toLabel' ),
				'description' => array( 'pl' => 'toLabel' ),
				'links' => array( 'plwiki' => array( 'name' => 'toLink', 'badges' => array() ) ),
			),
			array(
				'label' => array( 'en' => 'foo' ),
				'description' => array( 'pl' => 'pldesc' ),
				'links' => array( 'plwiki' => array( 'name' => 'bar', 'badges' => array() ) ),
			),
			array(
				'label' => array( 'en' => 'toLabel', 'pt' => 'ptfoo'  ),
				'description' => array( 'en' => 'foo', 'pl' => 'toLabel' ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array(
					'dewiki' => array( 'name' => 'foo', 'badges' => array() ),
					'plwiki' => array( 'name' => 'toLink', 'badges' => array() ),
				),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ) )
				),
			),
			array( 'label', 'description', 'sitelink' )
		);
		return $testCases;
	}

	private function getMockTerm( $entityId, $language, $type, $text ) {
		$mock = $this->getMock( '\Wikibase\Term' );
		$mock->expects( $this->once() )
			->method( 'getEntityId' )
			->will( $this->returnValue( $entityId ) );
		$mock->expects( $this->any() )
			->method( 'getLanguage' )
			->will( $this->returnValue( $language ) );
		$mock->expects( $this->any() )
			->method( 'getType' )
			->will( $this->returnValue( $type ) );
		$mock->expects( $this->any() )
			->method( 'getText' )
			->will( $this->returnValue( $text ) );
		return $mock;
	}

	public function testExceptionThrownWhenLabelDescriptionDuplicatesDetected() {
		$conflicts = array( $this->getMockTerm( new ItemId( 'Q999' ), 'imalang', 'imatype', 'foog text' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			array(),
			1, $conflicts,
			1, array()
		);

		$this->setExpectedException(
			'\Wikibase\ChangeOp\ChangeOpException',
			'Item being merged to has conflicting terms: (Q999 => imalang => imatype => foog text)'
		);
		$changeOps->apply();
	}

	public function testExceptionNotThrownWhenLabelDescriptionDuplicatesDetectedOnFromItem() {
		$conflicts = array( $this->getMockTerm( new ItemId( 'Q111' ), 'imalang', 'imatype', 'foog text' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			array(),
			1, $conflicts,
			1, array()
		);

		$changeOps->apply();
		$this->assertTrue( true ); // no exception thrown
	}

	public function testExceptionThrownWhenSitelinkDuplicatesDetected() {
		$conflicts = array( array( 'itemId' => 8888, 'siteId' => 'eewiki', 'sitePage' => 'imapage' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			array(),
			1, array(),
			1, $conflicts
		);

		$this->setExpectedException(
			'\Wikibase\ChangeOp\ChangeOpException',
			'Item being merged to has conflicting terms: (Q8888 => eewiki => imapage)'
		);
		$changeOps->apply();
	}

	public function testExceptionNotThrownWhenSitelinkDuplicatesDetectedOnFromItem() {
		$conflicts = array( array( 'itemId' => 111, 'siteId' => 'eewiki', 'sitePage' => 'imapage' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMerge(
			$from,
			$to,
			array(),
			1, array(),
			1, $conflicts
		);

		$changeOps->apply();
		$this->assertTrue( true ); // no exception thrown
	}

}
