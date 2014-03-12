<?php

namespace Wikibase;

use Iterator;
use Title;

/**
 * Interface to a table that join wiki pages and entities.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 */
interface EntityPerPage {

	/**
	 * Adds a new link between an entity and a page
	 *
	 * @since 0.5
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 *
	 * @return boolean Success indicator
	 */
	public function addEntityPage( EntityId $entityId, $pageId );

	/**
	 * Removes a link between an entity and a page
	 *
	 * @since 0.5
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 *
	 * @return boolean Success indicator
	 */
	public function deleteEntityPage( EntityId $entityId, $pageId );

	/**
	 * Removes all associations of the given entity
	 *
	 * @since 0.5
	 *
	 * @param EntityId $entityId
	 *
	 * @return boolean Success indicator
	 */
	public function deleteEntity( EntityId $entityId );

	/**
	 * Clears the table
	 *
	 * @since 0.2
	 *
	 * @return boolean Success indicator
	 */
	public function clear();

	/**
	 * Rebuilds the table
	 *
	 * @since 0.2
	 *
	 * @return boolean success indicator
	 */
	public function rebuild();

	/**
	 * Return all entities without a specify term
	 *
	 * @since 0.2
	 *
	 * @todo: move this to the TermIndex service
	 *
	 * @param string $termType Can be any member of the Term::TYPE_ enum
	 * @param string|null $language Restrict the search for one language. By default the search is done for all languages.
	 * @param string|null $entityType Can be "item", "property" or "query". By default the search is done for all entities.
	 * @param integer $limit Limit of the query.
	 * @param integer $offset Offset of the query.
	 *
	 * @return EntityId[]
	 */
	public function getEntitiesWithoutTerm( $termType, $language = null, $entityType = null, $limit = 50, $offset = 0 );


	/**
	 * Return all items without sitelinks
	 *
	 * @since 0.4
	 *
	 * @todo: move this to the SiteLinkLookup service
	 *
	 * @param string|null $siteId Restrict the request to a specific site.
	 * @param integer $limit Limit of the query.
	 * @param integer $offset Offset of the query.
	 *
	 * @return EntityId[]
	 */
	public function getItemsWithoutSitelinks( $siteId = null, $limit = 50, $offset = 0 );

	/**
	 * Returns an iterator providing an EntityId object for each entity.
	 *
	 * @param string $entityType The type of entity to return, or null for any type.
	 *
	 * @return Iterator
	 */
	public function getEntities( $entityType = null );
}
