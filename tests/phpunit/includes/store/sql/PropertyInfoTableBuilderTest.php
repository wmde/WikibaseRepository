<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\PropertyInfoStore;
use Wikibase\PropertyInfoTable;
use Wikibase\PropertyInfoTableBuilder;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\PropertyInfoTableBuilder
 *
 * @license GPL 2+
 *
 * @group Wikibase
 * @group WikibaseStore
 * @group WikibasePropertyInfo
 * @group WikibaseRepo
 * @group Database
 * @group medium
 *
 * @author Daniel Kinzler
 */
class PropertyInfoTableBuilderTest extends \MediaWikiTestCase {

	private function initProperties() {
		$infos = array(
			array( PropertyInfoStore::KEY_DATA_TYPE => 'string', 'test' => 'one' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'string', 'test' => 'two' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'time', 'test' => 'three' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'time', 'test' => 'four' ),
			array( PropertyInfoStore::KEY_DATA_TYPE => 'string', 'test' => 'five' ),
		);

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$properties = array();

		foreach ( $infos as $info ) {
			$property = Property::newFromType( $info[PropertyInfoStore::KEY_DATA_TYPE] );
			$property->setDescription( 'en', $info['test'] );

			$revision = $store->saveEntity( $property, "test", $GLOBALS['wgUser'], EDIT_NEW );

			$id = $revision->getEntity()->getId()->getSerialization();
			$properties[$id] = $info;
		}

		return $properties;
	}

	private function resetPropertyInfoTable( PropertyInfoTable $table ) {
		$dbw = $table->getWriteConnection();
		$dbw->delete( 'wb_entity_per_page',  '*' );
	}

	public function testRebuildPropertyInfo() {
		$table = new PropertyInfoTable( false );

		$this->resetPropertyInfoTable( $table );
		$properties = $this->initProperties();
		$propertyIds = array_keys( $properties );

		// NOTE: We use the EntityStore from WikibaseRepo in initProperties,
		//       so we should also use the EntityLookup from WikibaseRepo.
		$entityLookup = WikibaseRepo::getDefaultInstance()->getEntityLookup( 'uncached' );
		$useRedirectTargetColumn = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'useRedirectTargetColumn' );

		$builder = new PropertyInfoTableBuilder( $table, $entityLookup, $useRedirectTargetColumn );
		$builder->setBatchSize( 3 );

		// rebuild all ----
		$builder->setFromId( 0 );
		$builder->setRebuildAll( true );

		$builder->rebuildPropertyInfo();

		$this->assertTableHasProperties( $properties, $table );

		// make table incomplete ----
		$propId1 = new PropertyId( $propertyIds[0] );
		$table->removePropertyInfo( $propId1 );

		// rebuild from offset, with no effect ----
		$builder->setFromId( $propId1->getNumericId() +1 );
		$builder->setRebuildAll( false );

		$builder->rebuildPropertyInfo();

		$info = $table->getPropertyInfo( $propId1 );
		$this->assertNull( $info, "rebuild missing from offset should have skipped this" );

		// rebuild all from offset, with no effect ----
		$builder->setFromId( $propId1->getNumericId() +1 );
		$builder->setRebuildAll( false );

		$builder->rebuildPropertyInfo();

		$info = $table->getPropertyInfo( $propId1 );
		$this->assertNull( $info, "rebuild all from offset should have skipped this" );

		// rebuild missing  ----
		$builder->setFromId( 0 );
		$builder->setRebuildAll( false );

		$builder->rebuildPropertyInfo();

		$this->assertTableHasProperties( $properties, $table );

		// rebuild again ----
		$builder->setFromId( 0 );
		$builder->setRebuildAll( false );

		$c = $builder->rebuildPropertyInfo();
		$this->assertEquals( 0, $c, "Thre should be nothing left to rebuild" );
	}

	private function assertTableHasProperties( array $properties, PropertyInfoTable $table ) {
		foreach ( $properties as $propId => $expected ) {
			$info = $table->getPropertyInfo( new PropertyId( $propId ) );
			$this->assertEquals( $expected[PropertyInfoStore::KEY_DATA_TYPE], $info[PropertyInfoStore::KEY_DATA_TYPE], "Property $propId" );
		}
	}

}
