<?php

namespace Wikibase;
use Iterator;
use Maintenance;
use ValueFormatters\FormatterOptions;
use Wikibase\Dumpers\JsonDumpGenerator;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Lib\Serializers\EntitySerializationOptions;
use Wikibase\Lib\Serializers\EntitySerializer;
use Wikibase\Lib\Serializers\Serializer;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for generating a JSON dump of entities in the repository.
 *
 * @since 0.5
 *
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class DumpJson extends Maintenance {

	/**
	 * @var EntityLookup
	 */
	public $entityLookup;

	/**
	 * @var Serializer
	 */
	public $entitySerializer;

	/**
	 * @var EntityPerPage
	 */
	public $entityPerPage;

	public function __construct() {
		parent::__construct();

		$this->mDescription = 'Generate a JSON dump from entities in the repository.';

		//TODO: read list of IDs from file
		//TODO: filter by entity type
		//$this->addOption( 'rebuild-all', "Update property info for all properties (per default, only missing entries are created)" );
		//$this->addOption( 'start-row', "The ID of the first row to update (useful for continuing aborted runs)", false, true );
		//$this->addOption( 'batch-size', "Number of rows to update per database transaction (100 per default)", false, true );
	}

	public function initServices() {
		$serializerOptions = new EntitySerializationOptions( new EntityIdFormatter( new FormatterOptions() ) );
		$this->entitySerializer = new EntitySerializer( $serializerOptions );

		//TODO: allow injection for unit tests
		$this->entityPerPage = new EntityPerPageTable();
		$this->entityLookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
	}

	/**
	 * Outputs a message vis the output() method.
	 *
	 * @param $msg
	 */
	public function report( $msg ) {
		$this->output( "$msg\n" );
	}

	/**
	 * Do the actual work. All child classes will need to implement this
	 */
	public function execute() {
		$this->initServices();

		$output = fopen( 'php://stdout', 'wa' ); //TODO: Allow injection of an OutputStream
		$dumper = new JsonDumpGenerator( $output, $this->entityLookup, $this->entitySerializer );

		$idStream = $this->makeIdStream();
		$dumper->generateDump( $idStream );
	}

	/**
	 * @return Iterator a stream of EntityId objects
	 */
	public function makeIdStream() {
		//TODO: provide list/filter of entities
		//TODO: allow ids to be read from a file

		$stream = $this->entityPerPage->getEntities();
		return $stream;
	}
}

$maintClass = 'Wikibase\DumpJson';
require_once( RUN_MAINTENANCE_IF_MAIN );
