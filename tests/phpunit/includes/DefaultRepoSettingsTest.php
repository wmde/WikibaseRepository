<?php

namespace Wikibase\Repo\Tests;

use Wikibase\SettingsArray;

/**
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gamil.com >
 */
class DefaultRepoSettingsTest extends \PHPUnit_Framework_TestCase {

	public function testDefaultTransformLegacyFormatOnExportSetting() {
		$defaultSettings = require __DIR__ . '/../../../config/Wikibase.default.php';
		$settings = $this->newSettingsArray( $defaultSettings );

		$this->assertTrue( $settings->getSetting( 'transformLegacyFormatOnExport' ) );
	}

	public function testDefaultTransformLegacyFormatOnExport_WithInternalSerializerSet() {
		$nonDefaultSettings = require __DIR__ . '/../../../config/Wikibase.default.php';

		$serializerClass = 'Wikibase\Lib\Serializers\LegacyInternalEntitySerializer';
		$nonDefaultSettings['internalEntitySerializerClass'] = $serializerClass;

		$settings = $this->newSettingsArray( $nonDefaultSettings );

		$this->assertFalse( $settings->getSetting( 'transformLegacyFormatOnExport' ) );
	}

	/**
	 * @param mixed[] $settings
	 *
	 * @return SettingsArray
	 */
	private function newSettingsArray( array $settings ) {
		$settingsArray = new SettingsArray();

		foreach( $settings as $setting => $value ) {
			$settingsArray->setSetting( $setting, $value );
		}

		return $settingsArray;
	}

}
