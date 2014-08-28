<?php

namespace Wikibase\Test;

use MessageCache;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Repo\View\FingerprintView;
use Wikibase\Repo\View\SectionEditLinkGenerator;

/**
 * @covers Wikibase\Repo\View\FingerprintView
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @group Database
 *		^---- needed because we rely on Title objects internally
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Thiemo Mättig
 */
class FingerprintViewTest extends \MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();

		$msgCache = MessageCache::singleton();
		$msgCache->enable();

		// Mocks for all "this is empty" placeholders
		$msgCache->replace( 'Wikibase-label-empty', '<strong class="test">No label</strong>' );
		$msgCache->replace( 'Wikibase-description-empty', '<strong class="test">No description</strong>' );
		$msgCache->replace( 'Wikibase-aliases-empty', '<strong class="test">No aliases</strong>' );

		// Mock for the only other message in the class
		$msgCache->replace( 'Wikibase-aliases-label', '<strong class="test">A.&thinsp;k.&thinsp;a.:</strong>' );
	}

	protected function tearDown() {
		$msgCache = MessageCache::singleton();
		$msgCache->disable();
	}

	private function getFingerprintView( $languageCode = 'en' ) {
		return new FingerprintView( new SectionEditLinkGenerator(), $languageCode );
	}

	private function getFingerprint( $languageCode = 'en' ) {
		$fingerprint = Fingerprint::newEmpty();
		$fingerprint->setLabel( $languageCode, 'Example label' );
		$fingerprint->setDescription( $languageCode, 'This is an example description' );
		$fingerprint->setAliasGroup(
			$languageCode,
			array(
				'sample alias',
				'specimen alias',
			)
		);
		return $fingerprint;
	}

	public function testGetHtml_containsTermsAndAliases() {
		$fingerprintView = $this->getFingerprintView();
		$fingerprint = $this->getFingerprint();
		$html = $fingerprintView->getHtml( $fingerprint );

		$this->assertContains( htmlspecialchars( $fingerprint->getLabel( 'en' )->getText() ), $html );
		$this->assertContains( htmlspecialchars( $fingerprint->getDescription( 'en' )->getText() ), $html );
		foreach ( $fingerprint->getAliasGroup( 'en' )->getAliases() as $alias ) {
			$this->assertContains( htmlspecialchars( $alias ), $html );
		}
	}

	public function entityFingerprintProvider() {
		$fingerprint = $this->getFingerprint();

		return array(
			'empty' => array( Fingerprint::newEmpty(), new ItemId( 'Q42' ), 'en' ),
			'other language' => array( $fingerprint, new ItemId( 'Q42' ), 'de' ),
			'other id' => array( $fingerprint, new ItemId( 'Q12' ), 'en' ),
		);
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_isEditable( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$fingerprintView = $this->getFingerprintView( $languageCode );
		$html = $fingerprintView->getHtml( $fingerprint, $entityId );
		$idString = $entityId->getSerialization();

		$this->assertRegExp( '@<a href="[^"]*\bSpecial:SetLabel/' . $idString . '/' . $languageCode . '"@', $html );
		$this->assertRegExp( '@<a href="[^"]*\bSpecial:SetDescription/' . $idString . '/' . $languageCode . '"@', $html );
		$this->assertRegExp( '@<a href="[^"]*\bSpecial:SetAliases/' . $idString . '/' . $languageCode . '"@', $html );
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_isNotEditable( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$fingerprintView = $this->getFingerprintView( $languageCode );
		$html = $fingerprintView->getHtml( $fingerprint, $entityId, false );

		$this->assertNotContains( '<a ', $html );
	}

	public function testGetHtml_valuesAreEscaped() {
		$fingerprintView = $this->getFingerprintView();
		$fingerprint = Fingerprint::newEmpty();
		$fingerprint->setLabel( 'en', '<a href="#">evil html</a>' );
		$fingerprint->setDescription( 'en', '<script>alert( "xss" );</script>' );
		$fingerprint->setAliasGroup( 'en', array( '<b>bold</b>', '<i>italic</i>' ) );
		$html = $fingerprintView->getHtml( $fingerprint );

		$this->assertContains( 'evil html', $html, 'make sure it works' );
		$this->assertNotContains( 'href="#"', $html );
		$this->assertNotContains( '<script>', $html );
		$this->assertNotContains( '<b>', $html );
		$this->assertNotContains( '<i>', $html );
	}

	public function emptyFingerprintProvider() {
		$noLabel = $this->getFingerprint();
		$noLabel->removeLabel( 'en' );

		$noDescription = $this->getFingerprint();
		$noDescription->removeDescription( 'en' );

		$noAliases = $this->getFingerprint();
		$noAliases->removeAliasGroup( 'en' );

		return array(
			array( Fingerprint::newEmpty(), array( 'wb-value-empty', 'wb-empty' ), 'No' ),
			array( $noLabel, array( 'wb-value-empty' ), 'No label' ),
			array( $noDescription, array( 'wb-empty' ), 'No description' ),
			array( $noAliases, array( 'wb-empty' ), 'No aliases' ),
		);
	}

	/**
	 * @dataProvider emptyFingerprintProvider
	 */
	public function testGetHtml_isMarkedAsEmptyValue( Fingerprint $fingerprint, array $classes ) {
		$fingerprintView = $this->getFingerprintView();
		$html = $fingerprintView->getHtml( $fingerprint );

		foreach ( $classes as $class ) {
			$this->assertContains( $class, $html );
		}
	}

	public function testGetHtml_isNotMarkedAsEmpty() {
		$fingerprintView = $this->getFingerprintView();
		$html = $fingerprintView->getHtml( $this->getFingerprint() );

		$this->assertNotContains( 'wb-empty', $html );
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_withEntityId( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$fingerprintView = $this->getFingerprintView( $languageCode );
		$html = $fingerprintView->getHtml( $fingerprint, $entityId );
		$idString = $entityId->getSerialization();

		$this->assertNotContains( 'id="wb-firstHeading-new"', $html );
		$this->assertContains( 'id="wb-firstHeading-' . $idString . '"', $html );
		$this->assertContains( 'wb-value-supplement', $html );
		$this->assertRegExp( '/[ "]wb-value[ "].*[ "]wb-value-supplement[ "]/s', $html,
			'supplement follows value' );
		$this->assertContains( '<a ', $html );
	}

	public function testGetHtml_withoutEntityId() {
		$fingerprintView = $this->getFingerprintView();
		$html = $fingerprintView->getHtml( Fingerprint::newEmpty() );

		$this->assertContains( 'id="wb-firstHeading-new"', $html );
		$this->assertNotContains( 'id="wb-firstHeading-Q', $html );
		$this->assertNotContains( 'wb-value-supplement', $html );
		$this->assertNotContains( '<a ', $html );
	}

	public function testGetHtml_containsAliasesLabel() {
		$fingerprintView = $this->getFingerprintView();
		$html = $fingerprintView->getHtml( $this->getFingerprint() );

		$this->assertContains( 'A.&thinsp;k.&thinsp;a.:', $html );
		$this->assertContains( 'strong', $html, 'make sure the setUp works' );
		$this->assertNotContains( '<strong class="test">', $html );
	}

	/**
	 * @dataProvider emptyFingerprintProvider
	 */
	public function testGetHtml_containsIsEmptyPlaceholders( Fingerprint $fingerprint, array $classes, $message ) {
		$fingerprintView = $this->getFingerprintView();
		$html = $fingerprintView->getHtml( $fingerprint );

		$this->assertContains( $message, $html );
		$this->assertContains( 'strong', $html, 'make sure the setUp works' );
		$this->assertNotContains( '<strong class="test">', $html );
	}

}
