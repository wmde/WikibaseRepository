<?php

namespace Wikibase;

use InvalidArgumentException;
use Iterator;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\ItemId;

/**
 * Represents a lookup database table that make the link between entities and pages.
 * Corresponds to the wb_entities_per_page table.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 * @author Daniel Kinzler
 */
class EntityPerPageTable implements EntityPerPage {

	/**
	 * @see EntityPerPage::addEntityPage
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 *
	 * @throws InvalidArgumentException
	 * @return boolean Success indicator
	 */
	public function addEntityPage( EntityId $entityId, $pageId ) {
		if ( !is_int( $pageId ) ) {
			throw new InvalidArgumentException( '$pageId must be an int' );
		}

		if ( $pageId <= 0 ) {
			throw new InvalidArgumentException( '$pageId must be greater than 0' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$select = $dbw->selectField(
			'wb_entity_per_page',
			'epp_page_id',
			array(
				'epp_entity_id' => $entityId->getNumericId(),
				'epp_entity_type' => $entityId->getEntityType()
			),
			__METHOD__
		);
		if( $select !== false ) {
			return false;
		}

		return $dbw->insert(
			'wb_entity_per_page',
			array(
				'epp_entity_id' => $entityId->getNumericId(),
				'epp_entity_type' => $entityId->getEntityType(),
				'epp_page_id' => $pageId
			),
			__METHOD__
		);
	}

	/**
	 * @see EntityPerPage::deleteEntityPage
	 *
	 * @param EntityId $entityId
	 * @param int $pageId
	 *
	 * @return boolean Success indicator
	 */
	public function deleteEntityPage( EntityId $entityId, $pageId ) {
		$this->deleteEntity( $entityId );
	}

	/**
	 * @since 0.4
	 *
	 * @param EntityId $entityId
	 *
	 * @return boolean
	 */
	public function deleteEntity( EntityId $entityId ) {
		$dbw = wfGetDB( DB_MASTER );

		return $dbw->delete(
			'wb_entity_per_page',
			array(
				'epp_entity_id' => $entityId->getNumericId(),
				'epp_entity_type' => $entityId->getEntityType()
			),
			__METHOD__
		);
	}

	/**
	 * @see EntityPerPage::clear
	 *
	 * @since 0.2
	 *
	 * @return boolean Success indicator
	 */
	public function clear() {
		return wfGetDB( DB_MASTER )->delete( 'wb_entity_per_page', '*', __METHOD__ );
	}

	/**
	 * @see EntityPerPage::rebuild
	 *
	 * @since 0.2
	 *
	 * @return boolean success indicator
	 */
	public function rebuild() {
		// FIXME: class not found!
		$rebuilder = new EntityPerPageRebuilder();
		$rebuilder->rebuild( $this );

		return true;
	}

	/**
	 * @see EntityPerPage::getEntitiesWithoutTerm
	 *
	 * @since 0.2
	 *
	 * @param string $termType Can be any member of the Term::TYPE_ enum
	 * @param string|null $language Restrict the search for one language. By default the search is done for all languages.
	 * @param string|null $entityType Can be "item", "property" or "query". By default the search is done for all entities.
	 * @param integer $limit Limit of the query.
	 * @param integer $offset Offset of the query.
	 *
	 * @return EntityId[]
	 */
	public function getEntitiesWithoutTerm( $termType, $language = null, $entityType = null, $limit = 50, $offset = 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$conditions = array(
			'term_entity_type IS NULL'
		);
		$joinConditions = 'term_entity_id = epp_entity_id AND term_entity_type = epp_entity_type AND term_type = ' . $dbr->addQuotes( $termType );

		if ( $language !== null ) {
			$joinConditions .= ' AND term_language = ' . $dbr->addQuotes( $language );
		}

		if ( $entityType !== null ) {
			$conditions[] = 'epp_entity_type = ' . $dbr->addQuotes( $entityType );
		}

		$rows = $dbr->select(
			array( 'wb_entity_per_page', 'wb_terms' ),
			array(
				'entity_id' => 'epp_entity_id',
				'entity_type' => 'epp_entity_type',
			),
			$conditions,
			__METHOD__,
			array(
				'OFFSET' => $offset,
				'LIMIT' => $limit,
				'ORDER BY' => 'epp_page_id DESC'
			),
			array( 'wb_terms' => array( 'LEFT JOIN', $joinConditions ) )
		);

		return $this->getEntityIdsFromRows( $rows );
	}

	protected function getEntityIdsFromRows( $rows ) {
		$entities = array();
		$idParser = new BasicEntityIdParser();

		foreach ( $rows as $row ) {
			$id = new EntityId( $row->entity_type, (int)$row->entity_id );
			$entities[] = $idParser->parse( $id->getSerialization() );
		}

		return $entities;
	}

	/**
	 * Return all items without sitelinks
	 *
	 * @since 0.4
	 *
	 * @param string|null $siteId Restrict the request to a specific site.
	 * @param integer $limit Limit of the query.
	 * @param integer $offset Offset of the query.
	 * @return ItemId[]
	 */
	public function getItemsWithoutSitelinks( $siteId = null, $limit = 50, $offset = 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$conditions = array(
			'ips_site_page IS NULL'
		);
		$conditions['epp_entity_type'] = Item::ENTITY_TYPE;
		$joinConditions = 'ips_item_id = epp_entity_id';

		if ( $siteId !== null ) {
			$joinConditions .= ' AND ips_site_id = ' . $dbr->addQuotes( $siteId );
		}

		$rows = $dbr->select(
			array( 'wb_entity_per_page', 'wb_items_per_site' ),
			array(
				'entity_id' => 'epp_entity_id'
			),
			$conditions,
			__METHOD__,
			array(
				'OFFSET' => $offset,
				'LIMIT' => $limit,
				'ORDER BY' => 'epp_page_id DESC'
			),
			array( 'wb_items_per_site' => array( 'LEFT JOIN', $joinConditions ) )
		);

		return $this->getItemIdsFromRows( $rows );
	}

	protected function getItemIdsFromRows( $rows ) {
		$itemIds = array();

		foreach ( $rows as $row ) {
			$itemIds[] = ItemId::newFromNumber( (int)$row->entity_id );
		}

		return $itemIds;
	}

	/**
	 * Returns an iterator providing an EntityId object for each entity.
	 *
	 * @see EntityPerPage::getEntities
	 * @param null|string $entityType
	 *
	 * @return Iterator
	 */
	public function getEntities( $entityType = null ) {
		//XXX: Would be nice to get the DBR from a load balancer and allow access to foreign wikis.
		// But since we return a ResultWrapper, we don't know when we can release the connection for re-use.

		if ( $entityType !== null ) {
			$where = array( 'epp_entity_type' => $entityType );
		} else {
			$where = array();
		}

		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'wb_entity_per_page',
			array( 'epp_entity_id', 'epp_entity_type' ),
			$where,
			__METHOD__
		);

		return new DatabaseRowEntityIdIterator( $rows, 'epp_entity_type', 'epp_entity_id' );
	}

}
