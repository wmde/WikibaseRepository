<?php

namespace Wikibase\Test;
use DataValues\StringValue;
use Wikibase\Claim;
use Wikibase\EntityContent;
use Wikibase\EntityContentFactory;
use Wikibase\EntityId;
use Wikibase\EntityView;
use Wikibase\Item;
use Wikibase\ItemContent;
use \ValueFormatters\ValueFormatterFactory;
use Wikibase\Lib\InMemoryDataTypeLookup;
use Wikibase\Property;
use Wikibase\PropertyContent;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\PropertyValueSnak;

/**
 * @covers Wikibase\EntityView
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @file
 * @since 0.4
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group EntityView
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Kinzler
 */
class EntityViewTest extends \PHPUnit_Framework_TestCase {

	protected function newEntityView( EntityContent $entityContent ) {
		$valueFormatters = new ValueFormatterFactory( array() );
		$entityLoader = new MockRepository();

		$p11 = new EntityId( Property::ENTITY_TYPE, 11 );
		$p23 = new EntityId( Property::ENTITY_TYPE, 23 );
		$p42 = new EntityId( Property::ENTITY_TYPE, 42 );
		$p44 = new EntityId( Property::ENTITY_TYPE, 44 );

		$dataTypeLookup = new InMemoryDataTypeLookup();
		$dataTypeLookup->setDataTypeForProperty( $p23, 'string' );
		$dataTypeLookup->setDataTypeForProperty( $p42, 'url' );

		$dataTypeLookup->setDataTypeForProperty( $p11, 'wikibase-item' );
		$dataTypeLookup->setDataTypeForProperty( $p44, 'wikibase-item' );

		$entityView = EntityView::newForEntityContent(
			$entityContent,
			$valueFormatters,
			$dataTypeLookup,
			$entityLoader
		);

		return $entityView;
	}

	protected function newEntityContentForClaims( $claims ) {
		$entity = Item::newEmpty();

		foreach ( $claims as $claim ) {
			$entity->addClaim( $claim );
		}

		$content = EntityContentFactory::singleton()->newFromEntity( $entity );
		return $content;
	}

	/**
	 * @return array
	 */
	public function getHtmlForClaimsProvider() {
		$argLists = array();

		$itemContent = ItemContent::newEmpty();
		$itemContent->getEntity()->addClaim(
			new Claim(
				new PropertyNoValueSnak(
					new EntityId( Property::ENTITY_TYPE, 24 )
				)
			)
		);

		$argLists[] = array( $itemContent );

		return $argLists;
	}

	/**
	 * @dataProvider getHtmlForClaimsProvider
	 *
	 * @param EntityContent $entityContent
	 */
	public function testGetHtmlForClaims( EntityContent $entityContent ) {
		$entityView = $this->newEntityView( $entityContent );

		// Using a DOM document to parse HTML output:
		$doc = new \DOMDocument();

		// Disable default error handling in order to catch warnings caused by malformed markup:
		libxml_use_internal_errors( true );

		// Try loading the HTML:
		$this->assertTrue( $doc->loadHTML( $entityView->getHtmlForClaims( $entityContent ) ) );

		// Check if no warnings have been thrown:
		$errorString = '';
		foreach( libxml_get_errors() as $error ) {
			$errorString .= "\r\n" . $error->message;
		}

		$this->assertEmpty( $errorString, 'Malformed markup:' . $errorString );

		// Clear error cache and re-enable default error handling:
		libxml_clear_errors();
		libxml_use_internal_errors();
	}

	/**
	 * @dataProvider getParserOutputLinksProvider
	 *
	 * @param Claim[] $claims
	 * @param EntityId[] $expectedLinks
	 */
	public function testParserOutputLinks( array $claims, $expectedLinks ) {
		$entityContent = $this->newEntityContentForClaims( $claims );
		$entityView = $this->newEntityView( $entityContent );

		$out = $entityView->getParserOutput( $entityContent, null, false );
		$links = $out->getLinks();

		// convert expected links to link structure
		$contentFactory = EntityContentFactory::singleton();

		foreach ( $expectedLinks as $entityId ) {
			$title = $contentFactory->getTitleForId( $entityId );
			$ns = $title->getNamespace();
			$dbk = $title->getDBkey();

			$this->assertArrayHasKey( $ns, $links, "sub-array for namespace" );
			$this->assertArrayHasKey( $dbk, $links[$ns], "entry for database key" );
		}
	}

	public function getParserOutputLinksProvider() {
		$argLists = array();

		$p11 = new EntityId( Property::ENTITY_TYPE, 11 );
		$p23 = new EntityId( Property::ENTITY_TYPE, 42 );
		$p44 = new EntityId( Property::ENTITY_TYPE, 44 );

		$q23 = new EntityId( Item::ENTITY_TYPE, 23 );
		$q24 = new EntityId( Item::ENTITY_TYPE, 24 );

		$argLists["empty"] = array(
			array(),
			array() );

		$argLists["PropertyNoValueSnak"] = array(
			array( new Claim( new PropertyNoValueSnak( $p44 ) ) ),
			array( $p44 ) );

		$argLists["PropertySomeValueSnak"] = array(
			array( new Claim( new PropertySomeValueSnak( $p44 ) ) ),
			array( $p44 ) );

		$argLists["PropertyValueSnak with string value"] = array(
			array( new Claim( new PropertyValueSnak( $p23, new StringValue( 'onoez' ) ) ) ),
			array( $p23 ) );

		$argLists["PropertyValueSnak with EntityId"] = array(
			array( new Claim( new PropertyValueSnak( $p44, $q23 ) ) ),
			array( $p44, $q23 ) );

		$argLists["Mixed Snaks"] = array(
			array(
				new Claim( new PropertyValueSnak( $p11, $q23 ) ),
				new Claim( new PropertyNoValueSnak( $p44 ) ),
				new Claim( new PropertySomeValueSnak( $p44 ) ),
				new Claim( new PropertyValueSnak( $p44, new StringValue( 'onoez' ) ) ),
				new Claim( new PropertyValueSnak( $p44, $q24 ) ),
			),
			array( $p11, $q23, $p44, $q24 ) );

		return $argLists;
	}
	
	/**
	 * @dataProvider getParserOutputExternalLinksProvider
	 *
	 * @param Claim[] $claims
	 * @param string[] $expectedLinks
	 */
	public function testParserOutputExternalLinks( array $claims, $expectedLinks ) {
		$entityContent = $this->newEntityContentForClaims( $claims );
		$entityView = $this->newEntityView( $entityContent );

		$out = $entityView->getParserOutput( $entityContent, null, false );
		$links = $out->getExternalLinks();

		$expectedLinks = array_values( $expectedLinks );
		sort( $expectedLinks );

		$links = array_keys( $links );
		sort( $links );

		$this->assertEquals( $expectedLinks, $links );
	}

	public function getParserOutputExternalLinksProvider() {
		$argLists = array();

		$p23 = new EntityId( Property::ENTITY_TYPE, 23 );
		$p42 = new EntityId( Property::ENTITY_TYPE, 42 );

		$argLists["empty"] = array(
			array(),
			array() );

		$argLists["PropertyNoValueSnak"] = array(
			array( new Claim( new PropertyNoValueSnak( $p42 ) ) ),
			array());

		$argLists["PropertySomeValueSnak"] = array(
			array( new Claim( new PropertySomeValueSnak( $p42 ) ) ),
			array() );

		$argLists["PropertyValueSnak with string value"] = array(
			array( new Claim( new PropertyValueSnak( $p23, new StringValue( 'http://not/a/url' )  ) ) ),
			array() );

		$argLists["PropertyValueSnak with URL"] = array(
			array( new Claim( new PropertyValueSnak( $p42, new StringValue( 'http://acme.com/test' ) ) ) ),
			array( 'http://acme.com/test' ) );

		return $argLists;
	}

	/**
	 * @dataProvider providerNewForEntityContent
	 */
	public function testNewForEntityContent( EntityContent $entityContent ) {
		$valueFormatters = new ValueFormatterFactory( array() );
		$dataTypeLookup = new InMemoryDataTypeLookup( array() );
		$entityLoader = new MockRepository();

		// test whether we get the right EntityView from an EntityContent
		$view = EntityView::newForEntityContent( $entityContent, $valueFormatters, $dataTypeLookup, $entityLoader );
		$this->assertInstanceOf(
			EntityView::$typeMap[ $entityContent->getEntity()->getType() ],
			$view
		);
	}

	public static function providerNewForEntityContent() {
		return array(
			array( ItemContent::newEmpty() ),
			array( PropertyContent::newEmpty() )
		);
	}
}
