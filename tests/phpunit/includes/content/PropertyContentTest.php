<?php

namespace Wikibase\Test;

use Wikibase\PropertyContent;
use Wikibase\StoreFactory;

/**
 * @covers Wikibase\PropertyContent
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseProperty
 * @group WikibaseRepo
 * @group WikibaseContent
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyContentTest extends EntityContentTest {

	/**
	 * @see EntityContentTest::getContentClass
	 */
	protected function getContentClass() {
		return '\Wikibase\PropertyContent';
	}

	/**
	 * @see EntityContentTest::newEmpty
	 */
	protected function newEmpty() {
		$content = PropertyContent::newEmpty();
		$content->getProperty()->setDataTypeId( 'string' );

		return $content;
	}

	public function testLabelUniquenessRestriction() {
		if ( wfGetDB( DB_MASTER )->getType() === 'mysql' ) {
			$this->markTestSkipped( 'Can\'t test uniqueness restriction on MySQL' );
		}

		StoreFactory::getStore()->getTermIndex()->clear();
		$prefix = get_class( $this ) . '/';

		$propertyContent = PropertyContent::newEmpty();
		$propertyContent->getProperty()->setLabel( 'en', $prefix . 'testLabelUniquenessRestriction' );
		$propertyContent->getProperty()->setLabel( 'de', $prefix . 'testLabelUniquenessRestriction' );
		$propertyContent->getProperty()->setDataTypeId( 'wikibase-item' );

		$status = $this->saveContent( $propertyContent, 'create property', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), "property creation should work" );

		$propertyContent1 = PropertyContent::newEmpty();
		$propertyContent1->getProperty()->setLabel( 'nl', $prefix . 'testLabelUniquenessRestriction' );
		$propertyContent1->getProperty()->setDataTypeId( 'wikibase-item' );

		$status = $this->saveContent( $propertyContent1, 'create property', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), "property creation should wok" );

		$propertyContent1->getProperty()->setLabel( 'en', $prefix . 'testLabelUniquenessRestriction' );

		$status = $this->saveContent( $propertyContent1, 'save property' );
		$this->assertFalse( $status->isOK(), "saving a property with duplicate label+lang should not work" );

		$errors = $status->getErrorsArray(); // Status::hasMessage is broken, see I52a468bc33f!
		$this->assertEquals( 'wikibase-validator-label-conflict', $errors[0][0] );
	}

	public function testLabelEntityIdRestriction() {
		StoreFactory::getStore()->getTermIndex()->clear();
		$prefix = get_class( $this ) . '/';

		$propertyContent = PropertyContent::newEmpty();
		$propertyContent->getProperty()->setLabel( 'en', $prefix . 'testLabelEntityIdRestriction' );
		$propertyContent->getProperty()->setDataTypeId( 'wikibase-item' );

		$status = $this->saveContent( $propertyContent, 'create property', null, EDIT_NEW );
		$this->assertTrue( $status->isOK(), "property creation should work" );

		// save a property
		$propertyContent->getProperty()->setLabel( 'de', $prefix . 'testLabelEntityIdRestriction' );

		$status = $this->saveContent( $propertyContent, 'save property' );
		$this->assertTrue( $status->isOK(), "saving a property should work" );

		// save a property with a valid item id as label
		$propertyContent->getProperty()->setLabel( 'fr', 'Q42' );

		$status = $this->saveContent( $propertyContent, 'save property' );
		$this->assertTrue( $status->isOK(), "saving a property with a valid item id as label should work" );
	}

	/**
	 * Injects a property data type into the generic entity data array.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function prepareEntityData( array $data ) {

		if ( !isset( $data['datatype'] ) ) {
			$data['datatype'] = 'string';
		}

		return $data;
	}
}
