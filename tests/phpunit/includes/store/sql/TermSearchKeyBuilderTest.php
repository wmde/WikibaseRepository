<?php

namespace Wikibase\Test;

use Wikibase\Item;
use Wikibase\Settings;
use Wikibase\StoreFactory;
use Wikibase\TermSqlIndex;
use Wikibase\Term;
use Wikibase\TermSearchKeyBuilder;

/**
 * @covers Wikibase\TermSearchKeyBuilder
 *
 * @since 0.2
 *
 * @group Wikibase
 * @group WikibaseStore
 * @group WikibaseTerm
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class TermSearchKeyBuilderTest extends \MediaWikiTestCase {

	public function termProvider() {
		$argLists = array();

		$argLists[] = array( 'en', 'FoO', 'fOo', true );
		$argLists[] = array( 'ru', 'Берлин', '  берлин  ', true );

		$argLists[] = array( 'en', 'FoO', 'bar', false );
		$argLists[] = array( 'ru', 'Берлин', 'бе55585рлин', false );

		return $argLists;
	}

	/**
	 * @dataProvider termProvider
	 * @param $languageCode
	 * @param $termText
	 * @param $searchText
	 * @param boolean $matches
	 */
	public function testRebuildSearchKey( $languageCode, $termText, $searchText, $matches ) {
		if ( Settings::get( 'withoutTermSearchKey' ) ) {
			$this->markTestSkipped( "can't test search key if withoutTermSearchKey option is set." );
		}

		// make term in item
		$item = Item::newEmpty();
		$item->setId( 42 );
		$item->setLabel( $languageCode, $termText );

		// save term
		/* @var TermSqlIndex $termCache */
		$termCache = StoreFactory::getStore( 'sqlstore' )->getTermIndex();
		$termCache->clear();
		$termCache->saveTermsOfEntity( $item );

		// remove search key
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( $termCache->getTableName(), array( 'term_search_key' => '' ), array(), __METHOD__ );

		// rebuild search key
		$builder = new TermSearchKeyBuilder( $termCache );
		$builder->setRebuildAll( true );
		$builder->rebuildSearchKey();

		// remove search key
		$term = new Term();
		$term->setLanguage( $languageCode );
		$term->setText( $searchText );

		$options = array(
			'caseSensitive' => false,
		);

		$obtainedTerms = $termCache->getMatchingTerms( array( $term ), Term::TYPE_LABEL, Item::ENTITY_TYPE, $options );

		$this->assertEquals( $matches ? 1 : 0, count( $obtainedTerms ) );

		if ( $matches ) {
			$obtainedTerm = array_shift( $obtainedTerms );

			$this->assertEquals( $termText, $obtainedTerm->getText() );
		}
	}

}
