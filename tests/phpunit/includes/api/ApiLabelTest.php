<?php

namespace Wikibase\Test;
//use ApiLangAttributeBase;
use \Wikibase\Settings as Settings;

/**
 * Tests for the ApiWikibaseSetLabel API module.
 *
 * The tests are using "Database" to get its own set of temporal tables.
 * This is nice so we avoid poisoning an existing database.
 *
 * The tests are using "medium" so they are able to run alittle longer before they are killed.
 * Without this they will be killed after 1 second, but the setup of the tables takes so long
 * time that the first few tests get killed.
 *
 * The tests are doing some assumptions on the id numbers. If the database isn't empty when
 * when its filled with test items the ids will most likely get out of sync and the tests will
 * fail. It seems impossible to store the item ids back somehow and at the same time not being
 * dependant on some magically correct solution. That is we could use GetItemId but then we
 * would imply that this module in fact is correct.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group ApiLanguageAttributeTest
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
class ApiLabelTest extends ApiLangAttributeBase {

	public function paramProvider() {
		return array(
			// $handle, $langCode, $value, $exception
			array( 'Oslo', 'en', 'Oslo', null ),
			//array( 'Oslo', 'en', 'Oslo', 'UsageException' ),
			//array( 'Oslo', 'en', 'Bergen', null ),
			array( 'Oslo', 'en', '', null ),
		);
	}

	/**
	 * @dataProvider paramProvider
	 */
	public function testLanguageAttribute( $handle, $langCode, $value, $exception = null ) {
		$this->doLanguageAttribute( $handle, 'wbsetlabel', 'label', $langCode, $value, $exception );
		$id = $this->getItemId( $handle );
	}

}
