<?php

namespace Wikibase\Repo\Test\Database\MWDB;

use Wikibase\Repo\Database\MWDB\ExtendedAbstraction;
use Wikibase\Repo\Database\TableDefinition;
use Wikibase\Repo\Database\FieldDefinition;

/**
 * Base class with tests for the Wikibase\Repo\Database\MWDB\ExtendedAbstraction deriving classes.
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
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class ExtendedAbstractionTest extends \MediaWikiTestCase {

	/**
	 * @return ExtendedAbstraction
	 */
	protected abstract function newInstance();

	protected function tearDown() {
		parent::tearDown();

		$this->dropTablesIfStillThere();
	}

	protected function dropTablesIfStillThere() {
		$queryInterface = $this->newInstance();

		foreach ( array( 'differentfieldtypes', 'defaultfieldvalues', 'notnullfields' ) as $tableName ) {
			if ( $queryInterface->getDB()->tableExists( $tableName ) ) {
				$queryInterface->getDB()->dropTable( $tableName );
			}
		}
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
		$extendedAbstraction = $this->newInstance();

		$this->assertFalse(
			$extendedAbstraction->getDB()->tableExists( $table->getName() ),
			'Table should not exist before creation'
		);

		$success = $extendedAbstraction->createTable( $table );

		$this->assertTrue(
			$success,
			'Creation function returned success'
		);

		$this->assertTrue(
			$extendedAbstraction->getDB()->tableExists( $table->getName() ),
			'Table "' . $table->getName() . '" exists after creation'
		);

		$this->assertTrue(
			$extendedAbstraction->getDB()->dropTable( $table->getName() ),
			'Table removal worked'
		);

		$this->assertFalse(
			$extendedAbstraction->getDB()->tableExists( $table->getName() ),
			'Table should not exist after deletion'
		);
	}

}
