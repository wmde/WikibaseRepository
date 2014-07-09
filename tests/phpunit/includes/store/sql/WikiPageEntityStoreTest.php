<?php

namespace Wikibase\Test;

use ContentHandler;
use Revision;
use Status;
use User;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityContentFactory;
use Wikibase\EntityId;
use Wikibase\EntityPerPageTable;
use Wikibase\Lib\Store\EntityRedirect;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\WikiPageEntityStore;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\SqlIdGenerator;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\WikiPageEntityRevisionLookup;

/**
 * @covers Wikibase\Repo\Store\WikiPageEntityStore
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikiPageEntityStoreTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @see EntityLookupTest::newEntityLoader()
	 *
	 * @return array array( EntityStore, EntityLookup )
	 */
	protected function createStoreAndLookup() {
		// make sure the term index is empty to avoid conlficts.
		WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex()->clear();

		//NOTE: we want to test integration of WikiPageEntityRevisionLookup and WikiPageEntityStore here!
		$contentCodec = WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec();

		$lookup = new WikiPageEntityRevisionLookup( $contentCodec, false );

		$typeMap = WikibaseRepo::getDefaultInstance()->getContentModelMappings();

		$store = new WikiPageEntityStore(
			new EntityContentFactory( $typeMap ),
			new SqlIdGenerator( 'wb_id_counters', wfGetDB( DB_MASTER ) ),
			new EntityPerPageTable()
		);

		return array( $store, $lookup );
	}

	public function testSaveEntity() {
		/* @var WikiPageEntityStore $store */
		/* @var EntityRevisionLookup $lookup */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( 'Wikibase\Lib\Store\EntityStoreWatcher' );
		$watcher->expects( $this->exactly( 2 ) )
			->method( 'entityUpdated' );
		$watcher->expects( $this->never() )
			->method( 'redirectUpdated' );

		$store->registerWatcher( $watcher );

		// create one
		$one = Item::newEmpty();
		$one->setLabel( 'en', 'one' );

		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );
		$oneId = $r1->getEntity()->getId();

		$r1actual = $lookup->getEntityRevision( $oneId );
		$this->assertEquals( $r1->getRevision(), $r1actual->getRevision(), 'revid' );
		$this->assertEquals( $r1->getTimestamp(), $r1actual->getTimestamp(), 'timestamp' );
		$this->assertEquals( $r1->getEntity()->getId(), $r1actual->getEntity()->getId(), 'entity id' );

		// TODO: check notifications in wb_changes table!

		// update one
		$one = Item::newEmpty();
		$one->setId( $oneId );
		$one->setLabel( 'en', 'ONE' );

		$r2 = $store->saveEntity( $one, 'update one', $user, EDIT_UPDATE );
		$this->assertNotEquals( $r1->getRevision(), $r2->getRevision(), 'expected new revision id' );

		$r2actual = $lookup->getEntityRevision( $oneId );
		$this->assertEquals( $r2->getRevision(), $r2actual->getRevision(), 'revid' );
		$this->assertEquals( $r2->getTimestamp(), $r2actual->getTimestamp(), 'timestamp' );
		$this->assertEquals( $r2->getEntity()->getId(), $r2actual->getEntity()->getId(), 'entity id' );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertNotEmpty( $termIndex->getTermsOfEntity( $oneId ), 'getTermsOfEntity()' );

		$this->assertEntityPerPage( true, $oneId );
	}

	public function provideSaveEntityError() {
		$firstItem = Item::newEmpty();
		$firstItem->setLabel( 'en', 'one' );

		$secondItem = Item::newEmpty();
		$secondItem->setId( 768476834 );
		$secondItem->setLabel( 'en', 'Bwahahaha' );
		$secondItem->setLabel( 'de', 'Kähähähä' );

		return array(
			'not fresh' => array(
				'entity' => $firstItem,
				'flags' => EDIT_NEW,
				'baseRevid' => false,
				'error' => 'Wikibase\Lib\Store\StorageException'
			),

			'not exists' => array(
				'entity' => $secondItem,
				'flags' => EDIT_UPDATE,
				'baseRevid' => false,
				'error' => 'Wikibase\Lib\Store\StorageException'
			),
		);
	}

	/**
	 * @dataProvider provideSaveEntityError
	 */
	public function testSaveEntityError( Entity $entity, $flags, $baseRevId, $error ) {
		/* @var EntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// setup target item
		$one = Item::newEmpty();
		$one->setLabel( 'en', 'one' );
		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );

		// inject ids
		if ( is_int( $baseRevId ) ) {
			// use target item's revision as an offset
			$baseRevId += $r1->getRevision();
		}

		if ( $entity->getId() === null ) {
			// use target item's id
			$entity->setId( $r1->getEntity()->getId() );
		}

		// check for error
		$this->setExpectedException( $error );
		$store->saveEntity( $entity, '', $GLOBALS['wgUser'], $flags, $baseRevId );
	}

	private function itemSupportsRedirects() {
		$handler = ContentHandler::getForModelID( CONTENT_MODEL_WIKIBASE_ITEM );
		return $handler->supportsRedirects();
	}

	public function testSaveRedirect() {
		if ( !$this->itemSupportsRedirects() ) {
			// As of 2014-06-30, redirects are still experimental.
			// So do a feature check before trying to test redirects.
			$this->markTestSkipped( 'Redirects not yet supported.' );
		}

		/* @var WikiPageEntityStore $store */
		/* @var EntityRevisionLookup $lookup */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( 'Wikibase\Lib\Store\EntityStoreWatcher' );
		$watcher->expects( $this->exactly( 1 ) )
			->method( 'redirectUpdated' );
		$watcher->expects( $this->never() )
			->method( 'entityDeleted' );

		$store->registerWatcher( $watcher );

		// create one
		$one = Item::newEmpty();
		$one->setLabel( 'en', 'one' );

		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );
		$oneId = $r1->getEntity()->getId();

		// redirect one to Q33
		$q33 = new ItemId( 'Q33' );
		$redirect = new EntityRedirect( $oneId, $q33 );

		$redirectRevId = $store->saveRedirect( $redirect, 'redirect one', $user, EDIT_UPDATE );

		// FIXME: use the $lookup to check this, once EntityLookup supports redirects.
		$revision = Revision::newFromId( $redirectRevId );

		$this->assertTrue( $revision->getTitle()->isRedirect(), 'Title::isRedirect' );
		$this->assertTrue( $revision->getContent()->isRedirect(), 'EntityContent::isRedirect()' );
		$this->assertTrue( $revision->getContent()->getEntityRedirect()->equals( $redirect ), 'getEntityRedirect()' );

		$this->assertEntityPerPage( false, $oneId );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertEmpty( $termIndex->getTermsOfEntity( $oneId ), 'getTermsOfEntity' );

		// TODO: check notifications in wb_changes table!

		// Revert to original content
		$r1 = $store->saveEntity( $one, 'restore one', $user, EDIT_UPDATE );
		$revision = Revision::newFromId( $r1->getRevision() );

		$this->assertFalse( $revision->getTitle()->isRedirect(), 'Title::isRedirect' );
		$this->assertFalse( $revision->getContent()->isRedirect(), 'EntityContent::isRedirect()' );

		$this->assertEntityPerPage( true, $oneId );
	}

	public function unsupportedRedirectProvider() {
		$p1 = new PropertyId( 'P1' );
		$p2 = new PropertyId( 'P2' );

		return array(
			'P1 -> P2' => array( new EntityRedirect( $p1, $p2 ) ),
		);
	}

	/**
	 * @dataProvider unsupportedRedirectProvider
	 */
	public function testSaveRedirectFailure( EntityRedirect $redirect ) {
		/* @var WikiPageEntityStore $store */
		/* @var EntityRevisionLookup $lookup */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		$this->setExpectedException( 'Wikibase\Lib\Store\StorageException' );
		$store->saveRedirect( $redirect, 'redirect one', $user, EDIT_UPDATE );
	}

	public function testUserWasLastToEdit() {
		/* @var WikiPageEntityStore $store */
		/* @var EntityRevisionLookup $lookup */
		list( $store, $lookup ) = $this->createStoreAndLookup();

		$anonUser = User::newFromId(0);
		$anonUser->setName( '127.0.0.1' );
		$user = User::newFromName( "EditEntityTestUser" );
		$item = Item::newEmpty();

		// check for default values, last revision by anon --------------------
		$item->setLabel( 'en', "Test Anon default" );
		$store->saveEntity( $item, 'testing', $anonUser, EDIT_NEW );
		$itemId = $item->getId();

		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by sysop --------------------
		$item->setLabel( 'en', "Test SysOp default" );
		$store->saveEntity( $item, 'Test SysOp default', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by anon --------------------
		$item->setLabel( 'en', "Test Anon with user" );
		$store->saveEntity( $item, 'Test Anon with user', $anonUser, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, false );
		$this->assertFalse( $res );

		// check for default values, last revision by sysop --------------------
		$item->setLabel( 'en', "Test SysOp with user" );
		$store->saveEntity( $item, 'Test SysOp with user', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $user, $itemId, false );
		$this->assertFalse( $res );

		// create an edit and check if the anon user is last to edit --------------------
		$lastRevId = $lookup->getLatestRevisionId( $itemId );
		$item->setLabel( 'en', "Test Anon" );
		$store->saveEntity( $item, 'Test Anon', $anonUser, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $anonUser, $itemId, $lastRevId );
		$this->assertTrue( $res );
		// also check that there is a failure if we use the sysop user
		$res = $store->userWasLastToEdit( $user, $itemId, $lastRevId );
		$this->assertFalse( $res );

		// create an edit and check if the sysop user is last to edit --------------------
		$lastRevId = $lookup->getLatestRevisionId( $itemId );
		$item->setLabel( 'en', "Test SysOp" );
		$store->saveEntity( $item, 'Test SysOp', $user, EDIT_UPDATE );
		$res = $store->userWasLastToEdit( $user, $itemId, $lastRevId );
		$this->assertTrue( $res );

		// also check that there is a failure if we use the anon user
		$res = $store->userWasLastToEdit( $anonUser, $itemId, $lastRevId );
		$this->assertFalse( $res );
	}

	public function testUpdateWatchlist() {
		/* @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$user = User::newFromName( "WikiPageEntityStoreTestUser2" );

		if ( $user->getId() === 0 ) {
			$user->addToDatabase();
		}

		$item = Item::newEmpty();
		$store->saveEntity( $item, 'testing', $user, EDIT_NEW );

		$itemId = $item->getId();

		$store->updateWatchlist( $user, $itemId, true );
		$this->assertTrue( $store->isWatching( $user, $itemId ) );

		$store->updateWatchlist( $user, $itemId, false );
		$this->assertFalse( $store->isWatching( $user, $itemId ) );
	}

	protected function newEntity() {
		$item = Item::newEmpty();
		return $item;
	}

	/**
	 * Convenience wrapper offering the legacy Status based interface for saving
	 * Entities.
	 *
	 * @todo: rewrite the tests using this
	 *
	 * @param WikiPageEntityStore $store
	 * @param Entity $entity
	 * @param string $summary
	 * @param User $user
	 * @param int $flags
	 * @param bool $baseRevId
	 *
	 * @return \Status
	 */
	protected function saveEntity(
		WikiPageEntityStore $store,
		Entity $entity,
		$summary = '',
		User $user = null,
		$flags = 0,
		$baseRevId = false
	) {
		if ( $user === null ) {
			$user = $GLOBALS['wgUser'];
		}

		try {
			$rev = $store->saveEntity( $entity, $summary, $user, $flags, $baseRevId );
			$status = Status::newGood( Revision::newFromId( $rev->getRevision() ) );
		} catch ( StorageException $ex ) {
			$status = $ex->getStatus();

			if ( !$status ) {
				$status = Status::newFatal( 'boohoo' );
			}
		}

		return $status;
	}

	public function testSaveFlags() {
		/* @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$entity = $this->newEntity();
		$prefix = get_class( $this ) . '/';

		// try to create without flags
		$entity->setLabel( 'en', $prefix . 'one' );
		$status = $this->saveEntity( $store, $entity, 'create item' );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-gone-missing' ),
			'try to create without flags, edit gone missing'
		);

		// try to create with EDIT_UPDATE flag
		$entity->setLabel( 'en', $prefix . 'two' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_UPDATE );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-gone-missing' ),
			'edit gone missing, try to create with EDIT_UPDATE'
		);

		// try to create with EDIT_NEW flag
		$entity->setLabel( 'en', $prefix . 'three' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertTrue(
			$status->isOK(),
			'create with EDIT_NEW flag for ' .
			$entity->getId()->getPrefixedId()
		);

		// ok, the item exists now in the database.

		// try to save with EDIT_NEW flag
		$entity->setLabel( 'en', $prefix . 'four' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue(
			$status->hasMessage( 'edit-already-exists' ),
			'try to save with EDIT_NEW flag, edit already exists'
		);

		// try to save with EDIT_UPDATE flag
		$entity->setLabel( 'en', $prefix . 'five' );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_UPDATE );
		$this->assertTrue(
			$status->isOK(),
			'try to save with EDIT_UPDATE flag, save failed'
		);

		// try to save without flags
		$entity->setLabel( 'en', $prefix . 'six' );
		$status = $this->saveEntity( $store, $entity, 'create item' );
		$this->assertTrue( $status->isOK(), 'try to save without flags, save failed' );
	}

	public function testRepeatedSave() {
		/* @var WikiPageEntityStore $store */
		list( $store, ) = $this->createStoreAndLookup();

		$entity = $this->newEntity();
		$prefix = get_class( $this ) . '/';

		// create
		$entity->setLabel( 'en', $prefix . "First" );
		$status = $this->saveEntity( $store, $entity, 'create item', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), 'create, save failed, status ok' );
		$this->assertTrue( $status->isGood(), 'create, status is good' );

		// change
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$entity->setLabel( 'en', $prefix . "Second" );
		$status = $this->saveEntity( $store, $entity, 'modify item', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), 'change, status ok' );
		$this->assertTrue( $status->isGood(), 'change, status good' );

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertNotEquals( $prev_id, $rev_id, "revision ID should change on edit" );

		// change again
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$entity->setLabel( 'en', $prefix . "Third" );
		$status = $this->saveEntity( $store, $entity, 'modify item again', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), 'change again, status ok' );
		$this->assertTrue( $status->isGood(), 'change again, status good' );

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertNotEquals( $prev_id, $rev_id, "revision ID should change on edit" );

		// save unchanged
		$prev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$status = $this->saveEntity( $store, $entity, 'save unmodified', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), 'save unchanged, save failed, status ok' );

		$rev_id = $store->getWikiPageForEntity( $entity->getId() )->getLatest();
		$this->assertEquals( $prev_id, $rev_id, "revision ID should stay the same if no change was made" );
	}

	public function testDeleteEntity() {
		/* @var WikiPageEntityStore $store */
		/* @var EntityRevisionLookup $lookup */
		list( $store, $lookup ) = $this->createStoreAndLookup();
		$user = $GLOBALS['wgUser'];

		// register mock watcher
		$watcher = $this->getMock( 'Wikibase\Lib\Store\EntityStoreWatcher' );
		$watcher->expects( $this->exactly( 1 ) )
			->method( 'entityDeleted' );

		$store->registerWatcher( $watcher );

		// create one
		$one = Item::newEmpty();
		$one->setLabel( 'en', 'one' );

		$r1 = $store->saveEntity( $one, 'create one', $user, EDIT_NEW );
		$oneId = $r1->getEntity()->getId();

		// sanity check
		$this->assertNotNull( $lookup->getEntityRevision( $oneId ) );

		// delete one
		$store->deleteEntity( $oneId, 'testing', $user );

		// check that it's gone
		$this->assertFalse( $lookup->getLatestRevisionId( $oneId ), 'getLatestRevisionId()' );
		$this->assertNull( $lookup->getEntityRevision( $oneId ), 'getEntityRevision()' );

		// check that the term index got updated (via a DataUpdate).
		$termIndex = WikibaseRepo::getDefaultInstance()->getStore()->getTermIndex();
		$this->assertEmpty( $termIndex->getTermsOfEntity( $oneId ), 'getTermsOfEntity' );

		// TODO: check notifications in wb_changes table!

		$this->assertEntityPerPage( false, $oneId );
	}

	private function assertEntityPerPage( $expected, EntityId $entityId ) {
		$epp = new EntityPerPageTable();

		$pageId = $epp->getPageIdForEntity( $entityId );

		if ( $expected === true ) {
			$this->assertGreaterThan( 0, $pageId );
		} else {
			$this->assertEquals( $expected, $pageId );
		}
	}

}
