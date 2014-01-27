<?php

namespace Wikibase\Test;

use DerivativeContext;
use FauxRequest;
use FauxResponse;
use HttpError;
use OutputPage;
use RequestContext;
use Title;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\LinkedData\EntityDataSerializationService;
use Wikibase\LinkedData\EntityDataRequestHandler;
use Wikibase\LinkedData\EntityDataUriManager;

/**
 * @covers Wikibase\LinkedData\EntityDataRequestHandler
 *
 * @group Database
 *
 * @group Wikibase
 * @group WikibaseEntityData
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityDataRequestHandlerTest extends \MediaWikiTestCase {

	/**
	 * @var Title
	 */
	protected $interfaceTitle;

	/**
	 * @var int
	 */
	private $obLevel;

	public function setUp() {
		parent::setUp();

		$this->interfaceTitle = Title::newFromText( "Special:EntityDataRequestHandlerTest" );

		$this->obLevel = ob_get_level();
	}

	public function tearDown() {
		$obLevel = ob_get_level();

		while ( ob_get_level() > $this->obLevel ) {
			ob_end_clean();
		}

		if ( $obLevel !== $this->obLevel ) {
			$this->fail( "Test changed output buffer level: was {$this->obLevel} before test, but $obLevel after test.");
		}

		parent::tearDown();
	}

	/**
	 * @return EntityDataRequestHandler
	 */
	protected function newHandler() {
		$entityLookup = EntityDataTestProvider::getMockRepo();

		$idParser = new BasicEntityIdParser(); // we only test for items and properties here.

		$dataTypeLookup = $this->getMock( 'Wikibase\Lib\PropertyDataTypeLookup' );
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( 'string' ) );

		$titleLookup = $this->getMock( 'Wikibase\EntityTitleLookup' );
		$titleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				return Title::newFromText( $id->getEntityType() . ':' . $id->getSerialization() );
			} ) );

		$serializerOptions = new SerializationOptions();
		$serializerFactory = new SerializerFactory( $serializerOptions, $dataTypeLookup );

		$service = new EntityDataSerializationService(
			EntityDataSerializationServiceTest::URI_BASE,
			EntityDataSerializationServiceTest::URI_DATA,
			$entityLookup,
			$titleLookup,
			$serializerFactory
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

		$extensions = array(
			// using the API
			'json' => 'json', // default
			'php' => 'php',
			'xml' => 'xml',

			// using easyRdf
			'rdfxml' => 'rdf',
			'n3' => 'n3',
			'turtle' => 'ttl',
			'ntriples' => 'n3',
		);

		$uriManager = new EntityDataUriManager(
			$this->interfaceTitle,
			$extensions,
			$titleLookup
		);

		$handler = new EntityDataRequestHandler(
			$uriManager,
			$titleLookup,
			$idParser,
			$entityLookup,
			$service,
			'json',
			1800,
			false,
			null
		);

		return $handler;
	}

	/**
	 * @param $params
	 * @param $headers
	 *
	 * @return OutputPage
	 */
	protected function makeOutputPage( $params, $headers ) {
		// construct request
		$request = new FauxRequest( $params );
		$request->response()->header( 'Status: 200 OK', true, 200 ); // init/reset

		foreach ( $headers as $name => $value ) {
			$request->setHeader( strtoupper( $name ), $value );
		}

		// construct Context and OutputPage
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );

		$output = new OutputPage( $context );
		$output->setTitle( $this->interfaceTitle );
		$context->setOutput( $output );

		return $output;
	}

	public function handleRequestProvider() {
		return EntityDataTestProvider::provideHandleRequest();
	}

	/**
	 * @dataProvider handleRequestProvider
	 *
	 * @param string $subpage The subpage to request (or '')
	 * @param array  $params  Request parameters
	 * @param array  $headers  Request headers
	 * @param string $expRegExp   Regex to match the output against.
	 * @param int    $expCode     Expected HTTP status code
	 * @param array  $expHeaders  Expected HTTP response headers
	 */
	public function testHandleRequest( $subpage, $params, $headers, $expRegExp, $expCode = 200, $expHeaders = array() ) {
		$output = $this->makeOutputPage( $params, $headers );
		$request = $output->getRequest();

		/* @var FauxResponse $response */
		$response = $request->response();

		// construct handler
		$handler = $this->newHandler();

		try {
			ob_start();
			$handler->handleRequest( $subpage, $request, $output );

			if ( $output->getRedirect() !== '' ) {
				// hack to apply redirect to web response
				$output->output();
			}

			$text = ob_get_contents();
			ob_end_clean();

			$this->assertEquals( $expCode, $response->getStatusCode(), "status code" );
			$this->assertRegExp( $expRegExp, $text, "output" );

			foreach ( $expHeaders as $name => $exp ) {
				$value = $response->getheader( $name );
				$this->assertNotNull( $value, "header: $name" );
				$this->assertInternalType( 'string', $value, "header: $name" );
				$this->assertRegExp( $exp, $value, "header: $name" );
			}
		} catch ( HttpError $e ) {
			ob_end_clean();
			$this->assertEquals( $expCode, $e->getStatusCode(), "status code" );
			$this->assertRegExp( $expRegExp, $e->getHTML(), "error output" );
		}
	}

	//TODO: test canHandleRequest
	//TODO: test httpContentNegotiation
	//TODO: test getCanonicalFormat
	//TODO: test ALL the things!
}
