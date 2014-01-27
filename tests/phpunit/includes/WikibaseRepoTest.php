<?php

namespace Wikibase\Tests\Repo;

use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\WikibaseRepo
 *
 * @since 0.4
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseRepoTest
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class WikibaseRepoTest extends \MediaWikiTestCase {

	public function testGetDataTypeFactoryReturnType() {
		$returnValue = $this->getDefaultInstance()->getDataTypeFactory();
		$this->assertInstanceOf( 'DataTypes\DataTypeFactory', $returnValue );
	}

	public function testGetDataValueFactoryReturnType() {
		$returnValue = $this->getDefaultInstance()->getDataValueFactory();
		$this->assertInstanceOf( 'DataValues\DataValueFactory', $returnValue );
	}

	public function testGetEntityContentFactoryReturnType() {
		$returnValue = $this->getDefaultInstance()->getEntityContentFactory();
		$this->assertInstanceOf( 'Wikibase\EntityContentFactory', $returnValue );
	}

	public function testGetEntityTitleLookupReturnType() {
		$returnValue = $this->getDefaultInstance()->getEntityTitleLookup();
		$this->assertInstanceOf( 'Wikibase\EntityTitleLookup', $returnValue );
	}

	public function testGetEntityRevisionLookupReturnType() {
		$returnValue = $this->getDefaultInstance()->getEntityRevisionLookup();
		$this->assertInstanceOf( 'Wikibase\EntityRevisionLookup', $returnValue );
	}

	public function testGetIdFormatterReturnType() {
		$returnValue = $this->getDefaultInstance()->getIdFormatter();
		$this->assertInstanceOf( 'Wikibase\Lib\EntityIdFormatter', $returnValue );
	}

	public function testGetPropertyDataTypeLookupReturnType() {
		$returnValue = $this->getDefaultInstance()->getPropertyDataTypeLookup();
		$this->assertInstanceOf( 'Wikibase\Lib\PropertyDataTypeLookup', $returnValue );
	}

	public function testGetStringNormalizerReturnType() {
		$returnValue = $this->getDefaultInstance()->getStringNormalizer();
		$this->assertInstanceOf( 'Wikibase\StringNormalizer', $returnValue );
	}

	public function testGetEntityLookupReturnType() {
		$returnValue = $this->getDefaultInstance()->getEntityLookup();
		$this->assertInstanceOf( 'Wikibase\EntityLookup', $returnValue );
	}

	public function testGetSnakConstructionServiceReturnType() {
		$returnValue = $this->getDefaultInstance()->getSnakConstructionService();
		$this->assertInstanceOf( 'Wikibase\Lib\SnakConstructionService', $returnValue );
	}

	/**
	 * @dataProvider provideGetRdfBaseURI
	 */
	public function testGetRdfBaseURI( $server, $expected ) {
		$this->setMwGlobals( 'wgServer', $server );

		$returnValue = $this->getDefaultInstance()->getRdfBaseURI();
		$this->assertEquals( $expected, $returnValue );
	}

	public function provideGetRdfBaseURI() {
		return array(
			array ( 'http://acme.test', 'http://acme.test/entity/' ),
			array ( 'https://acme.test', 'https://acme.test/entity/' ),
			array ( '//acme.test', 'http://acme.test/entity/' ),
		);
	}

	public function testGetEntityIdParserReturnType() {
		$returnValue = $this->getDefaultInstance()->getEntityIdParser();
		$this->assertInstanceOf( 'Wikibase\DataModel\Entity\EntityIdParser', $returnValue );
	}

	public function testGetClaimGuidParser() {
		$returnValue = $this->getDefaultInstance()->getClaimGuidParser();
		$this->assertInstanceOf( 'Wikibase\DataModel\Claim\ClaimGuidParser', $returnValue );
	}

	public function testGetLanguageFallbackChainFactory() {
		$returnValue = $this->getDefaultInstance()->getLanguageFallbackChainFactory();
		$this->assertInstanceOf( 'Wikibase\LanguageFallbackChainFactory', $returnValue );
	}

	public function testGetClaimGuidValidator() {
		$returnValue = $this->getDefaultInstance()->getClaimGuidValidator();
		$this->assertInstanceOf( 'Wikibase\Lib\ClaimGuidValidator', $returnValue );
	}

	public function testGetSettingsReturnType() {
		$returnValue = $this->getDefaultInstance()->getSettings();
		$this->assertInstanceOf( 'Wikibase\SettingsArray', $returnValue );
	}

	public function testGetStoreReturnType() {
		$returnValue = $this->getDefaultInstance()->getStore();
		$this->assertInstanceOf( 'Wikibase\Store', $returnValue );
	}

	public function testGetSnakFormatterFactory() {
		$returnValue = $this->getDefaultInstance()->getSnakFormatterFactory();
		$this->assertInstanceOf( 'Wikibase\Lib\OutputFormatSnakFormatterFactory', $returnValue );
	}

	public function testGetValueFormatterFactory() {
		$returnValue = $this->getDefaultInstance()->getValueFormatterFactory();
		$this->assertInstanceOf( 'Wikibase\Lib\OutputFormatValueFormatterFactory', $returnValue );
	}

	public function testGetSummaryFormatter() {
		$returnValue = $this->getDefaultInstance()->getSummaryFormatter();
		$this->assertInstanceOf( 'Wikibase\SummaryFormatter', $returnValue );
	}

	/**
	 * @return WikibaseRepo
	 */
	private function getDefaultInstance() {
		return WikibaseRepo::getDefaultInstance();
	}
}
