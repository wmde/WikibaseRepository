<?php

namespace Wikibase;

/**
 * Represents a lookup database table that make the link between entities and pages.
 * Corresponds to the wb_entities_per_page table.
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
 * @since 0.2
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 */
class EntityPerPageTable implements EntityPerPage {

	/**
	 * @see EntityPerPage::addEntityContent
	 *
	 * @since 0.2
	 *
	 * @param EntityContent $entityContent
	 *
	 * @return boolean Success indicator
	 */
	public function addEntityContent( EntityContent $entityContent ) {
		$dbr = wfGetDB( DB_SLAVE );
		$select = $dbr->selectField(
			'wb_entity_per_page',
			'epp_page_id',
			array(
				'epp_entity_id' => $entityContent->getEntity()->getId()->getNumericId(),
				'epp_entity_type' => $entityContent->getEntity()->getType()
			),
			__METHOD__
		);
		if( $select !== false ) {
			return false;
		}

		$dbw = wfGetDB( DB_MASTER );
		return $dbw->insert(
			'wb_entity_per_page',
			array(
				'epp_entity_id' => $entityContent->getEntity()->getId()->getNumericId(),
				'epp_entity_type' => $entityContent->getEntity()->getType(),
				'epp_page_id' => $entityContent->getTitle()->getArticleID()
			),
			__METHOD__
		);
	}

	/**
	 * @see EntityPerPage::deleteEntityContent
	 *
	 * @since 0.2
	 *
	 * @param EntityContent $entityContent
	 *
	 * @return boolean Success indicator
	 */
	public function deleteEntityContent( EntityContent $entityContent ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->delete(
			'wb_entity_per_page',
			array(
				'epp_entity_id' => $entityContent->getEntity()->getId()->getNumericId(),
				'epp_entity_type' => $entityContent->getEntity()->getType()
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
		$dbw = wfGetDB( DB_MASTER );
		$begin = 0;
		$entityContentFactory = EntityContentFactory::singleton();
		do {
			$pages = $dbw->select(
				array( 'page' ),
				array( 'page_title' ),
				array( 'page_namespace' => Utils::getEntityNamespaces() ),
				__METHOD__,
				array( 'LIMIT' => 1000, 'OFFSET' => $begin )
			);

			foreach ( $pages as $pageRow ) {
				$entityContent = $entityContentFactory->getFromId( EntityId::newFromPrefixedId( $pageRow->page_title ), \Revision::RAW );

				if ( $entityContent !== null ) {
					$this->addEntityContent( $entityContent );
				}
			}
			$begin += 1000;
		} while ( $pages->numRows() === 1000 );
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
			$condisions[] = 'epp_entity_type = ' . $dbr->addQuotes( $entityType );
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

		$entities = array();
		foreach ( $rows as $row ) {
			$entities[] = new EntityId( $row->entity_type, (int)$row->entity_id );
		}
		return $entities;
	}
}
