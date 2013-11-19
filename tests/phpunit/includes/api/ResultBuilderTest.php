<?php

namespace Wikibase\Test\Api;

use ApiResult;
use DataValues\StringValue;
use PHPUnit_Framework_TestCase;
use Wikibase\Api\ResultBuilder;
use Wikibase\Claim;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\PropertyValueSnak;
use Wikibase\Reference;
use Wikibase\SnakList;

/**
 * @covers Wikibase\Api\ResultBuilder
 * @todo mock and inject serializers to avoid massive expected output?
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ResultBuilderTest extends PHPUnit_Framework_TestCase {

	protected function getDefaultResult(){
		$apiMain =  $this->getMockBuilder( 'ApiMain' )->disableOriginalConstructor()->getMockForAbstractClass();
		return new ApiResult( $apiMain );
	}

	protected function getResultBuilder( $result ){
		return new ResultBuilder( $result );
	}

	public function testCanConstruct(){
		$result = $this->getDefaultResult();
		$resultBuilder = $this->getResultBuilder( $result );
		$this->assertInstanceOf( '\Wikibase\Api\ResultBuilder', $resultBuilder );
	}

	/**
	 * @dataProvider provideBadConstructionData
	 */
	public function testBadConstruction( $result ){
		$this->setExpectedException( 'InvalidArgumentException' );
		$this->getResultBuilder( $result );
	}

	public static function provideBadConstructionData() {
		return array(
			array( null ),
			array( 1234 ),
			array( "imastring" ),
			array( array() ),
		);
	}

	/**
	 * @dataProvider provideMarkResultSuccess
	 */
	public function testMarkResultSuccess( $param, $expected ){
		$result = $this->getDefaultResult();
		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->markSuccess( $param );
		$this->assertEquals( array( 'success' => $expected ),  $result->getData() );
	}

	public static function provideMarkResultSuccess() {
		return array( array( true, 1 ), array( 1, 1 ), array( false, 0 ), array( 0, 0 ), array( null, 0 ) );
	}

	/**
	 * @dataProvider provideMarkResultSuccessExceptions
	 */
	public function testMarkResultSuccessExceptions( $param ){
		$this->setExpectedException( 'InvalidArgumentException' );
		$result = $this->getDefaultResult();
		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->markSuccess( $param );
	}

	public static function provideMarkResultSuccessExceptions() {
		return array( array( 3 ), array( -1 ) );
	}

	public function testAddLabels(){
		$result = $this->getDefaultResult();
		$labels = array( 'en' => 'foo', 'de' => 'bar' );
		$path = array( 'entities', 'Q1' );
		$expected = array(
			'entities' => array(
				'Q1' => array(
					'labels' => array(
						'en' => array(
							'language' => 'en',
							'value' => 'foo',
						),
						'de' => array(
							'language' => 'de',
							'value' => 'bar',
						),
					),
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addLabels( $labels, $path );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddDescriptions(){
		$result = $this->getDefaultResult();
		$descriptions = array( 'en' => 'foo', 'de' => 'bar' );
		$path = array( 'entities', 'Q1' );
		$expected = array(
			'entities' => array(
				'Q1' => array(
					'descriptions' => array(
						'en' => array(
							'language' => 'en',
							'value' => 'foo',
						),
						'de' => array(
							'language' => 'de',
							'value' => 'bar',
						),
					),
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addDescriptions( $descriptions, $path );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddAliases(){
		$result = $this->getDefaultResult();
		$aliases = array( 'en' => array( 'boo', 'hoo' ), 'de' => array( 'ham', 'cheese' ) );
		$path = array( 'entities', 'Q1' );
		$expected = array(
			'entities' => array(
				'Q1' => array(
					'aliases' => array(
						'en' => array(
							array(
								'language' => 'en',
								'value' => 'boo',
							),
							array(
								'language' => 'en',
								'value' => 'hoo',
							),
						),
						'de' => array(
							array(
								'language' => 'de',
								'value' => 'ham',
							),
							array(
								'language' => 'de',
								'value' => 'cheese',
							),
						),
					),
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addAliases( $aliases, $path );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddSiteLinks(){
		$result = $this->getDefaultResult();
		$sitelinks = array( new SimpleSiteLink( 'enwiki', 'User:Addshore' ), new SimpleSiteLink( 'dewikivoyage', 'Berlin' ) );
		$path = array( 'entities', 'Q1' );
		$expected = array(
			'entities' => array(
				'Q1' => array(
					'sitelinks' => array(
						'enwiki' => array(
							'site' => 'enwiki',
							'title' => 'User:Addshore',
							'badges' => array(),
						),
						'dewikivoyage' => array(
							'site' => 'dewikivoyage',
							'title' => 'Berlin',
							'badges' => array(),
						),
					),
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addSiteLinks( $sitelinks, $path );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddClaims(){
		$result = $this->getDefaultResult();
		$claim1 = new Claim( new PropertyValueSnak( new PropertyId( 'P12' ), new StringValue( 'stringVal' ) ) );
		$claim1->setGuid( 'fooguidbar' );
		$claims = array( $claim1 );
		$path = array( 'entities', 'Q1' );
		$expected = array(
			'entities' => array(
				'Q1' => array(
					'claims' => array(
						'P12' => array(
							array(
								'id' => 'fooguidbar',
								'mainsnak' => array(
									'snaktype' => 'value',
									'property' => 'P12',
									'datavalue' => array(
										'value' => 'stringVal',
										'type' => 'string',
									),
								),
								'type' => 'claim',
							)
						)
					),
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addClaims( $claims, $path );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddClaim(){
		$result = $this->getDefaultResult();
		$claim = new Claim( new PropertyValueSnak( new PropertyId( 'P12' ), new StringValue( 'stringVal' ) ) );
		$claim->setGuid( 'fooguidbar' );
		$expected = array(
			'claim' => array(
				'id' => 'fooguidbar',
				'mainsnak' => array(
					'snaktype' => 'value',
					'property' => 'P12',
					'datavalue' => array(
						'value' => 'stringVal',
						'type' => 'string',
					),
				),
				'type' => 'claim',
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addClaim( $claim );

		$this->assertEquals( $expected, $result->getData() );
	}

	public function testAddReference(){
		$result = $this->getDefaultResult();
		$reference = new Reference( new SnakList( array( new PropertyValueSnak( new PropertyId( 'P12' ), new StringValue( 'stringVal' ) ) ) ) );
		$hash = $reference->getHash();
		$expected = array(
			'reference' => array(
				'hash' => $hash,
				'snaks' => array(
					'P12' => array(
						array(
							'snaktype' => 'value',
							'property' => 'P12',
							'datavalue' => array(
								'value' => 'stringVal',
								'type' => 'string',
							),
						)
					),
				),
				'snaks-order' => array( 'P12' ),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addReference( $reference );

		$this->assertEquals( $expected, $result->getData() );
	}

	/**
	 * @dataProvider provideMissingEntity
	 */
	public function testAddMissingEntity( $missingEntities, $expected ){
		$result = $this->getDefaultResult();
		$resultBuilder = $this->getResultBuilder( $result );

		foreach( $missingEntities as $missingDetails ){
			$resultBuilder->addMissingEntity( $missingDetails );
		}

		$this->assertEquals( $expected, $result->getData() );
	}

	public static function provideMissingEntity() {
		return array(
			array(
				array(
					array( 'site' => 'enwiki', 'title' => 'Berlin'),
				),
				array(
					'entities' => array(
						'-1' => array(
							'site' => 'enwiki',
							'title' => 'Berlin',
							//@todo fix bug 45509 useless missing flag
							'missing' => '',
						)
					),
				)
			),
			array(
				array(
					array( 'site' => 'enwiki', 'title' => 'Berlin'),
					array( 'site' => 'dewiki', 'title' => 'Foo'),
				),
				array(
					'entities' => array(
						'-1' => array(
							'site' => 'enwiki',
							'title' => 'Berlin',
							//@todo fix bug 45509 useless missing flag
							'missing' => '',
						),
						'-2' => array(
							'site' => 'dewiki',
							'title' => 'Foo',
							//@todo fix bug 45509 useless missing flag
							'missing' => '',
						)
					),
				)
			),
		);
	}

	public function testAddNormalizedTitle(){
		$result = $this->getDefaultResult();
		$from = 'berlin';
		$to = 'Berlin';
		$expected = array(
			'normalized' => array(
				//todo this is JUST SILLY
				'n' => array(
					'from' => 'berlin',
					'to' => 'Berlin'
				),
			),
		);

		$resultBuilder = $this->getResultBuilder( $result );
		$resultBuilder->addNormalizedTitle( $from, $to );

		$this->assertEquals( $expected, $result->getData() );
	}
}