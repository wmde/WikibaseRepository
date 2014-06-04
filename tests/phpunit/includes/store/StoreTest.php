<?php

namespace Wikibase\Test;

use Wikibase\Repo\WikibaseRepo;
use Wikibase\SqlStore;
use Wikibase\Store;

/**
 * @covers Wikibase\Store
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 * @group Database
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class StoreTest extends \MediaWikiTestCase {

	public function instanceProvider() {
		$contentCodec = WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec();
		$entityFactory = WikibaseRepo::getDefaultInstance()->getEntityFactory();

		$instances = array(
			new SqlStore( $contentCodec, $entityFactory )
		);

		return array( $instances );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testRebuild( Store $store ) {
		$store->rebuild();
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewSiteLinkCache( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\SiteLinkLookup', $store->newSiteLinkCache() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewTermCache( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\TermIndex', $store->getTermIndex() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewIdGenerator( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\IdGenerator', $store->newIdGenerator() );
	}

}
