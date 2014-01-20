<?php

namespace Wikibase\Test\Api;

use UsageException;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\ItemContent;
use Wikibase\PropertyContent;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Api\CreateClaim
 *
 * @since 0.3
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group CreateClaimTest
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CreateClaimTest extends WikibaseApiTestCase {

	protected static function getNewEntityAndProperty() {
		$entity = Item::newEmpty();
		$content = new ItemContent( $entity );
		$content->save( '', null, EDIT_NEW );
		$entity = $content->getEntity();

		$property = Property::newFromType( 'commonsMedia' );
		$content = new PropertyContent( $property );
		$content->save( '', null, EDIT_NEW );
		$property = $content->getEntity();

		return array( $entity, $property );
	}

	protected function assertRequestValidity( $resultArray ) {
		$this->assertResultSuccess( $resultArray );
		$this->assertInternalType( 'array', $resultArray, 'top level element is an array' );
		$this->assertArrayHasKey( 'claim', $resultArray, 'top level element has a claim key' );
		$this->assertArrayNotHasKey( 'lastrevid', $resultArray['claim'], 'claim has a lastrevid key' );

		$this->assertArrayHasKey( 'pageinfo', $resultArray, 'top level element has a pageinfo key' );
		$this->assertArrayHasKey( 'lastrevid', $resultArray['pageinfo'], 'pageinfo has a lastrevid key' );
	}

	public function testValidRequest() {
		list( $entity, $property ) = self::getNewEntityAndProperty();

		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => $this->getFormattedIdForEntity( $entity ),
			'snaktype' => 'value',
			'property' => $this->getFormattedIdForEntity( $property ),
			'value' => '"Foo.png"',
		);

		list( $resultArray, ) = $this->doApiRequestWithToken( $params );

		$this->assertRequestValidity( $resultArray );

		$claim = $resultArray['claim'];

		foreach ( array( 'id', 'mainsnak', 'type', 'rank' ) as $requiredKey ) {
			$this->assertArrayHasKey( $requiredKey, $claim, 'claim has a "' . $requiredKey . '" key' );
		}

		$this->assertStringStartsWith( $this->getFormattedIdForEntity( $entity ) , $claim['id'] );

		$this->assertEquals( 'value', $claim['mainsnak']['snaktype'] );

		$entityContent = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromId( $entity->getId() );

		$claims = new Claims( $entityContent->getEntity()->getClaims() );

		$this->assertTrue( $claims->hasClaimWithGuid( $claim['id'] ) );
	}

	public function invalidRequestProvider() {
		$argLists = array();

		//0
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => 'q123456789',
			'snaktype' => 'value',
			'property' => '-',
			'value' => '"Foo.png"',
		);
		$argLists[] = array( 'cant-load-entity-content', $params );

		//1
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => 'i123',
			'snaktype' => 'value',
			'property' => '-',
			'value' => '"Foo.png"',
		);
		$argLists[] = array( 'invalid-entity-id', $params );

		//2
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'value',
			'property' => 'i123',
			'value' => '"Foo.png"',
		);
		$argLists[] = array( 'invalid-entity-id', $params );

		//3
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'value',
			'property' => 'p1',
			'value' => 'Foo.png',
		);
		$argLists[] = array( 'invalid-snak', $params );

		//4
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'hax',
			'property' => '-',
			'value' => '"Foo.png"',
		);
		$argLists[] = array( 'unknown_snaktype', $params );

		//5, 6
		foreach ( array( 'entity', 'snaktype' ) as $requiredParam ) {
			$params = array(
				'action' => 'wbcreateclaim',
				'entity' => '-',
				'snaktype' => 'value',
				'property' => '-',
				'value' => '"Foo.png"',
			);

			unset( $params[$requiredParam] );

			$argLists[] = array( 'no' . $requiredParam, $params );
		}

		//7
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'value',
			'value' => '"Foo.png"',
		);
		$argLists[] = array( 'param-missing', $params );

		//8
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'value',
			'property' => '-',
		);
		$argLists[] = array( 'param-missing', $params );

		//9
		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => '-',
			'snaktype' => 'value',
			'property' => '-',
			'value' => '{"x":"foo", "y":"bar"}',
		);
		$argLists[] = array( 'invalid-snak', $params );

		return $argLists;
	}

	public static function getEntityAndPropertyForInvalid() {
		static $array = null;

		if ( $array === null ) {
			$array = self::getNewEntityAndProperty();
		}

		return $array;
	}

	/**
	 * @dataProvider invalidRequestProvider
	 *
	 * @param string $errorCode
	 * @param array $params
	 */
	public function testInvalidRequest( $errorCode, array $params ) {
		list( $entity, $property ) = self::getEntityAndPropertyForInvalid();

		if ( array_key_exists( 'entity', $params ) && $params['entity'] === '-' ) {
			$params['entity'] = $this->getFormattedIdForEntity( $entity );
		}

		if ( array_key_exists( 'property', $params ) && $params['property'] === '-' ) {
			$params['property'] = $this->getFormattedIdForEntity( $property );
		}

		try {
			$this->doApiRequestWithToken( $params );
			$this->fail( 'Invalid request should raise an exception' );
		}
		catch ( UsageException $e ) {
			$this->assertEquals(
				$errorCode,
				$e->getCodeString(), 'Invalid request raised correct error: ' . $e->getMessage()
			);
		}

		$entityContent = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromId( $entity->getId() );

		$this->assertFalse( $entityContent->getEntity()->hasClaims() );
	}

	protected function getFormattedIdForEntity( Entity $entity ) {
		$idFormatter = WikibaseRepo::getDefaultInstance()->getIdFormatter();
		return $idFormatter->format( $entity->getId() );
	}

	public function testMultipleRequests() {
		list( $entity, $property ) = self::getNewEntityAndProperty();

		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => $this->getFormattedIdForEntity( $entity ),
			'snaktype' => 'value',
			'property' => $this->getFormattedIdForEntity( $property ),
			'value' => '"Foo.png"',
		);

		list( $resultArray, ) = $this->doApiRequestWithToken( $params );

		$this->assertRequestValidity( $resultArray );

		$revId = $resultArray['pageinfo']['lastrevid'];

		$firstGuid = $resultArray['claim']['id'];

		$params = array(
			'action' => 'wbcreateclaim',
			'entity' => $this->getFormattedIdForEntity( $entity ),
			'snaktype' => 'value',
			'property' => $this->getFormattedIdForEntity( $property ),
			'value' => '"Bar.jpg"',
			'baserevid' => $revId
		);

		list( $resultArray, ) = $this->doApiRequestWithToken( $params );

		$this->assertRequestValidity( $resultArray );

		$newRevId = $resultArray['pageinfo']['lastrevid'];

		$secondGuid = $resultArray['claim']['id'];

		$this->assertTrue( (int)$revId < (int)$newRevId );

		$this->assertNotEquals( $firstGuid, $secondGuid );

		$entityContent = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromId( $entity->getId() );

		$claims = new Claims( $entityContent->getEntity()->getClaims() );

		$this->assertTrue( $claims->hasClaimWithGuid( $firstGuid ) );
		$this->assertTrue( $claims->hasClaimWithGuid( $secondGuid ) );
	}

}
