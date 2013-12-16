<?php

namespace Wikibase\Test;

use Revision;
use ValueFormatters\FormatterOptions;
use Wikibase\Entity;
use Wikibase\Item;
use Wikibase\LinkedData\EntityDataSerializationService;

/**
 * @covers \Wikibase\LinkedData\EntityDataSerializationService
 *
 * @since 0.4
 *
 * @group Wikibase
 * @group WikibaseEntityData
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityDataSerializationServiceTest extends \PHPUnit_Framework_TestCase {

	const URI_BASE = 'http://acme.test/';
	const URI_DATA = 'http://data.acme.test/';

	protected function newService() {
		$entityLookup = new MockRepository();

		$service = new EntityDataSerializationService(
			self::URI_BASE,
			self::URI_DATA,
			$entityLookup
		);

		$service->setFormatWhiteList(
			array(
				// using the API
				'json', // default
				'php',
				'xml',

				// using easyRdf
				'rdfxml',
				'n3',
				'turtle',
				'ntriples',
			)
		);

		return $service;
	}


	public static function provideGetSerializedData() {
		$entity = Item::newEmpty();
		$entity->setId( 23 );
		$entity->setLabel( 'en', "ACME" );

		$revisions = new Revision( array(
			'id' => 123,
			'page' => 23,
			'user_text' => 'TestUser',
			'user' => 13,
			'timestamp' => '20130505010101',
			'content_model' => CONTENT_MODEL_WIKIBASE_ITEM,
			'comment' => 'just testing',
		) );

		//TODO: set up...

		$cases = array();

		$cases[] = array( // #0:
			'json',       // format
			$entity,      // entity
			null,         // revision
			'!^\{.*ACME!', // output regex
			'application/json', // mime type
		);

		return $cases;
	}

	/**
	 * @dataProvider provideGetSerializedData
	 *
	 */
	public function testGetSerializedData(
		$format,
		Entity $entity,
		Revision $rev = null,
		$expectedDataRegex,
		$expectedMimeType
	) {
		$service = $this->newService();
		list( $data, $mimeType ) = $service->getSerializedData( $format, $entity, $rev );

		$this->assertEquals( $expectedMimeType, $mimeType );
		$this->assertRegExp( $expectedDataRegex, $data, "outpout" );
	}

	protected static $apiMimeTypes = array(
		'application/vnd.php.serialized',
		'application/json',
		'text/xml'
	);

	protected static $apiExtensions = array(
		'php',
		'json',
		'xml'
	);

	protected static $apiFormats = array(
		'php',
		'json',
		'xml'
	);

	protected static $rdfMimeTypes = array(
		'application/rdf+xml',
		'text/n3',
		'text/rdf+n3',
		'text/turtle',
		'application/turtle',
		'application/ntriples',
	);

	protected static $rdfExtensions = array(
		'rdf',
		'n3',
		'ttl',
		'nt'
	);

	protected static $rdfFormats = array(
		'rdfxml',
		'n3',
		'turtle',
		'ntriples'
	);

	protected static $badMimeTypes = array(
		'text/html',
		'text/text',
		// 'text/plain', // ntriples presents as text/plain!
	);

	protected static $badExtensions = array(
		'html',
		'text',
		'txt',
	);

	protected static $badFormats = array(
		'html',
		'text',
	);

	public function testGetSupportedMimeTypes() {
		$service = $this->newService();

		$types = $service->getSupportedMimeTypes();

		foreach ( self::$apiMimeTypes as $type ) {
			$this->assertTrue( in_array( $type, $types), $type );
		}

		if ( $service->isRdfSupported() ) {
			foreach ( self::$rdfMimeTypes as $type ) {
				$this->assertTrue( in_array( $type, $types), $type );
			}
		}

		foreach ( self::$badMimeTypes as $type ) {
			$this->assertFalse( in_array( $type, $types), $type );
		}
	}

	public function testGetSupportedExtensions() {
		$service = $this->newService();

		$types = $service->getSupportedExtensions();

		foreach ( self::$apiExtensions as $type ) {
			$this->assertTrue( in_array( $type, $types), $type );
		}

		if ( $service->isRdfSupported() ) {
			foreach ( self::$rdfExtensions as $type ) {
				$this->assertTrue( in_array( $type, $types), $type );
			}
		}

		foreach ( self::$badExtensions as $type ) {
			$this->assertFalse( in_array( $type, $types), $type );
		}
	}

	public function testGetSupportedFormats() {
		$service = $this->newService();

		$types = $service->getSupportedFormats();

		foreach ( self::$apiFormats as $type ) {
			$this->assertTrue( in_array( $type, $types), $type );
		}

		if ( $service->isRdfSupported() ) {
			foreach ( self::$rdfFormats as $type ) {
				$this->assertTrue( in_array( $type, $types), $type );
			}
		}

		foreach ( self::$badFormats as $type ) {
			$this->assertFalse( in_array( $type, $types), $type );
		}
	}

	public function testGetFormatName() {
		$service = $this->newService();

		$types = $service->getSupportedMimeTypes();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}

		$types = $service->getSupportedExtensions();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}

		$types = $service->getSupportedFormats();

		foreach ( $types as $type ) {
			$format = $service->getFormatName( $type );
			$this->assertNotNull( $format, $type );
		}
	}

	public function testGetExtension() {
		$service = $this->newService();

		$extensions = $service->getSupportedExtensions();
		foreach ( $extensions as $expected ) {
			$format = $service->getFormatName( $expected );
			$actual = $service->getExtension( $format );

			$this->assertInternalType( 'string', $actual, $expected );
		}

		foreach ( self::$badFormats as $format ) {
			$actual = $service->getExtension( $format );

			$this->assertNull( $actual, $format );
		}
	}

	public function testGetMimeType() {
		$service = $this->newService();

		$extensions = $service->getSupportedMimeTypes();
		foreach ( $extensions as $expected ) {
			$format = $service->getFormatName( $expected );
			$actual = $service->getMimeType( $format );

			$this->assertInternalType( 'string', $actual, $expected );
		}

		foreach ( self::$badFormats as $format ) {
			$actual = $service->getMimeType( $format );

			$this->assertNull( $actual, $format );
		}
	}
}
