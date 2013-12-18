<?php

namespace Wikibase\Test;

use ContentHandler;
use Language;
use Revision;
use Title;
use Wikibase\Entity;
use Wikibase\EntityContentFactory;
use Wikibase\EntityFactory;
use Wikibase\EntityHandler;
use Wikibase\EntityContent;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\EntityHandler
 *
 * @since 0.1
 *
 * @group Wikibase
 * @group WikibaseEntity
 * @group WikibaseEntityHandler
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
abstract class EntityHandlerTest extends \MediaWikiTestCase {

	abstract public function getModelId();

	abstract public function getClassName();

	/**
	 * Returns instances of the EntityHandler deriving class.
	 * @return array
	 */
	public function instanceProvider() {
		$class = $this->getClassName();
		return array(
			array( new $class ),
			array( $this->getHandler() ),
		);
	}

	/**
	 * @return EntityHandler
	 */
	protected function getHandler() {
		return ContentHandler::getForModelID( $this->getModelId() );
	}

	/**
	 * @return Entity
	 */
	protected function newEntity() {
		$handler = $this->getHandler();
		return EntityFactory::singleton()->newEmpty( $handler->getEntityType() );
	}

	/**
	 * Returns EntityContents that can be handled by the EntityHandler deriving class.
	 * @return array
	 */
	public function contentProvider() {
		/**
		 * @var EntityContent $content
		 */
		$content = $this->getHandler()->makeEmptyContent();
		$content->getEntity()->addAliases( 'en', array( 'foo' ) );
		$content->getEntity()->setDescription( 'de', 'foobar' );
		$content->getEntity()->setDescription( 'en', 'baz' );
		$content->getEntity()->setLabel( 'nl', 'o_O' );

		return array(
			array( $this->getHandler()->makeEmptyContent() ),
			array( $content ),
		);
	}

	/**
	 * @dataProvider instanceProvider
	 * @param \Wikibase\EntityHandler $entityHandler
	 */
	public function testGetModelName( EntityHandler $entityHandler ) {
		$this->assertEquals( $this->getModelId(), $entityHandler->getModelID() );
		$this->assertInstanceOf( '\ContentHandler', $entityHandler );
		$this->assertInstanceOf( $this->getClassName(), $entityHandler );
	}


	/**
	 * @dataProvider instanceProvider
	 * @param \Wikibase\EntityHandler $entityHandler
	 */
	public function testGetSpecialPageForCreation( EntityHandler $entityHandler ) {
		$specialPageName = $entityHandler->getSpecialPageForCreation();
		$this->assertTrue( $specialPageName === null || is_string( $specialPageName ) );
	}

	/**
	 * @dataProvider contentProvider
	 * @param EntityContent $content
	 */
	public function testSerialization( EntityContent $content ) {
		$handler = $this->getHandler();

		foreach ( array( CONTENT_FORMAT_JSON,  CONTENT_FORMAT_SERIALIZED ) as $format ) {
			$this->assertTrue( $content->equals(
				$handler->unserializeContent( $handler->serializeContent( $content, $format ), $format )
			) );
		}
	}

	public function testCanBeUsedOn() {
		$handler = $this->getHandler();

		$this->assertTrue( $handler->canBeUsedOn( Title::makeTitle( $handler->getEntityNamespace(), "1234" ) ),
							'It should be possible to create this kind of entity in the respective entity namespace!'
						);

		$this->assertFalse( $handler->canBeUsedOn( Title::makeTitle( NS_MEDIAWIKI, "Foo" ) ),
							'It should be impossible to create an entity outside the respective entity namespace!'
						);
	}

	public function testGetPageLanguage() {
		global $wgContLang;

		$handler = $this->getHandler();
		$title = Title::makeTitle( $handler->getEntityNamespace(), "1234567" );

		//NOTE: currently, this tests whether getPageLanguage will always return the content language, even
		//      if the user language is different. It's unclear whether this is actually the desired behavior,
		//      since Wikibase Entities are inherently multilingual, so they have no actual "page language".

		// test whatever is there
		$this->assertEquals( $wgContLang->getCode(), $handler->getPageLanguage( $title )->getCode() );

		// test fr
		$this->setMwGlobals( 'wgLang', Language::factory( "fr" ) );
		$handler = $this->getHandler();
		$this->assertEquals( $wgContLang->getCode(), $handler->getPageLanguage( $title )->getCode() );

		// test nl
		$this->setMwGlobals( 'wgLang', Language::factory( "nl" ) );
		$this->setMwGlobals( 'wgContLang', Language::factory( "fr" ) );
		$handler = $this->getHandler();
		$this->assertEquals( $wgContLang->getCode(), $handler->getPageLanguage( $title )->getCode() );
	}

	public function testGetPageViewLanguage() {
		global $wgLang;

		$handler = $this->getHandler();
		$title = \Title::makeTitle( $handler->getEntityNamespace(), "1234567" );

		//NOTE: we expect getPageViewLanguage to return the user language, because Wikibase Entities
		//      are always shown in the user language.

		// test whatever is there
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );

		// test fr
		$this->setMwGlobals( 'wgLang', Language::factory( "fr" ) );
		$handler = $this->getHandler();
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );

		// test nl
		$this->setMwGlobals( 'wgLang', Language::factory( "nl" ) );
		$handler = $this->getHandler();
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );
	}

	public function testLocalizedModelName() {
		$name = ContentHandler::getLocalizedName( $this->getModelId() );

		$this->assertNotEquals( $this->getModelId(), $name, "localization of model name" );
	}

	protected function fakeRevision( Entity $entity, $id = 0 ) {
		$content = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->newFromEntity( $entity );

		$revision = new Revision( array(
			'id' => $id,
			'page' => $id,
			'content' => $content,
		) );

		return $revision;
	}

	public function provideGetUndoContent() {
		$e1 = $this->newEntity();
		$r1 = $this->fakeRevision( $e1, 1 );

		$e2 = $e1->copy();
		$e2->setLabel( 'en', 'Foo' );
		$r2 = $this->fakeRevision( $e2, 2 );

		$e3 = $e2->copy();
		$e3->setLabel( 'de', 'Fuh' );
		$r3 = $this->fakeRevision( $e3, 3 );

		$e4 = $e3->copy();
		$e4->setLabel( 'fr', 'Fu' );
		$r4 = $this->fakeRevision( $e4, 4 );

		$e5 = $e4->copy();
		$e5->setLabel( 'en', 'F00' );
		$r5 = $this->fakeRevision( $e5, 5 );

		$e5u4 = $e5->copy();
		$e5u4->removeLabel( 'fr' );

		$e5u4u3 = $e5u4->copy();
		$e5u4u3->removeLabel( 'de' );

		return array(
			array( $r5, $r5, $r4, $e4, "undo last edit" ),
			array( $r5, $r4, $r3, $e5u4, "undo previous edit" ),

			array( $r5, $r5, $r3, $e3, "undo last two edits" ),
			array( $r5, $r4, $r2, $e5u4u3, "undo past two edits" ),

			array( $r5, $r2, $r1, null, "undo conflicting edit" ),
			array( $r5, $r3, $r1, null, "undo two edits with conflict" ),
		);
	}

	/**
	 * @dataProvider provideGetUndoContent
	 *
	 * @param Revision      $latestRevision
	 * @param Revision      $newerRevision
	 * @param Revision      $olderRevision
	 * @param EntityContent $expected
	 * @param string        $message
	 */
	public function testGetUndoContent(
		Revision $latestRevision,
		Revision $newerRevision,
		Revision $olderRevision,
		Entity $expected = null,
		$message ) {

		$handler = $this->getHandler();
		$undo = $handler->getUndoContent( $latestRevision, $newerRevision, $olderRevision );

		if ( $expected ) {
			$this->assertInstanceOf( 'Wikibase\EntityContent', $undo, $message );
			$this->assertEquals( $expected->toArray(), $undo->getEntity()->toArray(), $message );
		} else {
			$this->assertFalse( $undo, $message );
		}
	}

	public function testGetEntityType() {
		$handler = $this->getHandler();
		$content = $handler->makeEmptyContent();
		$entity = $content->getEntity();

		$this->assertEquals( $entity->getType(), $handler->getEntityType() );
	}

	public function testMakeEntityContent() {
		$entity = $this->newEntity();

		$handler = $this->getHandler();
		$content = $handler->makeEntityContent( $entity );

		$this->assertEquals( $this->getModelId(), $content->getModel() );
		$this->assertSame( $entity, $content->getEntity() );
	}

	public function testMakeEmptyContent() {
		$handler = $this->getHandler();
		$content = $handler->makeEmptyContent();

		$this->assertEquals( $this->getModelId(), $content->getModel() );
	}

}
