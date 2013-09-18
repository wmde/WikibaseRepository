<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Serializers\FetchedEntityContentSerializer;
use Wikibase\Serializers\FetchedEntityContentSerializationOptions;
use Wikibase\Lib\Serializers\EntitySerializationOptions;
use Wikibase\PropertyContent;
use Wikibase\PropertyHandler;
use Wikibase\Repo\WikibaseRepo;
use Title;

/**
 * @covers Wikibase\Serializers\FetchedEntityContentSerializer
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @group Database
 *        ^--- Needed because FetchedEntityContentSerializer uses WikiPage.
 *             Also because the test uses Title.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 */
class FetchedEntityContentSerializerTest extends SerializerBaseTest {
	/**
	 * @see SerializerBaseTest::getClass
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\Serializers\FetchedEntityContentSerializer';
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	public function validProvider() {
		$entityContent = PropertyContent::newEmpty();
		$entitySerializerOptions = new EntitySerializationOptions(
			WikibaseRepo::getDefaultInstance()->getIdFormatter() );
		$entityContentSerializerOptions =
			new FetchedEntityContentSerializationOptions( $entitySerializerOptions );

		$entity = $entityContent->getEntity();
		$entity->setId( new PropertyId( 'P652320' ) );
		$entity->setDataTypeId( 'foo' );

		$propertyHandler = new PropertyHandler();
		$expectedEntityPageTitle =  Title::makeTitle(
			$propertyHandler->getEntityNamespace(), 'P652320' );

		$validArgs[] = array(
			$entityContent,
			array(
				'title' => $expectedEntityPageTitle->getPrefixedText(),
				'revision' => '',
				'content' => array(
					'id' => 'P652320',
					'type' => $propertyHandler->getEntityType(),
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
		$serializer = FetchedEntityContentSerializer::newForFrontendStore( 'en' );
		$this->assertInstanceOf( $this->getClass(), $serializer );
	}
}
