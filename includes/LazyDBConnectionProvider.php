<?php

namespace Wikibase\Repo;

use DatabaseBase;

/**
 * Lazy database connection provider.
 * The connection is fetched when needed using the id provided in the constructor.
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
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class LazyDBConnectionProvider implements DBConnectionProvider {

	/**
	 * @since 0.4
	 *
	 * @var DatabaseBase|null
	 */
	protected $connection = null;

	/**
	 * @since 0.4
	 *
	 * @var int|null
	 */
	protected $connectionId = null;

	/**
	 * @since 0.4
	 *
	 * @var string|array
	 */
	protected $groups;

	/**
	 * @since 0.4
	 *
	 * @var string|boolean $wiki
	 */
	protected $wiki;

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 *
	 * @param int $connectionId
	 * @param string|array $groups
	 * @param string|boolean $wiki
	 */
	public function __construct( $connectionId, $groups = array(), $wiki = false ) {
		$this->connectionId = $connectionId;
		$this->groups = $groups;
		$this->wiki = $wiki;
	}

	/**
	 * @see DBConnectionProvider::getConnection
	 *
	 * @since 0.4
	 *
	 * @return DatabaseBase
	 */
	public function getConnection() {
		if ( $this->connection === null ) {
			$this->connection = wfGetLB( $this->wiki )->getConnection( $this->connectionId, $this->groups, $this->wiki );
		}

		assert( $this->connection instanceof DatabaseBase );

		return $this->connection;
	}

	/**
	 * @see DBConnectionProvider::releaseConnection
	 *
	 * @since 0.4
	 */
	public function releaseConnection() {
		if ( $this->wiki !== false && $this->connection !== null ) {
			wfGetLB( $this->wiki )->reuseConnection( $this->connection );
		}
	}

}