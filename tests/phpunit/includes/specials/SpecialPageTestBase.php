<?php

namespace Wikibase\Test;

/**
 * Base class for testing special pages.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
abstract class SpecialPageTestBase extends \MediaWikiTestCase {

	public function testConstructor() {
		$this->assertInstanceOf( 'SpecialPage', new \SpecialItemDisambiguation() );
	}

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return \SpecialPage
	 */
	protected abstract function newSpecialPage();

	/**
	 * @param string      $sub The subpage parameter to call the page with
	 * @param \WebRequest $request Web request that may contain URL parameters, etc
	 *
	 * @return array array( String, \WebResponse ) containing the output generated
	 *         by the special page.
	 */
	protected function executeSpecialPage( $sub = '', \WebRequest $request = null ) {
		if ( !$request ) {
			$request = new \FauxRequest();
		}

		$response = $request->response();

		$page = $this->newSpecialPage();
		$page->getContext()->setRequest( $request );

		ob_start();
		$page->execute( $sub );

		if ( $page->getOutput()->isDisabled() ) {
			$text = ob_get_contents();
		} else {
			$text = $page->getOutput()->getHTML();
		}

		ob_end_clean();
		return array( $text, $response );
	}

}
