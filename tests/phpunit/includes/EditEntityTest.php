<?php

namespace Wikibase\Test;

use FauxRequest;
use HashBagOStuff;
use RequestContext;
use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\EditEntity;
use Wikibase\EntityPermissionChecker;
use Wikibase\EntityTitleLookup;

/**
 * @covers Wikibase\EditEntity
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group EditEntity
 *
 * @group Database
 *        ^--- needed just because we are using Title objects.
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EditEntityTest extends \MediaWikiTestCase {

	protected $permissions;
	protected $userGroups;

	protected static function getUser( $name ) {
		$user = User::newFromName( $name );

		if ( $user->getId() === 0 ) {
			$user = User::createNew( $user->getName() );
		}

		return $user;
	}

	function setUp() {
		global $wgGroupPermissions, $wgHooks;

		parent::setUp();

		$this->permissions = $wgGroupPermissions;
		$this->userGroups = array( 'user' );

		if ( empty( $wgHooks['EditFilterMergedContent'] ) ) {
			// This fake ensures EditEntity::runEditFilterHooks is run and runtime errors are found
			$wgHooks['EditFilterMergedContent'] = array( null );
		}
	}

	function tearDown() {
		global $wgGroupPermissions, $wgHooks;

		$wgGroupPermissions = $this->permissions;

		if ( $wgHooks['EditFilterMergedContent'] === array( null ) ) {
			unset( $wgHooks['EditFilterMergedContent'] );
		}

		parent::tearDown();
	}

	/**
	 * @return EntityTitleLookup
	 */
	protected function newTitleLookupMock() {
		$titleLookup = $this->getMock( 'Wikibase\EntityTitleLookup' );

		$titleLookup->expects( $this->any() )
			->method( 'getTitleForID' )
			->will( $this->returnCallback( function ( EntityId $id ) {
				return Title::makeTitle( NS_MAIN, $id->getEntityType() . '/' . $id->getSerialization() );
			}));

		$titleLookup->expects( $this->any() )
			->method( 'getNamespaceForType' )
			->will( $this->returnValue( NS_MAIN ) );

		return $titleLookup;
	}

	/**
	 * @param array|null $permissions
	 *
	 * @return EntityPermissionChecker
	 */
	protected function newEntityPermissionCheckerMock( $permissions ) {
		$permissionChecker = $this->getMock( 'Wikibase\EntityPermissionChecker' );

		$checkAction = function ( $user, $action ) use( $permissions ) {
			if ( $permissions === null ) {
				return Status::newGood( true );
			} elseif ( isset( $permissions[$action] ) && $permissions[$action] )  {
				return Status::newGood( true );
			} else {
				return Status::newFatal( 'badaccess-group0' );
			}
		};

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntity' )
			->will( $this->returnCallback( $checkAction ) );

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntityType' )
			->will( $this->returnCallback( $checkAction ) );

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntityId' )
			->will( $this->returnCallback( $checkAction ) );

		return $permissionChecker;
	}

	/**
	 * @param MockRepository $repo
	 * @param Entity $entity
	 * @param User $user
	 * @param bool $baseRevId
	 *
	 * @param null|array $permissions map of actions to bool, indicating which actions are allowed.
	 *
	 * @return EditEntity
	 */
	protected function makeEditEntity( MockRepository $repo, Entity $entity, User $user = null, $baseRevId = false, $permissions = null ) {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		if ( !$user ) {
			$user = User::newFromName( 'EditEntityTestUser' );
		}

		$titleLookup = $this->newTitleLookupMock();
		$permissionChecker = $this->newEntityPermissionCheckerMock( $permissions );

		$edit = new EditEntity( $titleLookup, $repo, $repo, $permissionChecker, $entity, $user, $baseRevId, $context );

		return $edit;
	}

	/**
	 * @return MockRepository
	 */
	protected function makeMockRepo() {
		$repo = new MockRepository();

		$user = self::getUser( 'EditEntityTestUser1' );
		$otherUser = self::getUser( 'EditEntityTestUser2' );

		/* @var Item $item */
		$item = Item::newEmpty();
		$item->setId( new ItemId( 'Q17' ) );
		$item->setLabel('en', 'foo' );
		$repo->putEntity( $item, 10, 0, $user );

		$item = $item->copy();
		$item->setLabel( 'en', 'bar' );
		$repo->putEntity( $item, 11, 0, $otherUser );

		$item = $item->copy();
		$item->setLabel( 'de', 'bar' );
		$repo->putEntity( $item, 12, 0, $user );

		$item = $item->copy();
		$item->setLabel('en', 'test' );
		$item->setDescription( 'en', 'more testing' );
		$repo->putEntity( $item, 13, 0, $user );

		return $repo;
	}

	public function provideHasEditConflict() {
		/*
		 * Test Revisions:
		 * #0: label: array( 'en' => 'foo' );
		 * #1: label: array( 'en' => 'bar' ); // by other user
		 * #2: label: array( 'en' => 'bar', 'de' => 'bar' );
		 * #3: label: array( 'en' => 'test', 'de' => 'bar' ), description: array( 'en' => 'more testing' );
		*/

		return array(
			array( // #0: case I: no base rev given.
				null,  // input data
				0,  // base rev
				false, // expected conflict
				false, // expected fix
			),
			array( // #1: case II: base rev == current
				null,  // input data
				13,     // base rev
				false, // expected conflict
				false, // expected fix
			),
			array( // #2: case IIIa: user was last to edit
				array( // input data
					'label' => array( 'de' => 'yarrr' ),
				),
				12,     // base rev
				true,  // expected conflict
				true,  // expected fix
				array( // expected data
					'label' => array( 'en' => 'test', 'de' => 'yarrr' ),
				)
			),
			array( // #3: case IIIb: user was last to edit, but intoduces a new operand
				array( // input data
					'label' => array( 'de' => 'yarrr' ),
				),
				11,     // base rev
				true,  // expected conflict
				false, // expected failure, diff operand change
				null
			),
			array( // #4: case IV: patch applied
				array( // input data
					'label' => array( 'nl' => 'test', 'fr' => 'frrrrtt' ),
				),
				10,     // base rev
				true,  // expected conflict
				true,  // expected fix
				array( // expected data
					'label' => array( 'de' => 'bar', 'en' => 'test',
					                  'nl' => 'test', 'fr' => 'frrrrtt' ),
				)
			),
			array( // #5: case V: patch failed, expect a conflict
				array( // input data
					'label' => array( 'nl' => 'test', 'de' => 'bar' ),
				),
				10,     // base rev
				true,  // expected conflict
				false, // expected fix
				null   // expected data
			),
			array( // #6: case VI: patch is empty, keep current (not base)
				array( // input data
					'label' => array( 'en' => 'bar', 'de' => 'bar' ),
				),
				12,     // base rev
				true,  // expected conflict
				true,  // expected fix
				array( // expected data
					'label' => array( 'en' => 'test', 'de' => 'bar' ),
					'description' => array( 'en' => 'more testing' )
				)
			),
		);
	}

	/**
	 * @dataProvider provideHasEditConflict
	 */
	public function testHasEditConflict( $inputData, $baseRevisionId, $expectedConflict, $expectedFix, array $expectedData = null ) {
		$repo = $this->makeMockRepo();

		$entityId = new ItemId( 'Q17' );
		$revision = $repo->getEntityRevision( $entityId, $baseRevisionId );
		$entity = $revision->getEntity( $entityId );

		// NOTE: the user name must be the one used in makeMockRepo()
		$user = self::getUser( 'EditEntityTestUser1' );

		// change entity ----------------------------------
		if ( $inputData === null ) {
			$entity->clear();
		} else {
			if ( !empty( $inputData['label'] ) ) {
				foreach ( $inputData['label'] as $k => $v ) {
					$entity->setLabel( $k, $v );
				}
			}

			if ( !empty( $inputData['description'] ) ) {
				foreach ( $inputData['description'] as $k => $v ) {
					$entity->setDescription( $k, $v );
				}
			}

			if ( !empty( $inputData['aliases'] ) ) {
				foreach ( $inputData['aliases'] as $k => $v ) {
					$entity->setAliases( $k, $v );
				}
			}
		}

		// save entity ----------------------------------
		$editEntity = $this->makeEditEntity( $repo, $entity, $user, $baseRevisionId );

		$conflict = $editEntity->hasEditConflict();
		$this->assertEquals( $expectedConflict, $conflict, 'hasEditConflict()' );

		if ( $conflict ) {
			$fixed = $editEntity->fixEditConflict();
			$this->assertEquals( $expectedFix, $fixed, 'fixEditConflict()' );
		}

		if ( $expectedData !== null ) {
			$data = $this->fingerprintToPartialArray( $editEntity->getNewEntity()->getFingerprint() );

			foreach ( $expectedData as $key => $expectedValue ) {
				$actualValue = $data[$key];
				$this->assertArrayEquals( $expectedValue, $actualValue, false, true );
			}
		}
	}

	private function fingerprintToPartialArray( Fingerprint $fingerprint ) {
		return array(
			'label' => $fingerprint->getLabels()->toTextArray(),
			'description' => $fingerprint->getDescriptions()->toTextArray(),
		);
	}

	public static function provideAttemptSaveWithLateConflict() {
		return array(
			array( true, true ),
			array( false, false ),
		);
	}

	/**
	 * @dataProvider provideAttemptSaveWithLateConflict
	 */
	public function testAttemptSaveWithLateConflict( $baseRevId, $expectedConflict ) {
		$repo = $this->makeMockRepo();

		$user = self::getUser( 'EditEntityTestUser' );

		// create item
		$entity = Item::newEmpty();
		$entity->setLabel( 'en', 'Test' );

		$repo->putEntity( $entity, 0, 0, $user );

		// begin editing the entity
		$entity = $entity->copy();
		$entity->setLabel( 'en', 'Trust' );

		$editEntity = $this->makeEditEntity( $repo,  $entity, $user, $baseRevId );
		$editEntity->getLatestRevision(); // make sure EditEntity has page and revision

		$this->assertEquals( $baseRevId !== false, $editEntity->doesCheckForEditConflicts(), 'doesCheckForEditConflicts()' );

		// create independent Entity instance for the same entity, and modify and save it
		$entity2 = $entity->copy();
		$user2 = self::getUser( "EditEntityTestUser2" );

		$entity2->setLabel( 'en', 'Toast' );
		$repo->putEntity( $entity2, 0, 0, $user2 );

		// now try to save the original edit. The conflict should still be detected
		$token = $user->getEditToken();
		$status = $editEntity->attemptSave( "Testing", EDIT_UPDATE, $token );

		$id = $entity->getId()->__toString();

		if ( $status->isOK() ) {
			$statusMessage = "Status ($id): OK";
		} else {
			$statusMessage = "Status ($id): " . $status->getWikiText();
		}

		$this->assertNotEquals( $expectedConflict, $status->isOK(),
			"Saving should have failed late if and only if a base rev was provided.\n$statusMessage" );

		$this->assertEquals( $expectedConflict, $editEntity->hasError(),
			"Saving should have failed late if and only if a base rev was provided.\n$statusMessage" );

		$this->assertEquals( $expectedConflict, $status->hasMessage( 'edit-conflict' ),
			"Saving should have failed late if and only if a base rev was provided.\n$statusMessage" );

		$this->assertEquals( $expectedConflict, $editEntity->showErrorPage(),
			"If and only if there was an error, an error page should be shown.\n$statusMessage" );
	}

	public function testErrorPage_DoesNotDoubleEscapeHtmlCharacters() {
		$repo = $this->makeMockRepo();
		$permissions = array();
		$context = new RequestContext();
		// Can not reuse makeEditEntity because we need the access the context
		$editEntity = new EditEntity(
			$this->newTitleLookupMock(),
			$repo,
			$repo,
			$this->newEntityPermissionCheckerMock( $permissions ),
			Item::newEmpty(),
			self::getUser( 'EditEntityTestUser' ),
			false,
			$context
		);

		$editEntity->checkEditPermissions();
		$editEntity->showErrorPage();
		$html = $context->getOutput()->getHTML();

		$this->assertContains( '<li>', $html, 'Unescaped HTML' );
		$this->assertNotContains( '&amp;lt;', $html, 'No double escaping' );
	}

	public function dataCheckEditPermissions() {
		return array(
			array( #0: edit allowed for new item
				array( 'read' => true, 'edit' => true, 'createpage' => true ),
				false,
				true,
			),
			array( #3: edit not allowed for existing item
				array( 'read' => true, 'edit' => false ),
				true,
				false,
			),
		);
	}

	protected function prepareItemForPermissionCheck( User $user, MockRepository $repo, $create ) {
		$item = Item::newEmpty();

		if ( $create ) {
			$item->setLabel( 'de', 'Test' );
			$repo->putEntity( $item, 0, 0, $user );
		}

		return $item;
	}

	/**
	 * @dataProvider dataCheckEditPermissions
	 */
	public function testCheckEditPermissions( $permissions, $create, $expectedOK ) {
		$repo = $this->makeMockRepo();

		$user = self::getUser( "EditEntityTestUser" );
		$item = $this->prepareItemForPermissionCheck( $user, $repo, $create );

		$edit = $this->makeEditEntity( $repo, $item, $user, false, $permissions );
		$edit->checkEditPermissions();

		$this->assertEquals( $expectedOK, $edit->getStatus()->isOK() );
		$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::PERMISSION_ERROR ) );
	}

	/**
	 * @dataProvider dataCheckEditPermissions
	 */
	public function testAttemptSavePermissions( $permissions, $create, $expectedOK ) {
		$repo = $this->makeMockRepo();

		$user = self::getUser( "EditEntityTestUser" );
		$item = $this->prepareItemForPermissionCheck( $user, $repo, $create );

		$token = $user->getEditToken();
		$edit = $this->makeEditEntity( $repo, $item, $user, false, $permissions );

		$edit->attemptSave( "testing", ( $item->getId() === null ? EDIT_NEW : EDIT_UPDATE ), $token );

		$this->assertEquals( $expectedOK, $edit->getStatus()->isOK(), var_export( $edit->getStatus()->getErrorsArray(), true ) );
		$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::PERMISSION_ERROR ) );
	}

	/**
	 * Forces the group membership of the given user
	 *
	 * @param User $user
	 * @param array $groups
	 */
	protected function setUserGroups( User $user, array $groups ) {
		if ( $user->getId() === 0 ) {
			$user = User::createNew( $user->getName() );
		}

		$remove = array_diff( $user->getGroups(), $groups );
		$add = array_diff( $groups, $user->getGroups() );

		foreach ( $remove as $group ) {
			$user->removeGroup( $group );
		}

		foreach ( $add as $group ) {
			$user->addGroup( $group );
		}
	}

	public static function dataAttemptSaveRateLimit() {
		return array(

			array( // #0: no limits
				array(), // limits: none
				array(), // groups: none
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'bar', 'ok' => true ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'Bar', 'ok' => true ),
				)
			),

			array( // #1: limits bypassed with noratelimit permission
				array( // limits:
					'edit' => array(
						'user' => array( 1, 60 ), // one edit per minute
					)
				),
				array( // groups:
					'sysop' // assume sysop has the noratelimit permission, as per default
				),
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'bar', 'ok' => true ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'Bar', 'ok' => true ),
				)
			),

			array( // #2: per-group limit overrides with less restrictive limit
				array( // limits:
					'edit' => array(
						'user' => array( 1, 60 ), // one edit per minute
						'kittens' => array( 10, 60 ), // one edit per minute
					)
				),
				array( // groups:
					'kittens'
				),
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'bar', 'ok' => true ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'Bar', 'ok' => true ),
				)
			),

			array( // #3: edit limit applies
				array( // limits:
					'edit' => array(
						'user' => array( 1, 60 ), // one edit per minute
					),
				),
				array(), // groups: none
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => false ),
				)
			),

			array( // #4: edit limit also applies to creations
				array( // limits:
					'edit' => array(
						'user' => array( 1, 60 ), // one edit per minute
					),
					'create' => array(
						'user' => array( 10, 60 ), // ten creations per minute
					),
				),
				array(), // groups: none
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'bar', 'ok' => false ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => false ),
				)
			),

			array( // #5: creation limit applies in addition to edit limit
				array( // limits:
					'edit' => array(
						'user' => array( 10, 60 ), // ten edits per minute
					),
					'create' => array(
						'user' => array( 1, 60 ), // ...but only one creation
					),
				),
				array(), // groups: none
				array(  // edits:
					array( 'item' => 'foo', 'label' => 'foo', 'ok' => true ),
					array( 'item' => 'foo', 'label' => 'Foo', 'ok' => true ),
					array( 'item' => 'bar', 'label' => 'bar', 'ok' => false ),
				)
			)

		);
	}

	/**
	 * @dataProvider dataAttemptSaveRateLimit
	 */
	public function testAttemptSaveRateLimit( $limits, $groups, $edits ) {
		$repo = $this->makeMockRepo();

		$this->setMwGlobals(
			'wgRateLimits',
			$limits
		);

		// make sure we have a fresh, working cache
		$this->setMwGlobals(
			'wgMemc',
			new HashBagOStuff()
		);

		$user = self::getUser( "UserForTestAttemptSaveRateLimit" );
		$this->setUserGroups( $user, $groups );

		$items = array();

		foreach ( $edits as $e ) {
			$name = $e[ 'item' ];
			$label = $e[ 'label' ];
			$expectedOK = $e[ 'ok' ];

			if ( isset( $items[$name] ) ) {
				// re-use item
				$item = $items[$name];
			} else {
				// create item
				$item = Item::newEmpty();
				$items[$name] = $item;
			}

			$item->setLabel( 'en', $label );

			$edit = $this->makeEditEntity( $repo, $item, $user );
			$edit->attemptSave( "testing", ( $item->getId() === null ? EDIT_NEW : EDIT_UPDATE ), false );

			$this->assertEquals( $expectedOK, $edit->getStatus()->isOK(), var_export( $edit->getStatus()->getErrorsArray(), true ) );
			$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::RATE_LIMIT ) );
		}
	}

	public static function provideIsTokenOk() {
		return array(
			array( //0
				true, // use a newly generated valid token
				true, // should work
			),
			array( //1
				"xyz", // use an invalid token
				false, // should fail
			),
			array( //2
				"", // use an empty token
				false, // should fail
			),
			array( //3
				null, // use no token
				false, // should fail
			),
		);
	}

	/**
	 * @dataProvider provideIsTokenOk
	 */
	public function testIsTokenOk( $token, $shouldWork ) {
		$repo = $this->makeMockRepo();
		$user = self::getUser( "EditEntityTestUser" );

		$item = Item::newEmpty();
		$edit = $this->makeEditEntity( $repo, $item, $user );

		// check valid token --------------------
		if ( $token === true ) {
			$token = $user->getEditToken();
		}

		$this->assertEquals( $shouldWork, $edit->isTokenOK( $token ) );

		$this->assertEquals( $shouldWork, $edit->getStatus()->isOK() );
		$this->assertNotEquals( $shouldWork, $edit->hasError( EditEntity::TOKEN_ERROR ) );
		$this->assertNotEquals( $shouldWork, $edit->showErrorPage() );
	}

	public static function provideAttemptSaveWatch() {
		// $watchdefault, $watchcreations, $new, $watched, $watch, $expected

		return array(
			array( true, true, true, false, null, true ), // watch new
			array( true, true, true, false, false, false ), // override watch new

			array( true, true, false, false, null, true ), // watch edit
			array( true, true, false, false, false, false ), // override watch edit

			array( false, false, false, false, null, false ), // don't watch edit
			array( false, false, false, false, true, true ), // override don't watch edit

			array( false, false, false, true, null, true ), // watch watched
			array( false, false, false, true, false, false ), // override don't watch edit
		);
	}

	/**
	 * @dataProvider provideAttemptSaveWatch
	 */
	public function testAttemptSaveWatch( $watchdefault, $watchcreations, $new, $watched, $watch, $expected ) {
		$repo = $this->makeMockRepo();

		$user = self::getUser( "EditEntityTestUser2" );

		if ( $user->getId() === 0 ) {
			$user->addToDatabase();
		}

		$user->setOption( 'watchdefault', $watchdefault );
		$user->setOption( 'watchcreations', $watchcreations );

		$item = Item::newEmpty();
		$item->setLabel( "en", "Test" );

		if ( !$new ) {
			$repo->putEntity( $item ) ;
			$repo->updateWatchlist( $user, $item->getId(), $watched );
		}

		$edit = $this->makeEditEntity( $repo, $item, $user );
		$status = $edit->attemptSave( "testing", $new ? EDIT_NEW : EDIT_UPDATE, false, $watch );

		$this->assertTrue( $status->isOK(), "edit failed: " . $status->getWikiText() ); // sanity

		$this->assertEquals( $expected, $repo->isWatching( $user, $item->getId() ), "watched" );
	}

}
