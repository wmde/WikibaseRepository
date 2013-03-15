<?php

namespace Wikibase\Repo\Test\Query\SQLStore;

use Wikibase\Repo\Database\MWDB\ExtendedMySQLAbstraction;
use Wikibase\Repo\Database\MediaWikiQueryInterface;
use Wikibase\Repo\LazyDBConnectionProvider;
use Wikibase\Repo\Query\SQLStore\Engine;
use Wikibase\Repo\Query\SQLStore\StoreConfig;
use Wikibase\Repo\Test\Query\QueryEngineTest;

/**
 * Unit tests for the Wikibase\Repo\Query\SQLStore\Engine class.
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
 * @since wd.qe
 *
 * @ingroup WikibaseRepoTest
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseQuery
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class EngineTest extends QueryEngineTest {

	/**
	 * @see QueryEngineTest::getInstances
	 */
	protected function getInstances() {
		$instances = array();

		$connectionProvider = new LazyDBConnectionProvider( DB_MASTER );
		$storeConfig = new StoreConfig( 'foo', 'bar', array() );
		$queryInterface = new MediaWikiQueryInterface(
			$connectionProvider,
			new ExtendedMySQLAbstraction( $connectionProvider )
		);

		$instances[] = new Engine( $storeConfig, $queryInterface );

		return $instances;
	}

}
