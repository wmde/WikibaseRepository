<?php

namespace Wikibase\Repo\Test\Database;

use Wikibase\Repo\Database\MediaWikiQueryInterface;
use Wikibase\Repo\Database\TableDefinition;
use Wikibase\Repo\Database\FieldDefinition;

/**
 * Unit tests for the Wikibase\Repo\Database\MediaWikiQueryInterface class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since wd.db
 *
 * @ingroup WikibaseRepoTest
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseDatabase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class MediaWikiQueryInterfaceTest extends \MediaWikiTestCase {

	protected function tearDown() {
		parent::tearDown();

		$this->dropTablesIfStillThere();
	}

	protected function dropTablesIfStillThere() {
		$queryInterface = $this->newInstance();

		foreach ( array( 'differentfieldtypes', 'defaultfieldvalues', 'notnullfields' ) as $tableName ) {
			if ( $queryInterface->tableExists( $tableName ) ) {
				$queryInterface->dropTable( $tableName );
			}
		}
	}

	/**
	 * @return MediaWikiQueryInterface
	 */
	protected function newInstance() {
		$conn = new \Wikibase\Repo\LazyDBConnectionProvider( DB_MASTER );

		return new MediaWikiQueryInterface(
			$conn,
			new \Wikibase\Repo\Database\MWDB\ExtendedMySQLAbstraction( $conn )
		);
	}

	public function tableExistsProvider() {
		$argLists = array();

		$argLists[] = array( 'user', true );
		$argLists[] = array( 'xdgxftjhreyetfytj', false );

		return $argLists;
	}

	/**
	 * @dataProvider tableExistsProvider
	 *
	 * @param string $tableName
	 * @param boolean $expected
	 */
	public function testTableExists( $tableName, $expected ) {
		$actual = $this->newInstance()->tableExists( $tableName );

		$this->assertInternalType( 'boolean', $actual );
		$this->assertEquals( $expected, $actual );
	}

	public function tableProvider() {
		$tables = array();

		$tables[] = new TableDefinition( 'differentfieldtypes', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER ),
			new FieldDefinition( 'floatfield', FieldDefinition::TYPE_FLOAT ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT ),
			new FieldDefinition( 'boolfield', FieldDefinition::TYPE_BOOLEAN ),
		) );

		$tables[] = new TableDefinition( 'defaultfieldvalues', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, 42 ),
		) );

		$tables[] = new TableDefinition( 'notnullfields', array(
			new FieldDefinition( 'intfield', FieldDefinition::TYPE_INTEGER, null, null, false ),
			new FieldDefinition( 'textfield', FieldDefinition::TYPE_TEXT, null, null, false ),
		) );

		return $this->arrayWrap( $tables );
	}

	/**
	 * @dataProvider tableProvider
	 *
	 * @param TableDefinition $table
	 */
	public function testCreateAndDropTable( TableDefinition $table ) {
		$queryInterface = $this->newInstance();

		$this->assertFalse(
			$queryInterface->tableExists( $table->getName() ),
			'Table should not exist before creation'
		);

		$success = $queryInterface->createTable( $table );

		$this->assertTrue(
			$success,
			'Creation function returned success'
		);

		$this->assertTrue(
			$queryInterface->tableExists( $table->getName() ),
			'Table "' . $table->getName() . '" exists after creation'
		);

		$this->assertTrue(
			$queryInterface->dropTable( $table->getName() ),
			'Table removal worked'
		);

		$this->assertFalse(
			$queryInterface->tableExists( $table->getName() ),
			'Table should not exist after deletion'
		);
	}

}
