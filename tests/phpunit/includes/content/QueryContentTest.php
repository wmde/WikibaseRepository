<?php

namespace Wikibase\Test;

/**
 * Tests for the Wikibase\QueryContent class.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseQuery
 * @group WikibaseRepo
 * @group WikibaseContent
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class QueryContentTest extends EntityContentTest {

	/**
	 * @see EntityContentTest::getContentClass
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	protected function getContentClass() {
		return '\Wikibase\QueryContent';
	}

}
