<?php

namespace Wikibase\Repo\Query\SQLStore;

use Wikibase\Repo\Query\QueryEngineResult;
use Wikibase\Repo\Query\QueryEngine;
use Wikibase\Repo\Database\QueryInterface;

use Ask\Language\Query;
use Ask\Language\Description\Description;
use Ask\Language\Option\QueryOptions;
use Ask\Language\Selection\SelectionRequest;

/**
 * Simple query engine that works on top of the SQLStore.
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
 * @since wd.qe
 *
 * @file
 * @ingroup WikibaseSQLStore
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class Engine implements QueryEngine {

	/**
	 * @since wd.qe
	 *
	 * @var StoreConfig
	 */
	private $config;

	/**
	 * @since wd.qe
	 *
	 * @var QueryInterface
	 */
	private $queryInterface;

	/**
	 * Constructor.
	 *
	 * @since wd.qe
	 *
	 * @param StoreConfig $storeConfig
	 * @param QueryInterface $queryInterface
	 */
	public function __construct( StoreConfig $storeConfig, QueryInterface $queryInterface ) {
		$this->config = $storeConfig;
		$this->queryInterface = $queryInterface;
	}

	/**
	 * @see QueryEngine::runQuery
	 *
	 * @since wd.qe
	 *
	 * @param Query $query
	 *
	 * @return QueryEngineResult
	 */
	public function runQuery( Query $query ) {
		$internalEntityIds = $this->findQueryMatches( $query->getDescription(), $query->getOptions() );

		$result = $this->selectRequestedFields( $internalEntityIds, $query->getSelectionRequests() );

		return $result;
	}

	/**
	 * Finds all entities that match the selection criteria.
	 * The matching entities are returned as an array of internal entity ids.
	 *
	 * @since wd.qe
	 *
	 * @param Description $description
	 * @param QueryOptions $options
	 *
	 * @return int[]
	 */
	private function findQueryMatches( Description $description, QueryOptions $options ) {
		// TODO
	}

	/**
	 * Selects all the quested data from the matching entities.
	 * This data is put in a QueryEngineResult object which is then returned.
	 *
	 * @since wd.qe
	 *
	 * @param array $internalEntityIds
	 * @param array $query
	 *
	 * @return QueryEngineResult
	 */
	private function selectRequestedFields( array $internalEntityIds, array $query ) {
		// TODO
	}

}
