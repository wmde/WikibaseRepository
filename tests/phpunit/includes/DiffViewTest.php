<?php

namespace Wikibase\Test;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Wikibase\DiffView;

/**
 * @covers Wikibase\DiffView
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class DiffViewTest extends \PHPUnit_Framework_TestCase {

	public function diffOpProvider() {
		return array(
			'Empty' => array(
				'@^$@',
			),
			'Add operation inserted' => array(
				'@<ins\b[^>]*>NEW</ins>@',
				null,
				'NEW',
			),
			'Remove operation is deleted' => array(
				'@<del\b[^>]*>OLD</del>@',
				'OLD',
			),
			'Change operation is deleted and inserted' => array(
				'@<del\b[^>]*>OLD</del>.*<ins\b[^>]*>NEW</ins>@',
				'OLD',
				'NEW',
			),
			'Link is linked' => array(
				'@<a\b[^>]* href="[^"]*\bNEW"[^>]*>NEW</a>@',
				null,
				'NEW',
				'links/enwiki'
			),
			'Link has direction' => array(
				'@<a\b[^>]* hreflang="en" dir="auto"@',
				null,
				'NEW',
				'links/enwiki'
			),
		);
	}

	private function getDiffOps( $oldValue = null, $newValue = null ) {
		$diffOps = array();
		if ( $oldValue !== null && $newValue !== null ) {
			$diffOps['change'] = new DiffOpChange( $oldValue, $newValue );
		} else if ( $oldValue !== null ) {
			$diffOps['remove'] = new DiffOpRemove( $oldValue );
		} else if ( $newValue !== null ) {
			$diffOps['add'] = new DiffOpAdd( $newValue );
		}
		return $diffOps;
	}

	/**
	 * @dataProvider diffOpProvider
	 *
	 * @param string $pattern
	 * @param string|null $oldValue
	 * @param string|null $newValue
	 * @param string|string[] $path
	 */
	public function testGetHtml( $pattern, $oldValue = null, $newValue = null, $path = array() ) {
		if ( is_string( $path ) ) {
			$path = preg_split( '@\s*/\s*@', $path );
		}
		$diff = new Diff( $this->getDiffOps( $oldValue, $newValue ) );
		$siteStore = MockSiteStore::newFromTestSites();
		$diffView = new DiffView( $path, $diff, $siteStore );

		$html = $diffView->getHtml();

		$this->assertInternalType( 'string', $html );

		$pos = strpos( $html, '</tr><tr>' );
		if ( $pos !== false ) {
			$pos += 5;
			$header = substr( $html, 0, $pos );
			$html = substr( $html, $pos );

			$this->assertRegExp(
				'@^<tr><td\b[^>]* colspan="2"[^>]*>[^<]*</td><td\b[^>]* colspan="2"[^>]*>[^<]*</td></tr>$@',
				$header,
				'Diff table header line'
			);
		}

		$this->assertRegExp( $pattern, $html, 'Diff table content line' );
	}

}
