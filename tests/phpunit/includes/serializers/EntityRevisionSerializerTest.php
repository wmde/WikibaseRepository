<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityRevision;
use Wikibase\EntityTitleLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageWithConversion;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Property;
use Wikibase\Serializers\EntityRevisionSerializationOptions;
use Wikibase\Serializers\EntityRevisionSerializer;
use Wikibase\Lib\Serializers\EntitySerializationOptions;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\LanguageFallbackChainFactory;
use Title;

/**
 * @covers Wikibase\Serializers\EntityRevisionSerializer
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @group Database
 *        ^--- Needed because we use Title objects.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 * @author Daniel Kinzler
 */
class EntityRevisionSerializerTest extends SerializerBaseTest {

	/**
	 * @see SerializerBaseTest::getClass
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\Serializers\EntityRevisionSerializer';
	}

	public function getTitleForId( EntityId $id ) {
		$name = $id->getEntityType() . ':' . $id->getPrefixedId();
		return Title::makeTitle( NS_MAIN, $name );
	}

	public function formatId( EntityId $id ) {
		return $id->getPrefixedId();
	}

	/**
	 * @return EntityTitleLookup
	 */
	protected function getTitleLookupMock() {
		$titleLookup = $this->getMock( 'Wikibase\EntityTitleLookup' );

		$titleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( array( $this, 'getTitleForId' ) ) );

		return $titleLookup;
	}

	/**
	 * @return EntityIdFormatter
	 */
	protected function getIdFormatterMock() {
		$titleLookup = $this->getMockBuilder( 'Wikibase\Lib\EntityIdFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$titleLookup->expects( $this->any() )
			->method( 'format' )
			->will( $this->returnCallback( array( $this, 'formatId' ) ) );

		return $titleLookup;
	}

	/**
	 * @see SerializerBaseTest::SerializerObject
	 *
	 * @since 0.2
	 *
	 * @return EntityRevisionSerializer
	 */
	protected function getInstance() {
		return new EntityRevisionSerializer( $this->getTitleLookupMock() );
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	public function validProvider() {
		$entitySerializerOptions = new EntitySerializationOptions( $this->getIdFormatterMock() );

		$entityContentSerializerOptions =
			new EntityRevisionSerializationOptions( $entitySerializerOptions );

		$entity = Property::newEmpty();
		$entity->setId( new PropertyId( 'P652320' ) );
		$entity->setDataTypeId( 'foo' );

		$expectedEntityPageTitle = $this->getTitleForId( $entity->getId() );

		$entityRevision = new EntityRevision( $entity, 123456789, '20130102030405' );

		$validArgs[] = array(
			$entityRevision,
			array(
				'title' => $expectedEntityPageTitle->getPrefixedText(),
				'revision' => '',
				'content' => array(
					'id' => 'P652320',
					'type' => $entity->getType(),
					'datatype' => 'foo'
				)
			),
			$entityContentSerializerOptions
		);

		return $validArgs;
	}

	/**
	 * @since 0.5
	 */
	public function testNewForFrontendStore() {
		$titleLookup = $this->getTitleLookupMock();

		$fallbackChain = new LanguageFallbackChain( array(
			LanguageWithConversion::factory( 'en' )
		) );

		$serializer = EntityRevisionSerializer::newForFrontendStore(
			$titleLookup,
			'en',
			$fallbackChain
		);

		$this->assertInstanceOf( $this->getClass(), $serializer );
	}
}
