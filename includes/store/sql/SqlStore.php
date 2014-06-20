<?php

namespace Wikibase;

use DatabaseBase;
use DatabaseUpdater;
use DBQueryError;
use HashBagOStuff;
use MWException;
use ObjectCache;
use Revision;
use ObservableMessageReporter;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Lib\Store\CachingEntityRevisionLookup;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\SiteLinkCache;
use Wikibase\Lib\Store\SiteLinkTable;
use Wikibase\Lib\Store\WikiPageEntityLookup;
use Wikibase\Repo\Store\DispatchingEntityStoreWatcher;
use Wikibase\Repo\Store\WikiPageEntityStore;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;

/**
 * Implementation of the store interface using an SQL backend via MediaWiki's
 * storage abstraction layer.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SqlStore implements Store {

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup = null;

	/**
	 * @var EntityRevisionLookup
	 */
	private $rawEntityRevisionLookup = null;

	/**
	 * @var EntityStore
	 */
	private $entityStore = null;

	/**
	 * @var DispatchingEntityStoreWatcher
	 */
	private $entityStoreWatcher = null;

	/**
	 * @var EntityInfoBuilder
	 */
	private $entityInfoBuilder = null;

	/**
	 * @var PropertyInfoTable
	 */
	private $propertyInfoTable = null;

	/**
	 * @var ChangesTable
	 */
	private $changesTable = null;

	/**
	 * @var string|bool false for local, or a database id that wfGetLB understands.
	 */
	private $changesDatabase;

	/**
	 * @var TermIndex
	 */
	private $termIndex = null;

	/**
	 * @var string
	 */
	private $cachePrefix;

	/**
	 * @var int
	 */
	private $cacheType;

	/**
	 * @var int
	 */
	private $cacheDuration;

	/**
	 * @var EntityContentDataCodec
	 */
	private $contentCodec;

	/**
	 * @param EntityContentDataCodec $contentCodec
	 */
	public function __construct(
		EntityContentDataCodec $contentCodec
	) {
		$this->contentCodec = $contentCodec;

		//TODO: inject settings
		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$cachePrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$cacheType = $settings->getSetting( 'sharedCacheType' );

		$this->cachePrefix = $cachePrefix;
		$this->cacheDuration = $cacheDuration;
		$this->cacheType = $cacheType;

		$this->changesDatabase = $settings->getSetting( 'changesDatabase' );
	}

	/**
	 * @see Store::getTermIndex
	 *
	 * @since 0.4
	 *
	 * @return TermIndex
	 */
	public function getTermIndex() {
		if ( !$this->termIndex ) {
			$this->termIndex = $this->newTermIndex();
		}

		return $this->termIndex;
	}

	/**
	 * @since 0.1
	 *
	 * @return TermIndex
	 */
	protected function newTermIndex() {
		//TODO: Get $stringNormalizer from WikibaseRepo?
		//      Can't really pass this via the constructor...
		$stringNormalizer = new StringNormalizer();
		return new TermSqlIndex( $stringNormalizer );
	}

	/**
	 * @see Store::clear
	 *
	 * @since 0.1
	 */
	public function clear() {
		$this->newSiteLinkCache()->clear();
		$this->getTermIndex()->clear();
		$this->newEntityPerPage()->clear();
	}

	/**
	 * @see Store::rebuild
	 *
	 * @since 0.1
	 */
	public function rebuild() {
		$dbw = wfGetDB( DB_MASTER );

		// TODO: refactor selection code out (relevant for other stores)

		$pages = $dbw->select(
			array( 'page' ),
			array( 'page_id', 'page_latest' ),
			array( 'page_content_model' => WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getEntityContentModels() ),
			__METHOD__,
			array( 'LIMIT' => 1000 ) // TODO: continuation
		);

		foreach ( $pages as $pageRow ) {
			$page = WikiPage::newFromID( $pageRow->page_id );
			$revision = Revision::newFromId( $pageRow->page_latest );
			try {
				$page->doEditUpdates( $revision, $GLOBALS['wgUser'] );
			} catch ( DBQueryError $e ) {
				wfLogWarning(
					'editUpdateFailed for ' . $page->getId() . ' on revision ' .
					$revision->getId() . ': ' . $e->getMessage()
				);
			}
		}
	}

	/**
	 * Updates the schema of the SQL store to it's latest version.
	 *
	 * @since 0.1
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function doSchemaUpdate( DatabaseUpdater $updater ) {
		$db = $updater->getDB();

		// Update from 0.1.
		if ( !$db->tableExists( 'wb_terms' ) ) {
			$updater->dropTable( 'wb_items_per_site' );
			$updater->dropTable( 'wb_items' );
			$updater->dropTable( 'wb_aliases' );
			$updater->dropTable( 'wb_texts_per_lang' );

			$updater->addExtensionTable(
				'wb_terms',
				$this->getUpdateScriptPath( 'Wikibase', $db->getType() )
			);

			$this->rebuild();
		}

		$this->updateEntityPerPageTable( $updater, $db );
		$this->updateTermsTable( $updater, $db );

		$this->registerPropertyInfoTableUpdates( $updater );
	}

	private function registerPropertyInfoTableUpdates( DatabaseUpdater $updater ) {
		$table = 'wb_property_info';

		if ( !$updater->tableExists( $table ) ) {
			$type = $updater->getDB()->getType();
			$fileBase = __DIR__ . '/../../../../lib/includes/store/sql/' . $table;

			$file = $fileBase . '.' . $type . '.sql';
			if ( !file_exists( $file ) ) {
				$file = $fileBase . '.sql';
			}

			$updater->addExtensionTable( $table, $file );

			// populate the table after creating it
			$updater->addExtensionUpdate( array(
				array( __CLASS__, 'rebuildPropertyInfo' )
			) );
		}
	}

	/**
	 * Wrapper for invoking PropertyInfoTableBuilder from DatabaseUpdater
	 * during a database update.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function rebuildPropertyInfo( DatabaseUpdater $updater ) {
		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			function ( $msg ) use ( $updater ) {
				$updater->output( "..." . $msg . "\n" );
			}
		);

		$table = new PropertyInfoTable( false );
		$contentCodec = WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec();

		$wikiPageEntityLookup = new WikiPageEntityLookup( $contentCodec, false );
		$cachingEntityLookup = new CachingEntityRevisionLookup( $wikiPageEntityLookup, new \HashBagOStuff() );

		$builder = new PropertyInfoTableBuilder( $table, $cachingEntityLookup );
		$builder->setReporter( $reporter );
		$builder->setUseTransactions( false );

		$updater->output( 'Populating ' . $table->getTableName() . "\n" );
		$builder->rebuildPropertyInfo();
	}

	/**
	 * Returns the script directory that contains a file with the given name.
	 *
	 * @param string $fileName with extension
	 *
	 * @throws MWException If the file was not found in any script directory
	 * @return string The directory that contains the file
	 */
	private function getUpdateScriptDir( $fileName ) {
		$dirs = array(
			__DIR__,
			__DIR__ . '/../../../sql'
		);

		foreach ( $dirs as $dir ) {
			if ( file_exists( "$dir/$fileName" ) ) {
				return $dir;
			}
		}

		throw new MWException( "Update script not found: $fileName" );
	}

	/**
	 * Returns the appropriate script file for use with the given database type.
	 * Searches for files with type-specific extensions in the script directories,
	 * falling back to the plain ".sql" extension if no specific script is found.
	 *
	 * @param string $name the script's name, without file extension
	 * @param string $type the database type, as returned by DatabaseBase::getType()
	 *
	 * @return string The path to the script file
	 * @throws MWException If the script was not found in any script directory
	 */
	private function getUpdateScriptPath( $name, $type ) {
		$extensions = array(
			'sqlite' => 'sqlite.sql',
			//'postgres' => 'pg.sql', // PG support is broken as of Dec 2013
			'mysql' => 'mysql.sql',
		);

		// Find the base directory by looking for a plain ".sql" file.
		$dir = $this->getUpdateScriptDir( "$name.sql" );

		if ( isset( $extensions[$type] ) ) {
			$extension = $extensions[$type];
			$path = "$dir/$name.$extension";

			// if a type-specific file exists, use it
			if ( file_exists( "$dir/$name.$extension" ) ) {
				return $path;
			}
		} else {
			throw new MWException( "Database type $type is not supported by Wikibase!" );
		}

		// we already know that the generic file exists
		$path = "$dir/$name.sql";
		return $path;
	}

	/**
	 * Applies updates to the wb_entity_per_page table.
	 *
	 * @param DatabaseUpdater $updater
	 * @param DatabaseBase $db
	 */
	private function updateEntityPerPageTable( DatabaseUpdater $updater, DatabaseBase $db ) {
		// Update from 0.1. or 0.2.
		if ( !$db->tableExists( 'wb_entity_per_page' ) ) {

			$updater->addExtensionTable(
				'wb_entity_per_page',
				$this->getUpdateScriptPath( 'AddEntityPerPage', $db->getType() )
			);

			$updater->addPostDatabaseUpdateMaintenance( 'Wikibase\RebuildEntityPerPage' );
		}
	}

	/**
	 * Applies updates to the wb_terms table.
	 *
	 * @param DatabaseUpdater $updater
	 * @param DatabaseBase $db
	 */
	private function updateTermsTable( DatabaseUpdater $updater, DatabaseBase $db ) {
		$withoutTermSearchKey = WikibaseRepo::getDefaultInstance()->
			getSettings()->getSetting( 'withoutTermSearchKey' );

		// ---- Update from 0.1 or 0.2. ----
		if ( !$db->fieldExists( 'wb_terms', 'term_search_key' ) && !$withoutTermSearchKey ) {

			$updater->addExtensionField(
				'wb_terms',
				'term_search_key',
				$this->getUpdateScriptPath( 'AddTermsSearchKey', $db->getType() )
			);

			$updater->addPostDatabaseUpdateMaintenance( 'Wikibase\RebuildTermsSearchKey' );
		}

		// creates wb_terms.term_row_id
		// and also wb_item_per_site.ips_row_id.
		$updater->addExtensionField(
			'wb_terms',
			'term_row_id',
			$this->getUpdateScriptPath( 'AddRowIDs', $db->getType() )
		);

		// add weight to wb_terms
		$updater->addExtensionField(
			'wb_terms',
			'term_weight',
			$this->getUpdateScriptPath( 'AddTermsWeight', $db->getType() )
		);

		// ---- Update from 0.4 ----

		// NOTE: this update doesn't work on SQLite, but it's not needed there anyway.
		if ( $db->getType() !== 'sqlite' ) {
			// make term_row_id BIGINT
			$updater->modifyExtensionField(
				'wb_terms',
				'term_row_id',
				$this->getUpdateScriptPath( 'MakeRowIDsBig', $db->getType() )
			);
		}

		// updated indexes
		$updater->addExtensionIndex(
			'wb_terms',
			'term_search',
			$this->getUpdateScriptPath( 'UpdateTermIndexes', $db->getType() )
		);
	}

	/**
	 * @see Store::newIdGenerator
	 *
	 * @since 0.1
	 *
	 * @return IdGenerator
	 */
	public function newIdGenerator() {
		return new SqlIdGenerator( 'wb_id_counters', wfGetDB( DB_MASTER ) );
	}

	/**
	 * @see Store::newSiteLinkCache
	 *
	 * @since 0.1
	 *
	 * @return SiteLinkCache
	 */
	public function newSiteLinkCache() {
		return new SiteLinkTable( 'wb_items_per_site', false );
	}

	/**
	 * @see Store::newEntityPerPage
	 *
	 * @since 0.3
	 *
	 * @return EntityPerPage
	 */
	public function newEntityPerPage() {
		return new EntityPerPageTable();
	}

	/**
	 * @see Store::getEntityLookup
	 * @see SqlStore::getEntityRevisionLookup
	 *
	 * @since 0.4
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $uncached = '' ) {
		return $this->getEntityRevisionLookup( $uncached );
	}

	/**
	 * @see Store::getEntityStoreWatcher
	 *
	 * @since 0.5
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher() {
		if ( !$this->entityStoreWatcher ) {
			$this->entityStoreWatcher = new DispatchingEntityStoreWatcher();
		}

		return $this->entityStoreWatcher;
	}

	/**
	 * @see Store::getEntityStore
	 *
	 * @since 0.5
	 *
	 * @return EntityStore
	 */
	public function getEntityStore() {
		if ( !$this->entityStore ) {
			$this->entityStore = $this->newEntityStore();
		}

		return $this->entityStore;
	}

	/**
	 * @return WikiPageEntityStore
	 */
	protected function newEntityStore() {
		$contentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();
		$idGenerator = $this->newIdGenerator();
		$entityPerPage = $this->newEntityPerPage();

		$store = new WikiPageEntityStore( $contentFactory, $idGenerator, $entityPerPage );
		$store->registerWatcher( $this->getEntityStoreWatcher() );
		return $store;
	}


	/**
	 * @see Store::getEntityRevisionLookup
	 *
	 * @since 0.4
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $uncached = '' ) {
		if ( !$this->entityRevisionLookup ) {
			list( $this->rawEntityRevisionLookup, $this->entityRevisionLookup ) = $this->newEntityRevisionLookup();
		}

		if ( $uncached === 'uncached' ) {
			return $this->rawEntityRevisionLookup;
		} else {
			return $this->entityRevisionLookup;
		}
	}

	/**
	 * Creates a strongly connected pair of EntityRevisionLookup services, the first being the raw
	 * uncached lookup, the second being the cached lookup.
	 *
	 * @return array( WikiPageEntityLookup, CachingEntityRevisionLookup )
	 */
	protected function newEntityRevisionLookup() {
		//NOTE: Keep in sync with DirectSqlStore::newEntityLookup on the client
		$key = $this->cachePrefix . ':WikiPageEntityLookup';

		$rawLookup = new WikiPageEntityLookup( $this->contentCodec, false );

		// Maintain a list of watchers to be notified of changes to any entities,
		// in order to update caches.
		/** @var WikiPageEntityStore $dispatcher */
		$dispatcher = $this->getEntityStoreWatcher();

		// Lower caching layer using persistent cache (e.g. memcached).
		$persistentCachingLookup = new CachingEntityRevisionLookup(
			$rawLookup,
			wfGetCache( $this->cacheType ),
			$this->cacheDuration,
			$key
		);
		// We need to verify the revision ID against the database to avoid stale data.
		$persistentCachingLookup->setVerifyRevision( true );
		$dispatcher->registerWatcher( $persistentCachingLookup );

		// Top caching layer using an in-process hash.
		$hashCachingLookup = new CachingEntityRevisionLookup(
			$persistentCachingLookup,
			new HashBagOStuff()
		);
		// No need to verify the revision ID, we'll ignore updates that happen during the request.
		$hashCachingLookup->setVerifyRevision( false );
		$dispatcher->registerWatcher( $hashCachingLookup );

		return array( $rawLookup, $hashCachingLookup );
	}

	/**
	 * @see Store::getEntityInfoBuilder
	 *
	 * @since 0.4
	 *
	 * @return EntityInfoBuilder
	 */
	public function getEntityInfoBuilder() {
		if ( !$this->entityInfoBuilder ) {
			$this->entityInfoBuilder = $this->newEntityInfoBuilder();
		}

		return $this->entityInfoBuilder;
	}

	/**
	 * Creates a new EntityInfoBuilder
	 *
	 * @return EntityInfoBuilder
	 */
	protected function newEntityInfoBuilder() {
		//TODO: Get $idParser from WikibaseRepo?
		$idParser = new BasicEntityIdParser();
		$builder = new SqlEntityInfoBuilder( $idParser );
		return $builder;
	}

	/**
	 * @see Store::getPropertyInfoStore
	 *
	 * @since 0.4
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( !$this->propertyInfoTable ) {
			$this->propertyInfoTable = $this->newPropertyInfoTable();
		}

		return $this->propertyInfoTable;
	}

	/**
	 * Creates a new PropertyInfoTable
	 *
	 * @return PropertyInfoTable
	 */
	protected function newPropertyInfoTable() {
		$usePropertyInfoTable = WikibaseRepo::getDefaultInstance()->
			getSettings()->getSetting( 'usePropertyInfoTable' );

		if ( $usePropertyInfoTable ) {
			$table = new PropertyInfoTable( false );
			$key = $this->cachePrefix . ':CachingPropertyInfoStore';
			return new CachingPropertyInfoStore( $table, ObjectCache::getInstance( $this->cacheType ),
				$this->cacheDuration, $key );
		} else {
			// dummy info store
			return new DummyPropertyInfoStore();
		}
	}

	/**
	 * Returns an ChangesTable
	 *
	 * @since 0.5
	 *
	 * @return ChangesTable
	 */
	public function getChangesTable() {
		if ( $this->changesTable === null ) {
			$this->changesTable = new ChangesTable( $this->changesDatabase );
		}

		return $this->changesTable;
	}

}
