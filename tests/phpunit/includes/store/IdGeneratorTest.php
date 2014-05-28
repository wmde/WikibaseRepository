<?php

namespace Wikibase\Test;

use Wikibase\IdGenerator;
use Wikibase\StoreFactory;

/**
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class IdGeneratorTest extends \MediaWikiTestCase {

	public function instanceProvider() {
		$instances = array( StoreFactory::getStore( 'sqlstore' )->newIdGenerator() );

		return array( $instances );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetNewId( IdGenerator $generator ) {
		/**
		 * @var IdGenerator $clone
		 */
		$clone = clone $generator;

		$id = $generator->getNewId( 'foo' );

		$this->assertInternalType( 'integer', $id );

		$id1 = $generator->getNewId( 'foo' );

		$this->assertInternalType( 'integer', $id1 );
		$this->assertNotEquals( $id, $id1 );

		$id2 = $generator->getNewId( 'bar' );
		$this->assertInternalType( 'integer', $id2 );

		$id3 = $clone->getNewId( 'foo' );

		$this->assertInternalType( 'integer', $id3 );

		$this->assertTrue( !in_array( $id3, array( $id, $id1 ), true ) );
	}

}
