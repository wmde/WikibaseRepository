<?php

namespace Wikibase\Test;
use Wikibase\EntityHandler as EntityHandler;
use Wikibase\EntityContent as EntityContent;

/**
 *  Tests for the Wikibase\EntityContent class.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseEntity
 * @group WikibaseContent
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
abstract class EntityContentTest extends \MediaWikiTestCase {

	protected $permissions;
	protected $userGroups;

	function setUp() {
		global $wgGroupPermissions, $wgUser;

		parent::setUp();

		$this->permissions = $wgGroupPermissions;
		$this->userGroups = $wgUser->getGroups();

		\TestSites::insertIntoDb();
	}

	function tearDown() {
		global $wgGroupPermissions, $wgUser;

		$wgGroupPermissions = $this->permissions;

		$userGroups = $wgUser->getGroups();

		foreach ( array_diff( $this->userGroups, $userGroups ) as $group ) {
			$wgUser->addGroup( $group );
		}

		foreach ( array_diff( $userGroups, $this->userGroups ) as $group ) {
			$wgUser->removeGroup( $group );
		}

		$wgUser->getEffectiveGroups( true ); // recache

		parent::tearDown();
	}

	public function dataGetTextForSearchIndex() {
		return array( // runs
			array( // #0
				array( // data
					'label' => array( 'en' => 'Test', 'de' => 'Testen' ),
					'aliases' => array( 'en' => array( 'abc', 'cde' ), 'de' => array( 'xyz', 'uvw' ) )
				),
				array( // patterns
					'/^Test$/',
					'/^Testen$/',
					'/^abc$/',
					'/^cde$/',
					'/^uvw$/',
					'/^xyz$/',
					'/^(?!abcde).*$/',
				),
			),
		);
	}

	/**
	 * @since 0.1
	 *
	 * @return string
	 */
	protected abstract function getContentClass();

	/**
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return EntityContent
	 */
	protected function newFromArray( array $data ) {
		$class = $this->getContentClass();
		return $class::newFromArray( $data );
	}

	/**
	 * @since 0.1
	 *
	 * @return EntityContent
	 */
	protected function newEmpty() {
		$class = $this->getContentClass();
		return $class::newEmpty();
	}

	/**
	 * Tests @see Wikibase\Entity::getTextForSearchIndex
	 *
	 * @dataProvider dataGetTextForSearchIndex
	 *
	 * @param array $data
	 * @param array $patterns
	 */
	public function testGetTextForSearchIndex( array $data, array $patterns ) {
		$entity = $this->newFromArray( $data );
		$text = $entity->getTextForSearchIndex();

		foreach ( $patterns as $pattern ) {
			$this->assertRegExp( $pattern . 'm', $text );
		}
	}

	public function testSaveFlags() {
		$entityContent = $this->newEmpty();

		// try to create without flags
		$entityContent->getEntity()->setLabel( 'en', 'one' );
		$status = $entityContent->save( 'create item' );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue( $status->hasMessage( 'edit-gone-missing' ) );

		// try to create with EDIT_UPDATE flag
		$entityContent->getEntity()->setLabel( 'en', 'two' );
		$status = $entityContent->save( 'create item', null, EDIT_UPDATE );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue( $status->hasMessage( 'edit-gone-missing' ) );

		// try to create with EDIT_NEW flag
		$entityContent->getEntity()->setLabel( 'en', 'three' );
		$status = $entityContent->save( 'create item', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), "save failed" );

		// ok, the item exists now in the database.

		// try to save with EDIT_NEW flag
		$entityContent->getEntity()->setLabel( 'en', 'four' );
		$status = $entityContent->save( 'create item', null, EDIT_NEW );
		$this->assertFalse( $status->isOK(), "save should have failed" );
		$this->assertTrue( $status->hasMessage( 'edit-already-exists' ) );

		// try to save with EDIT_UPDATE flag
		$entityContent->getEntity()->setLabel( 'en', 'five' );
		$status = $entityContent->save( 'create item', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), "save failed" );

		// try to save without flags
		$entityContent->getEntity()->setLabel( 'en', 'six' );
		$status = $entityContent->save( 'create item' );
		$this->assertTrue( $status->isOK(), "save failed" );
	}

	public function testRepeatedSave() {
		$entityContent = $this->newEmpty();

		// create
		$entityContent->getEntity()->setLabel( 'en', "First" );
		$status = $entityContent->save( 'create item', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), "save failed" );
		$this->assertTrue( $status->isGood(), $status->getMessage() );

		// change
		$prev_id = $entityContent->getWikiPage()->getLatest();
		$entityContent->getEntity()->setLabel( 'en', "Second" );
		$status = $entityContent->save( 'modify item', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), "save failed" );
		$this->assertTrue( $status->isGood(), $status->getMessage() );
		$this->assertNotEquals( $prev_id, $entityContent->getWikiPage()->getLatest(), "revision ID should change on edit" );

		// change again
		$prev_id = $entityContent->getWikiPage()->getLatest();
		$entityContent->getEntity()->setLabel( 'en', "Third" );
		$status = $entityContent->save( 'modify item again', null, EDIT_UPDATE );
		$this->assertTrue( $status->isOK(), "save failed" );
		$this->assertTrue( $status->isGood(), $status->getMessage() );
		$this->assertNotEquals( $prev_id, $entityContent->getWikiPage()->getLatest(), "revision ID should change on edit" );

		// save unchanged
		if ( $this->getContentClass() === '\Wikibase\ItemContent' ) {
			$prev_id = $entityContent->getWikiPage()->getLatest();
			$status = $entityContent->save( 'save unmodified', null, EDIT_UPDATE );
			$this->assertTrue( $status->isOK(), "save failed" );
			$this->assertEquals( $prev_id, $entityContent->getWikiPage()->getLatest(), "revision ID should stay the same if no change was made" );
		}
		else {
			$this->markTestIncomplete( 'No change of ID for saving of same content should still be done for non-item entities' );
		}
	}

	public function dataCheckPermissions() {
		// FIXME: this is testing for some specific configuration and will break if the config is changed
		return array(
			array( #0: read allowed
				'read',
				'user',
				array( 'read' => true ),
				false,
				true,
			),
			array( #1: edit and createpage allowed for new item
				'edit',
				'user',
				array( 'read' => true, 'edit' => true, 'createpage' => true ),
				false,
				true,
			),
			array( #2: edit allowed but createpage not allowed for new item
				'edit',
				'user',
				array( 'read' => true, 'edit' => true, 'createpage' => false ),
				false,
				false,
			),
			array( #3: edit allowed but createpage not allowed for existing item
				'edit',
				'user',
				array( 'read' => true, 'edit' => true, 'createpage' => false ),
				true,
				true,
			),
			array( #4: edit not allowed for existing item
				'edit',
				'user',
				array( 'read' => true, 'edit' => false ),
				true,
				false,
			),
			array( #5: delete not allowed
				'delete',
				'user',
				array( 'read' => true, 'delete' => false ),
				false,
				false,
			),
		);
	}

	protected function prepareItemForPermissionCheck( $group, $permissions, $create ) {
		global $wgUser;

		$content = $this->newEmpty();

		if ( $create ) {
			$content->getEntity()->setLabel( 'de', 'Test' );
			$content->save( "testing", null, EDIT_NEW );
		}

		if ( !in_array( $group, $wgUser->getEffectiveGroups() ) ) {
			$wgUser->addGroup( $group );
		}

		if ( $permissions !== null ) {
			ApiModifyItemBase::applyPermissions( array(
				'*' => $permissions,
				'user' => $permissions,
				$group => $permissions,
			) );
		}

		return $content;
	}

	/**
	 * @dataProvider dataCheckPermissions
	 */
	public function testCheckPermission( $action, $group, $permissions, $create, $expectedOK ) {
		$content = $this->prepareItemForPermissionCheck( $group, $permissions, $create );

		$status = $content->checkPermission( $action );

		$this->assertEquals( $expectedOK, $status->isOK() );
	}

	/**
	 * @dataProvider dataCheckPermissions
	 */
	public function testUserCan( $action, $group, $permissions, $create, $expectedOK ) {
		$content = $this->prepareItemForPermissionCheck( $group, $permissions, $create );

		$status = $content->checkPermission( $action );

		$this->assertEquals( $expectedOK, $content->userCan( $action ) );
	}


	public function dataUserCanEdit() {
		return array(
			array( #0: edit and createpage allowed for new item
				array( 'read' => true, 'edit' => true, 'createpage' => true ),
				false,
				true,
			),
			array( #1: edit allowed but createpage not allowed for new item
				array( 'read' => true, 'edit' => true, 'createpage' => false ),
				false,
				false,
			),
			array( #2: edit allowed but createpage not allowed for existing item
				array( 'read' => true, 'edit' => true, 'createpage' => false ),
				true,
				true,
			),
			array( #3: edit not allowed for existing item
				array( 'read' => true, 'edit' => false ),
				true,
				false,
			),
		);
	}

	/**
	 * @dataProvider dataUserCanEdit
	 */
	public function testUserCanEdit( $permissions, $create, $expectedOK ) {
		$content = $this->prepareItemForPermissionCheck( 'user', $permissions, $create );

		$this->assertEquals( $expectedOK, $content->userCanEdit() );
	}

}
