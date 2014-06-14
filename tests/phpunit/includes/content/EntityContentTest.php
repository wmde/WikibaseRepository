<?php

namespace Wikibase\Test;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use IContextSource;
use ParserOptions;
use RequestContext;
use Title;
use Wikibase\DataModel\Claim\Statement;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityDiff;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\EntityContent;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageWithConversion;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\Content\EntityContentDiff;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;

/**
 * @covers Wikibase\EntityContent
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
abstract class EntityContentTest extends \MediaWikiTestCase {

	private $originalGroupPermissions;
	private $originalUser;

	/**
	 * @var EntityStore
	 */
	private $entityStore;

	function setUp() {
		global $wgGroupPermissions, $wgUser;

		parent::setUp();

		$this->originalGroupPermissions = $wgGroupPermissions;
		$this->originalUser = $wgUser;

		$this->entityStore = WikibaseRepo::getDefaultInstance()->getEntityStore();
	}

	function tearDown() {
		global $wgGroupPermissions;
		global $wgUser;

		$wgGroupPermissions = $this->originalGroupPermissions;

		if ( $this->originalUser ) { // should not be null, but sometimes, it is
			$wgUser = $this->originalUser;
		}

		if ( $wgUser ) { // should not be null, but sometimes, it is
			// reset rights cache
			$wgUser->addGroup( "dummy" );
			$wgUser->removeGroup( "dummy" );
		}

		parent::tearDown();
	}

	/**
	 * @return string
	 */
	protected abstract function getContentClass();

	/**
	 * @param array $data
	 *
	 * @return EntityContent
	 */
	protected function newFromArray( array $data ) {
		$deserializer = WikibaseRepo::getDefaultInstance()->newInternalDeserializerFactory()->newEntityDeserializer();
		$entity = $deserializer->deserialize( $data );
		$class = $this->getContentClass();
		return new $class( $entity );
	}

	/**
	 * @return EntityContent
	 */
	protected function newEmpty() {
		$class = $this->getContentClass();
		return $class::newEmpty();
	}

	/**
	 * Tests @see Wikibase\Entity::getTextForSearchIndex
	 *
	 * @dataProvider getTextForSearchIndexProvider
	 *
	 * @param EntityContent $entityContent
	 * @param string $pattern
	 */
	public function testGetTextForSearchIndex( EntityContent $entityContent, $pattern ) {
		$text = $entityContent->getTextForSearchIndex();
		$this->assertRegExp( $pattern . 'm', $text );
	}

	public function getTextForSearchIndexProvider() {
		$entityContent = $this->newEmpty();
		$entityContent->getEntity()->setLabel( 'en', "cake" );

		return array(
			array( $entityContent, '/^cake$/' )
		);
	}

	/**
	 * Prepares entity data from test cases for use in a new EntityContent.
	 * This allows subclasses to inject required fields into the entity data array.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function prepareEntityData( array $data ) {
		return $data;
	}

	public function testGetParserOutput() {
		$content = $this->newEmpty();

		//@todo: Use a fake ID, no need to hit the database once we
		//       got rid of the rest of the storage logic.
		$this->entityStore->assignFreshId( $content->getEntity() );

		$title = Title::newFromText( 'Foo' );
		$parserOutput = $content->getParserOutput( $title );

		$this->assertInstanceOf( '\ParserOutput', $parserOutput );
		$this->assertEquals( EntityContent::STATUS_EMPTY, $parserOutput->getProperty( 'wb-status' ) );
	}

	public function providePageProperties() {
		$cases = array();

		$cases['empty'] = array(
			$this->newEmpty(),
			array( 'wb-status' => EntityContent::STATUS_EMPTY, 'wb-claims' => 0 )
		);

		$contentWithLabel = $this->newEmpty();
		$contentWithLabel->getEntity()->setLabel( 'en', 'Foo' );

		$cases['labels'] = array(
			$contentWithLabel,
			array( 'wb-status' => EntityContent::STATUS_STUB, 'wb-claims' => 0 )
		);

		$contentWithClaim = $this->newEmpty();
		$claim = new Statement( new PropertyNoValueSnak( 83 ) );
		$claim->setGuid( '$testing$' );
		$contentWithClaim->getEntity()->addClaim( $claim );

		$cases['claims'] = array(
			$contentWithClaim,
			array( 'wb-claims' => 1 )
		);

		return $cases;
	}

	/**
	 * @dataProvider providePageProperties
	 */
	public function testPageProperties( EntityContent $content, array $expectedProps ) {
		$title = \Title::newFromText( 'Foo' );
		$parserOutput = $content->getParserOutput( $title, null, null, false );

		foreach ( $expectedProps as $name => $expected ) {
			$actual = $parserOutput->getProperty( $name );
			$this->assertEquals( $expected, $actual, "page property $name");
		}
	}

	public function provideGetEntityStatus() {
		$contentWithLabel = $this->newEmpty();
		$contentWithLabel->getEntity()->setLabel( 'de', 'xyz' );

		$contentWithClaim = $this->newEmpty();
		$claim = new Statement( new PropertyNoValueSnak( 83 ) );
		$claim->setGuid( '$testing$' );
		$contentWithClaim->getEntity()->addClaim( $claim );

		return array(
			'empty' => array(
				$this->newEmpty(),
				EntityContent::STATUS_EMPTY
			),
			'labels' => array(
				$contentWithLabel,
				EntityContent::STATUS_STUB
			),
			'claims' => array(
				$contentWithClaim,
				EntityContent::STATUS_NONE
			),
		);
	}

	/**
	 * @dataProvider provideGetEntityStatus
	 */
	public function testGetEntityStatus( EntityContent $content, $status ) {
		$actual = $content->getEntityStatus();

		$this->assertEquals( $status, $actual );
	}

	public function provideGetEntityPageProperties() {
		return array(
			'empty' => array(
				array(),
				array(
					'wb-status' => EntityContent::STATUS_EMPTY,
					'wb-claims' => 0,
				)
			),

			'labels' => array(
				array( 'label' => array( 'de' => 'xyz' ) ),
				array(
					'wb-status' => EntityContent::STATUS_STUB,
					'wb-claims' => 0,
				)
			),
		);
	}

	/**
	 * @dataProvider provideGetEntityPageProperties
	 */
	public function testGetEntityPageProperties( array $entityData, $pageProps ) {
		$content = $this->newFromArray( $this->prepareEntityData( $entityData ) );
		$actual = $content->getEntityPageProperties();

		foreach ( $pageProps as $key => $value ) {
			$this->assertArrayHasKey( $key, $actual );
			$this->assertEquals( $value, $actual[$key], $key );
		}

		$this->assertArrayEquals( array_keys( $pageProps ), array_keys( $actual ) );
	}

	public function dataGetEntityView() {
		$context = new RequestContext();
		$context->setLanguage( 'de' );

		$options = new ParserOptions();
		$options->setUserLang( 'nl' );

		$fallbackChain = new LanguageFallbackChain( array(
			LanguageWithConversion::factory( $context->getLanguage() )
		) );

		return array(
			array( $context, null, null ),
			array( null, $options, null ),
			array( $context, $options, null ),

			array( $context, null, $fallbackChain ),
			array( null, $options, $fallbackChain ),
			array( $context, $options, $fallbackChain ),
		);
	}

	/**
	 * @dataProvider dataGetEntityView
	 *
	 * @param IContextSource $context
	 * @param ParserOptions $parserOptions
	 * @param LanguageFallbackChain $fallbackChain
	 */
	public function testGetEntityView(
		IContextSource $context = null,
		ParserOptions $parserOptions = null,
		LanguageFallbackChain $fallbackChain = null
	) {
		$content = $this->newEmpty();
		$view = $content->getEntityView( $context, $parserOptions, $fallbackChain );

		$this->assertInstanceOf( 'Wikibase\EntityView', $view );

		if ( $parserOptions ) {
			// NOTE: the view must be using the language from the parser options.
			$this->assertEquals( $view->getLanguage()->getCode(), $parserOptions->getUserLang() );
		} elseif ( $content ) {
			$this->assertEquals( $view->getLanguage()->getCode(), $context->getLanguage()->getCode() );
		}
	}

	public function diffProvider() {
		$empty = $this->newEmpty();

		$spam = $this->newEmpty();
		$spam->getEntity()->setLabel( 'en', 'Spam' );

		$ham = $this->newEmpty();
		$ham->getEntity()->setLabel( 'en', 'Ham' );

		$spamToHam = new DiffOpChange( 'Spam', 'Ham' );
		$spamToHamDiff = new EntityDiff( array(
			'label' => new Diff( array( 'en' => $spamToHam ) ),
		) );

		return array(
			'empty' => array( $empty, $empty, new EntityContentDiff( new EntityDiff(), new Diff() ) ),
			'same' => array( $ham, $ham, new EntityContentDiff(  new EntityDiff(), new Diff()  ) ),
			'spam to ham' => array( $spam, $ham, new EntityContentDiff( $spamToHamDiff, new Diff() ) ),
		);
	}

	/**
	 * @dataProvider diffProvider
	 *
	 * @param EntityContent $a
	 * @param EntityContent $b
	 * @param EntityContentDiff $expected
	 */
	public function testGetDiff( EntityContent $a, EntityContent $b, EntityContentDiff $expected ) {
		$actual = $a->getDiff( $b );

		$expectedOps = $expected->getOperations();
		$actualOps = $actual->getOperations();

		// HACK: ItemDiff always sets this, even if it's empty. Ignore.
		if ( isset( $actualOps['claim'] ) && $actualOps['claim']->isEmpty() ) {
			unset( $actualOps['claim'] );
		}

		$this->assertArrayEquals( $expectedOps, $actualOps, true );
	}

	public function patchedCopyProvider() {
		$spam = $this->newEmpty();
		$spam->getEntity()->setLabel( 'en', 'Spam' );

		$ham = $this->newEmpty();
		$ham->getEntity()->setLabel( 'en', 'Ham' );

		$spamToHam = new DiffOpChange( 'Spam', 'Ham' );
		$spamToHamDiff = new EntityDiff( array(
			'label' => new Diff( array( 'en' => $spamToHam ) ),
		) );

		return array(
			'empty' => array( $spam, new EntityContentDiff( new EntityDiff(), new Diff() ), $spam ),
			'spam to ham' => array( $spam, new EntityContentDiff( $spamToHamDiff, new Diff() ), $ham ),
		);
	}

	/**
	 * @dataProvider patchedCopyProvider
	 *
	 * @param EntityContent $base
	 * @param EntityContentDiff $patch
	 * @param EntityContent $expected
	 */
	public function testGetPatchedCopy( EntityContent $base, EntityContentDiff $patch, EntityContent $expected ) {
		$actual = $base->getPatchedCopy( $patch );

		$this->assertTrue( $expected->equals( $actual ), 'equals()' );
	}

	private function createTitleForEntity( Entity $entity ) {
		// NOTE: needs database access
		$this->entityStore->assignFreshId( $entity );
		$titleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();
		$title = $titleLookup->getTitleForId( $entity->getId() );

		if ( !$title->exists() ) {
			$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
			$store->saveEntity( $entity, 'test', $GLOBALS['wgUser'] );

			// $title lies, make a new one
			$title = Title::makeTitleSafe( $title->getNamespace(), $title->getText() );
		}

		// sanity check - page must exist now
		$this->assertGreaterThan( 0, $title->getArticleID(), 'sanity check: getArticleID()' );
		$this->assertTrue( $title->exists(), 'sanity check: exists()' );

		return $title;
	}

	public function testGetSecondaryDataUpdates() {
		$entityContent = $this->newEmpty();
		$title = $this->createTitleForEntity( $entityContent->getEntity() );

		// NOTE: $title->exists() must be true.
		$updates = $entityContent->getSecondaryDataUpdates( $title );

		$this->assertDataUpdates( $updates );
	}

	public function testGetDeletionUpdates() {
		$entityContent = $this->newEmpty();
		$title = $this->createTitleForEntity( $entityContent->getEntity() );

		$updates = $entityContent->getDeletionUpdates( new WikiPage( $title ) );

		$this->assertDataUpdates( $updates );
	}

	private function assertDataUpdates( $updates ) {
		$this->assertInternalType( 'array', $updates );
		$this->assertContainsOnlyInstancesOf( 'DataUpdate', $updates );
	}

}
