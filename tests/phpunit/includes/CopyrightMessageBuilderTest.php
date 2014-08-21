<?php

namespace Wikibase\Test;

use Language;
use Wikibase\CopyrightMessageBuilder;

/**
 * @covers Wikibase\CopyrightMessageBuilder
 *
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class CopyrightMessageBuilderTest extends \MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'qqx' )
		) );
	}

	/**
	 * @dataProvider buildShortCopyrightWarningMessageProvider
	 */
	public function testBuildShortCopyrightWarningMessage( $expectedKey, $expectedParams,
		$rightsUrl, $rightsText
	) {
		$language = Language::factory( 'qqx' );
		$messageBuilder = new CopyrightMessageBuilder();
		$message = $messageBuilder->build( $rightsUrl, $rightsText, $language );

		$this->assertEquals( $expectedKey, $message->getKey() );
		$this->assertEquals( $expectedParams, $message->getParams() );
	}

	public function buildShortCopyrightWarningMessageProvider() {
		return array(
			array(
				'wikibase-shortcopyrightwarning',
				array(
					'(wikibase-save)',
					'(copyrightpage)',
					'[https://creativecommons.org Creative Commons Attribution-Share Alike 3.0]'
				),
				'https://creativecommons.org',
				'Creative Commons Attribution-Share Alike 3.0'
			)
		);
	}

}

