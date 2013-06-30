<?php

namespace Wikibase\Test;

/**
 * Tests for the SpecialMyLanguageFallbackChain class.
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
 * @since 0.4
 *
 * @ingroup WikibaseRepoTest
 * @ingroup Test
 *
 * @group Wikibase
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @licence GNU GPL v2+
 */
class SpecialMyLanguageFallbackChainTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return new \SpecialMyLanguageFallbackChain();
	}

	public function testExecute() {
		list( $output, ) = $this->executeSpecialPage( '' );
		$this->assertTrue( strpos( $output, 'en - ' ) !== false );
	}

}
