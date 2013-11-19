<?php

namespace Wikibase\Repo\Specials;

use HttpError;
use Wikibase\Lib\Specials\SpecialWikibasePage;
use Wikibase\LinkedData\EntityDataRequestHandler;
use Wikibase\LinkedData\EntityDataSerializationService;
use Wikibase\LinkedData\EntityDataUriManager;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Settings;

/**
 * Special page to act as a data endpoint for the linked data web.
 * The web server should generally be configured to make this accessible via a canonical URL/URI,
 * such as <http://my.domain.org/entity/Q12345>.
 *
 * Note that this is implemented as a special page and not a per-page action, so there is no need
 * for the web server to map ID prefixes to wiki namespaces.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Thomas Pellissier Tanon
 * @author Anja Jentzsch < anja.jentzsch@wikimedia.de >
 */
class SpecialEntityData extends SpecialWikibasePage {

	/**
	 * @var EntityDataRequestHandler
	 */
	protected $requestHandler;

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 */
	public function __construct() {
		parent::__construct( 'EntityData' );
	}

	/**
	 * Initialize members from global context.
	 * This is poor man's inverse dependency injection.
	 */
	protected function initDependencies() {
		global $wgUseSquid, $wgApiFrameOptions;

		// Initialize serialization service.
		// TODO: use reverse DI facility (global registry/factory)
		$repo = WikibaseRepo::getDefaultInstance();

		$entityContentFactory = $repo->getEntityContentFactory();
		$entityIdParser = $repo->getEntityIdParser();
		$entityIdFormatter = $repo->getIdFormatter();

		$serializationService = new EntityDataSerializationService(
			$repo->getRdfBaseURI(),
			$this->getTitle()->getCanonicalURL() . '/',
			\Wikibase\StoreFactory::getStore()->getEntityLookup(),
			$repo->getDataTypeFactory(),
			$entityIdFormatter
		);

		$maxAge = Settings::get( 'dataSquidMaxage' );
		$formats = Settings::get( 'entityDataFormats' );
		$serializationService->setFormatWhiteList( $formats );

		$defaultFormat = empty( $formats ) ? 'html' : $formats[0];

		// build a mapping of formats to file extensions and include HTML
		$supportedExtensions = array();
		$supportedExtensions['html'] = 'html';
		foreach ( $serializationService->getSupportedFormats() as $format ) {
			$ext = $serializationService->getExtension( $format );

			if ( $ext !== null ) {
				$supportedExtensions[$format] = $ext;
			}
		}

		$uriManager = new EntityDataUriManager(
			$this->getTitle(),
			$supportedExtensions,
			$entityIdFormatter,
			$entityContentFactory
		);
		
		$this->requestHandler = new EntityDataRequestHandler(
			$uriManager,
			$entityContentFactory,
			$entityIdParser,
			$entityIdFormatter,
			$serializationService,
			$defaultFormat,
			$maxAge,
			$wgUseSquid,
			$wgApiFrameOptions
		);
	}

	/**
	 * Main method.
	 *
	 * @since 0.4
	 *
	 * @param string|null $subPage
	 *
	 * @throws HttpError
	 * @return bool
	 */
	public function execute( $subPage ) {
		$this->initDependencies();

		// If there is no ID, show an HTML form
		// TODO: Don't do this if HTML is not acceptable according to HTTP headers.
		if ( !$this->requestHandler->canHandleRequest( $subPage, $this->getRequest() ) ) {
			$this->showForm();
			return true;
		}

		$this->requestHandler->handleRequest( $subPage, $this->getRequest(), $this->getOutput() );

		return true;
	}

	/**
	 * Shows an informative page to the user; Called when there is no entity to output.
	 */
	public function showForm() {
		//TODO: show input form with selector for format and field for ID. Add some explanation,
		//      point to meta-info like schema and license, and generally be a helpful data endpoint.
		$this->getOutput()->showErrorPage( 'wikibase-entitydata-title', 'wikibase-entitydata-text' );
	}
}
