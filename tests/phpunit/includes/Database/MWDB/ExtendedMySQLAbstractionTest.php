<?php

namespace Wikibase\Repo\Test\Database\MWDB;

use Wikibase\Repo\Database\MWDB\ExtendedMySQLAbstraction;
use Wikibase\Repo\LazyDBConnectionProvider;

/**
 * Unit tests for the Wikibase\Repo\Database\MWDB\ExtendedMySQLAbstraction class.
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
class ExtendedMySQLAbstractionTest extends ExtendedAbstractionTest {

	/**
	 * @see ExtendedAbstractionTest::newInstance
	 *
	 * @return ExtendedMySQLAbstraction
	 */
	protected function newInstance() {
		return new ExtendedMySQLAbstraction( new LazyDBConnectionProvider( DB_MASTER ) );
	}

}
