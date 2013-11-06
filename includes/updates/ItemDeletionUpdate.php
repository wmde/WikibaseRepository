<?php

namespace Wikibase;

/**
 * Deletion update to handle deletion of Wikibase items.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ItemDeletionUpdate extends EntityDeletionUpdate {

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param ItemContent $newContent
	 */
	public function __construct( ItemContent $newContent ) {
		$this->content = $newContent;
	}

	/**
	 * @see EntityDeletionUpdate::doTypeSpecificStuff
	 *
	 * @since 0.1
	 *
	 * @param Store $store
	 * @param Entity $entity
	 */
	protected function doTypeSpecificStuff( Store $store, Entity $entity ) {
		$store->newSiteLinkCache()->deleteLinksOfItem( $entity->getId() );
	}

}
