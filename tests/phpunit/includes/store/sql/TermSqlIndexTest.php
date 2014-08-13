<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\StringNormalizer;
use Wikibase\Term;
use Wikibase\TermSqlIndex;

/**
 * @covers Wikibase\TermSqlIndex
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class TermSqlIndexTest extends TermIndexTest {

	public function setUp() {
		parent::setUp();

		$this->tablesUsed[] = 'wb_terms';
	}

	/**
	 * @return TermSqlIndex
	 */
	public function getTermIndex() {
		$normalizer = new StringNormalizer();
		return new TermSqlIndex( $normalizer );
	}

	public function termProvider() {
		$argLists = array();

		$argLists[] = array( 'en', 'FoO', 'fOo', true );
		$argLists[] = array( 'ru', 'Берлин', 'берлин', true );

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
	public function testGetMatchingTerms2( $languageCode, $termText, $searchText, $matches ) {
		$withoutTermSearchKey = WikibaseRepo::getDefaultInstance()->
			getSettings()->getSetting( 'withoutTermSearchKey' );

		if ( $withoutTermSearchKey ) {
			$this->markTestSkipped( "can't test search key if withoutTermSearchKey option is set." );
		}

		$termIndex = $this->getTermIndex();

		$termIndex->clear();

		$item = Item::newEmpty();
		$item->setId( 42 );

		$item->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item );

		$term = new Term();
		$term->setLanguage( $languageCode );
		$term->setText( $searchText );

		$options = array(
			'caseSensitive' => false,
		);

		$obtainedTerms = $termIndex->getMatchingTerms( array( $term ), Term::TYPE_LABEL, Item::ENTITY_TYPE, $options );

		$this->assertEquals( $matches ? 1 : 0, count( $obtainedTerms ) );

		if ( $matches ) {
			$obtainedTerm = array_shift( $obtainedTerms );

			$this->assertEquals( $termText, $obtainedTerm->getText() );
		}
	}

	/**
	 * @dataProvider termProvider
	 * @param $languageCode
	 * @param $termText
	 * @param $searchText
	 * @param boolean $matches
	 */
	public function testGetMatchingTermsWeights( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();

		if ( !$termIndex->supportsWeight() ) {
			$this->markTestSkipped( "can't test search weight if withoutTermWeight option is set." );
		}

		$termIndex->clear();

		$item1 = Item::newEmpty();
		$item1->setId( 42 );

		$item1->setLabel( $languageCode, $termText );
		$item1->addSiteLink( new SiteLink( 'enwiki', 'A' ) );

		$termIndex->saveTermsOfEntity( $item1 );

		$item2 = Item::newEmpty();
		$item2->setId( 23 );

		$item2->setLabel( $languageCode, $termText );
		$item2->addSiteLink( new SiteLink( 'enwiki', 'B' ) );
		$item2->addSiteLink( new SiteLink( 'dewiki', 'B' ) );
		$item2->addSiteLink( new SiteLink( 'hrwiki', 'B' ) );
		$item2->addSiteLink( new SiteLink( 'uzwiki', 'B' ) );

		$termIndex->saveTermsOfEntity( $item2 );

		$item3 = Item::newEmpty();
		$item3->setId( 108 );

		$item3->setLabel( $languageCode, $termText );
		$item3->addSiteLink( new SiteLink( 'hrwiki', 'C' ) );
		$item3->addSiteLink( new SiteLink( 'uzwiki', 'C' ) );

		$termIndex->saveTermsOfEntity( $item3 );

		$term = new Term();
		$term->setLanguage( $languageCode );
		$term->setText( $searchText );

		$options = array(
			'caseSensitive' => false,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertEquals( $matches ? 3 : 0, count( $obtainedIDs ) );

		if ( $matches ) {
			$expectedResult = array( $item2->getId(), $item3->getId(), $item1->getId() );
			$this->assertArrayEquals( $expectedResult, $obtainedIDs, true );
		}
	}

	/**
	 * @dataProvider termProvider
	 * @param $languageCode
	 * @param $termText
	 * @param $searchText
	 * @param boolean $matches
	 */
	public function testPrefixSearch( $languageCode, $termText, $searchText, $matches ) {
		$termIndex = $this->getTermIndex();

		$termIndex->clear();

		$item1 = Item::newEmpty();
		$item1->setId( 42 );

		$item1->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item1 );

		$term = new Term();
		$term->setLanguage( $languageCode );
		$term->setText( substr( $termText, 0, -1 ) ); //last character stripped

		$options = array(
			'caseSensitive' => false,
			'prefixSearch' => true,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertNotEmpty( $obtainedIDs );
	}

	/**
	 * @dataProvider termProvider
	 */
	public function testPrefixSearchQuoting( $languageCode, $termText ) {
		$termIndex = $this->getTermIndex();

		$termIndex->clear();

		$item1 = Item::newEmpty();
		$item1->setId( 42 );

		$item1->setLabel( $languageCode, $termText );

		$termIndex->saveTermsOfEntity( $item1 );

		$term = new Term();
		$term->setLanguage( $languageCode );
		$term->setText( '%' . $termText ); //must be used as a character and no LIKE placeholder

		$options = array(
			'caseSensitive' => false,
			'prefixSearch' => true,
		);

		$obtainedIDs = $termIndex->getMatchingIDs( array( $term ), Item::ENTITY_TYPE, $options );

		$this->assertEmpty( $obtainedIDs );
	}

	public static function provideGetSearchKey() {
		return array(
			array( // #0
				'foo', // raw
				'en',  // lang
				'foo', // normalized
			),

			array( // #1
				'  foo  ', // raw
				'en',  // lang
				'foo', // normalized
			),

			array( // #2: lower case of non-ascii character
				'ÄpFEl', // raw
				'de',    // lang
				'äpfel', // normalized
			),

			array( // #3: lower case of decomposed character
				"A\xCC\x88pfel", // raw
				'de',    // lang
				'äpfel', // normalized
			),

			array( // #4: lower case of cyrillic character
				'Берлин', // raw
				'ru',     // lang
				'берлин', // normalized
			),

			array( // #5: lower case of greek character
				'Τάχιστη', // raw
				'he',      // lang
				'τάχιστη', // normalized
			),

			array( // #6: nasty unicode whitespace
				// ZWNJ: U+200C \xE2\x80\x8C
				// RTLM: U+200F \xE2\x80\x8F
				// PSEP: U+2029 \xE2\x80\xA9
				"\xE2\x80\x8F\xE2\x80\x8Cfoo\xE2\x80\x8Cbar\xE2\x80\xA9", // raw
				'en',      // lang
				"foo bar", // normalized
			),
		);
	}

	/**
	 * @dataProvider provideGetSearchKey
	 */
	public function testGetSearchKey( $raw, $lang, $normalized ) {
		$index = $this->getTermIndex();

		$key = $index->getSearchKey( $raw, $lang );
		$this->assertEquals( $normalized, $key );
	}

}
