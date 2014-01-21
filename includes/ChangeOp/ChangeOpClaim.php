<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use OutOfBoundsException;
use Wikibase\DataModel\ByPropertyIdArray;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;

/**
 * Class for claim modification operations
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Adam Shorland
 * @author H. Snater < mediawiki@snater.com >
 */
class ChangeOpClaim extends ChangeOpBase {

	/**
	 * @since 0.4
	 *
	 * @var Claim
	 */
	protected $claim;

	/**
	 * @since 0.5
	 *
	 * @var ClaimGuidGenerator
	 */
	protected $guidGenerator;

	/**
	 * @since 0.5
	 *
	 * @var int|null
	 */
	protected $index;

	/**
	 * @param Claim $claim
	 * @param ClaimGuidGenerator $guidGenerator
	 * @param int|null $index
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $claim, $guidGenerator, $index = null ) {
		if ( !$claim instanceof Claim ) {
			throw new InvalidArgumentException( '$claim needs to be an instance of Claim' );
		}

		if( !$guidGenerator instanceof ClaimGuidGenerator ){
			throw new InvalidArgumentException( '$guidGenerator needs to be an instance of ClaimGuidGenerator' );
		}

		if( !is_null( $index ) && !is_integer( $index ) ) {
			throw new InvalidArgumentException( '$index needs to be null or an integer value' );
		}

		$this->claim = $claim;
		$this->guidGenerator = $guidGenerator;
		$this->index = $index;
	}

	/**
	 * @see ChangeOp::apply()
	 */
	public function apply( Entity $entity, Summary $summary = null ) {

		//TODO: inject parser and validator
		$guidValidator = WikibaseRepo::getDefaultInstance()->getClaimGuidValidator();
		$guidParser = WikibaseRepo::getDefaultInstance()->getClaimGuidParser();

		if( $this->claim->getGuid() === null ){
			$this->claim->setGuid( $this->guidGenerator->newGuid() );
		}
		$guid = $guidParser->parse( $this->claim->getGuid() );

		if ( $guidValidator->validate( $guid->getSerialization() ) === false ) {
			throw new ChangeOpException( "Claim does not have a valid GUID" );
		} else if ( !$entity->getId()->equals( $guid->getEntityId() ) ){
			throw new ChangeOpException( "Claim GUID invalid for given entity" );
		}

		$entityClaims = $entity->getClaims();
		$claims = new Claims( $entityClaims );

		if( !$claims->hasClaimWithGuid( $this->claim->getGuid() ) ) {
			// Adding a new claim.
			$this->updateSummary( $summary, 'create' );

			$indexedClaimList = new ByPropertyIdArray( $entityClaims );
			$indexedClaimList->buildIndex();

			try{
				$indexedClaimList->addObjectAtIndex( $this->claim, $this->index );
			}
			catch( OutOfBoundsException $e ) {
				if( $this->index < 0 ) {
					throw new ChangeOpException( 'Can not add claim at given index : '. $this->index );
				} else {
					// XXX: hack below to retry adding the object at a new index
					// If we fail with the user supplied index and the index is greater than 0
					// presume the user wants to have the index at the end of the list
					$this->addObjectAtEndOfList( $indexedClaimList );
				}
			}

		} else {
			// Altering an existing claim.
			$this->updateSummary( $summary, 'update' );

			// Replace claim at its current index:
			$currentIndex = $claims->indexOf( $this->claim );
			$claims->removeClaimWithGuid( $this->claim->getGuid() );
			$claims->addClaim( $this->claim, $currentIndex );

			// Move claim to its designated index:
			$indexedClaimList = new ByPropertyIdArray( $claims );
			$indexedClaimList->buildIndex();

			$index = !is_null( $this->index ) ? $this->index : $currentIndex;
			$indexedClaimList->moveObjectToIndex( $this->claim, $index );
		}

		$claims = new Claims( $indexedClaimList->toFlatArray() );
		$entity->setClaims( $claims );

		return true;
	}

	/**
	 * @see Bug 58394
	 * @param ByPropertyIdArray $indexedClaimList
	 */
	private function addObjectAtEndOfList( $indexedClaimList ) {
		$newIndex = $indexedClaimList->count() + 1;
		$indexedClaimList->addObjectAtIndex( $this->claim, $newIndex );
	}
}
