<?php

namespace Wikibase;
use Wikibase\store\EntityStore;
use Wikibase\store\EntityStoreWatcher;

/**
 * Store interface. All interaction with store Wikibase does on top
 * of storing pages and associated core MediaWiki indexing is done
 * through this interface.
 *
 * @todo: provide getXXX() methods for getting local pseudo-singletons (shared service objects).
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Store {

	/**
	 * Returns a new SiteLinkCache for this store.
	 *
	 * @since 0.1
	 *
	 * @return SiteLinkCache
	 *
	 * @todo: rename to newSiteLinkIndex
	 */
	public function newSiteLinkCache();

	/**
	 * Removes all data from the store.
	 *
	 * @since 0.1
	 */
	public function clear();

	/**
	 * Rebuilds the store.
	 *
	 * @since 0.1
	 */
	public function rebuild();

	/**
	 * Returns a TermIndex for this store.
	 *
	 * @since 0.4
	 *
	 * @return TermIndex
	 */
	public function getTermIndex();

	/**
	 * Returns a new IdGenerator for this store.
	 *
	 * @since 0.1
	 *
	 * @return IdGenerator
	 */
	public function newIdGenerator();

	/**
	 * Return a new EntityPerPage.
	 *
	 * @since 0.3
	 *
	 * @return EntityPerPage
	 */
	public function newEntityPerPage();

	/**
	 * Returns an EntityLookup
	 *
	 * @since 0.4
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $uncached = '' );

	/**
	 * Returns an EntityRevisionLookup
	 *
	 * @since 0.5
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $uncached = '' );

	/**
	 * Returns an EntityStore
	 *
	 * @since 0.5
	 *
	 * @return EntityStore
	 */
	public function getEntityStore();

	/**
	 * Returns an EntityStoreWatcher that should be notified of changes to
	 * entities, in order to keep any caches updated.
	 *
	 * @since 0.5
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher();

	/**
	 * Returns an EntityInfoBuilder
	 *
	 * @since 0.5
	 *
	 * @return EntityInfoBuilder
	 */
	public function getEntityInfoBuilder();

	/**
	 * Returns an PropertyInfoStore
	 *
	 * @since 0.4
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore();

}
