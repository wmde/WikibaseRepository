<?php

namespace Wikibase\Test;

use Diff\Comparer\ComparableComparer;
use Diff\Diff;
use Diff\DiffOpAdd;
use Diff\DiffOpChange;
use Diff\DiffOpRemove;
use Diff\OrderedListDiffer;
use Wikibase\Claim;
use Wikibase\ClaimDiffer;
use Wikibase\ClaimDifference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\PropertyNoValueSnak;
use Wikibase\SnakList;
use Wikibase\Statement;

/**
 * @covers Wikibase\ClaimDiffer
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseClaim
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ClaimDifferTest extends \MediaWikiTestCase {

	public function diffClaimsProvider() {
		$argLists = array();

		$noValueForP42 = new Statement( new PropertyNoValueSnak( 42 ) );
		$noValueForP43 = new Statement( new PropertyNoValueSnak( 43 ) );

		$argLists[] = array(
			$noValueForP42,
			$noValueForP42,
			new ClaimDifference()
		);

		$argLists[] = array(
			$noValueForP42,
			$noValueForP43,
			new ClaimDifference( new DiffOpChange( new PropertyNoValueSnak( 42 ), new PropertyNoValueSnak( 43 ) ) )
		);

		$qualifiers = new SnakList( array( new PropertyNoValueSnak( 1 ) ) );
		$withQualifiers = clone $noValueForP42;
		$withQualifiers->setQualifiers( $qualifiers );

		$argLists[] = array(
			$noValueForP42,
			$withQualifiers,
			new ClaimDifference(
				null,
				new Diff( array(
					new DiffOpAdd( new PropertyNoValueSnak( 1 ) )
				), false )
			)
		);

		$references = new ReferenceList( array( new PropertyNoValueSnak( 2 ) ) );
		$withReferences = clone $noValueForP42;
		$withReferences->setReferences( $references );

		$argLists[] = array(
			$noValueForP42,
			$withReferences,
			new ClaimDifference(
				null,
				null,
				new Diff( array(
					new DiffOpAdd( new PropertyNoValueSnak( 2 ) )
				), false )
			)
		);

		$argLists[] = array(
			$withQualifiers,
			$withReferences,
			new ClaimDifference(
				null,
				new Diff( array(
					new DiffOpRemove( new PropertyNoValueSnak( 1 ) )
				), false ),
				new Diff( array(
					new DiffOpAdd( new PropertyNoValueSnak( 2 ) )
				), false )
			)
		);

		$noValueForP42Preferred = clone $noValueForP42;
		$noValueForP42Preferred->setRank( Statement::RANK_PREFERRED );

		$argLists[] = array(
			$noValueForP42,
			$noValueForP42Preferred,
			new ClaimDifference(
				null,
				null,
				null,
				new DiffOpChange( Statement::RANK_NORMAL, Statement::RANK_PREFERRED )
			)
		);

		return $argLists;
	}

	/**
	 * @dataProvider diffClaimsProvider
	 *
	 * @param Claim $oldClaim
	 * @param Claim $newClaim
	 * @param ClaimDifference $expected
	 */
	public function testDiffClaims( Claim $oldClaim, Claim $newClaim, ClaimDifference $expected ) {
		$differ = new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) );
		$actual = $differ->diffClaims( $oldClaim, $newClaim );

		$this->assertInstanceOf( 'Wikibase\ClaimDifference', $actual );

		if ( !$expected->equals( $actual ) ) {
			$this->assertEquals($expected, $actual);
		}

		$this->assertTrue(
			$expected->equals( $actual ),
			'Diffing the claims results in the correct ClaimDifference'
		);
	}

}
