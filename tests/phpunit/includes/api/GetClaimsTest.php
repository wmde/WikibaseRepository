<?php

namespace Wikibase\Test\Api;

use DataValues\StringValue;
use UsageException;
use Wikibase\DataModel\Claim\Statement;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Api\GetClaims
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group GetClaimsTest
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Adam Shorland
 */
class GetClaimsTest extends \ApiTestCase {

	/**
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	protected function addClaimsAndSave( Entity $entity ) {
		wfSuppressWarnings(); // We are referencing properties that don't exist. Not relevant here.

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$store->saveEntity( $entity, '', $GLOBALS['wgUser'], EDIT_NEW );

		/** @var $claims Claim[] */
		$claims[0] = $entity->newClaim( new PropertyNoValueSnak( 42 ) );
		$claims[1] = $entity->newClaim( new PropertyNoValueSnak( 1 ) );
		$claims[2] = $entity->newClaim( new PropertySomeValueSnak( 42 ) );
		$claims[3] = $entity->newClaim( new PropertyValueSnak( 9001, new StringValue( 'o_O' ) ) );

		foreach( $claims as $key => $claim ){
			$claim->setGuid( $entity->getId()->getPrefixedId() . '$D8404CDA-56A1-4334-AF13-A3290BCD9CL' . $key );
			$entity->addClaim( $claim );
		}

		$store->saveEntity( $entity, '', $GLOBALS['wgUser'], EDIT_UPDATE );
		wfRestoreWarnings();

		return $entity;
	}

	/**
	 * @return Entity[]
	 */
	protected function getNewEntities() {
		$property = Property::newEmpty();

		$property->setDataTypeId( 'string' );

		return array(
			$this->addClaimsAndSave( Item::newEmpty() ),
			$this->addClaimsAndSave( $property ),
		);
	}

	public function validRequestProvider() {
		$entities = $this->getNewEntities();

		$argLists = array();

		foreach ( $entities as $entity ) {
			$params = array(
				'action' => 'wbgetclaims',
				'entity' => $entity->getId()->getSerialization(),
			);

			$argLists[] = array( $params, $entity->getClaims(), true );

			/**
			 * @var Claim $claim
			 */
			foreach ( $entity->getClaims() as $claim ) {
				$params = array(
					'action' => 'wbgetclaims',
					'claim' => $claim->getGuid(),
				);
				$argLists[] = array( $params, array( $claim ), true );

				$params['ungroupedlist'] = true;
				$argLists[] = array( $params, array( $claim ), false );
			}

			foreach ( array( Statement::RANK_DEPRECATED, Statement::RANK_NORMAL, Statement::RANK_PREFERRED ) as $rank ) {
				$params = array(
					'action' => 'wbgetclaims',
					'entity' => $entity->getId()->getSerialization(),
					'rank' => ClaimSerializer::serializeRank( $rank ),
				);

				$claims = array();

				foreach ( $entity->getClaims() as $claim ) {
					if ( $claim instanceof Statement && $claim->getRank() === $rank ) {
						$claims[] = $claim;
					}
				}

				$argLists[] = array( $params, $claims, true );
			}
		}

		return $argLists;
	}

	public function testValidRequests() {
		foreach ( $this->validRequestProvider() as $argList ) {
			list( $params, $claims, $groupedByProperty ) = $argList;

			$this->doTestValidRequest( $params, $claims, $groupedByProperty );
		}
	}

	/**
	 * @param string[] $params
	 * @param Claims|Claim[] $claims
	 * @param bool $groupedByProperty
	 */
	public function doTestValidRequest( array $params, $claims, $groupedByProperty ) {
		if ( is_array( $claims ) ) {
			$claims = new Claims( $claims );
		}
		$options = new SerializationOptions();
		if( !$groupedByProperty ) {
			$options->setOption( SerializationOptions::OPT_GROUP_BY_PROPERTIES, array() );
		}
		$serializerFactory = new SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $claims );
		$serializer->setOptions( $options );
		$expected = $serializer->getSerialized( $claims );

		list( $resultArray, ) = $this->doApiRequest( $params );

		$this->assertInternalType( 'array', $resultArray, 'top level element is an array' );
		$this->assertArrayHasKey( 'claims', $resultArray, 'top level element has a claims key' );

		$this->assertEquals( $expected, $resultArray['claims'] );
	}

	/**
	 * @dataProvider invalidClaimProvider
	 */
	public function testGetInvalidClaims( $claimGuid ) {
		$params = array(
			'action' => 'wbgetclaims',
			'claim' => $claimGuid
		);

		try {
			$this->doApiRequest( $params );
			$this->fail( 'Invalid claim guid did not throw an error' );
		} catch ( UsageException $e ) {
			$this->assertEquals( 'invalid-guid', $e->getCodeString(), 'Invalid claim guid raised correct error' );
		}
	}

	public function invalidClaimProvider() {
		return array(
			array( 'xyz' ),
			array( 'x$y$z' )
		);
	}
}
