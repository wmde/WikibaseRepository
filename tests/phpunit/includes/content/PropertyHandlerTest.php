<?php

namespace Wikibase\Test;

use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\PropertyContent;
use Wikibase\PropertyHandler;
use Wikibase\SettingsArray;

/**
 * @covers Wikibase\PropertyHandler
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseProperty
 * @group WikibaseEntity
 * @group WikibaseEntityHandler
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyHandlerTest extends EntityHandlerTest {

	/**
	 * @see EntityHandlerTest::getModelId
	 * @return string
	 */
	public function getModelId() {
		return CONTENT_MODEL_WIKIBASE_PROPERTY;
	}

	/**
	 * @see EntityHandlerTest::getClassName
	 * @return string
	 */
	public function getClassName() {
		return '\Wikibase\PropertyHandler';
	}

	/**
	 * @see EntityHandlerTest::contentProvider
	 */
	public function contentProvider() {
		$contents = parent::contentProvider();

		/**
		 * @var PropertyContent $content
		 */
		$content = clone $contents[1][0];
		// TODO: add some prop-specific stuff: $content->getProperty()->;
		$contents[] = array( $content );

		return $contents;
	}

	public function testGetTitleForId() {
		$handler = $this->getHandler();
		$id = new PropertyId( 'P123' );

		$title = $handler->getTitleForId( $id );
		$this->assertEquals( $id->getSerialization(), $title->getText() );
	}

	public function testGetIdForTitle() {
		$handler = $this->getHandler();
		$title = Title::makeTitle( $handler->getEntityNamespace(), 'P123' );

		$id = $handler->getIdForTitle( $title );
		$this->assertEquals( $title->getText(), $id->getSerialization() );
	}

	protected function newEntity( EntityId $id = null ) {
		if ( !$id ) {
			$id = new PropertyId( 'P7' );
		}

		$property = Property::newFromType( 'string' );
		$property->setId( $id );
		return $property;
	}

	public function entityIdProvider() {
		return array(
			array( 'P7' )
		);
	}

	/**
	 * @param SettingsArray $settings
	 *
	 * @return PropertyHandler
	 */
	protected function getHandler( SettingsArray $settings = null ) {
		$repo = $this->getRepo( $settings );
		return $repo->newPropertyHandler();
	}

}
