<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use Wikibase\Claim;
use Wikibase\Claims;
use Wikibase\Entity;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Lib\ClaimGuidValidator;
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

		$guidValidator = new ClaimGuidValidator();
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

		$index = $this->index;

		if( $index !== null ) {
			$index = $this->getOverallClaimIndex( $entityClaims );
		}

		if( $claims->hasClaimWithGuid( $this->claim->getGuid() ) ){
			if( is_null( $index ) ) {
				// Set index to current index to not have the claim removed and appended but retain
				// its position within the list of claims.
				$index = $claims->indexOf( $this->claim );
			}

			$claims->removeClaimWithGuid( $this->claim->getGuid() );
			$this->updateSummary( $summary, 'update' );
		} else {
			$this->updateSummary( $summary, 'create' );
		}

		$claims->addClaim( $this->claim, $index );
		$entity->setClaims( $claims );

		return true;
	}

	/**
	 * Computes the claim's overall index within the list of claims from the index within the subset
	 * of claims whose main snak features the same property id.
	 * @since 0.5
	 *
	 * @param Claim[] $claims
	 * @return int|null
	 */
	protected function getOverallClaimIndex( $claims ) {
		$overallIndex = 0;
		$indexInProperty = 0;

		foreach( $claims as $claim ) {
			if( $claim->getPropertyId()->equals( $this->claim->getPropertyId() ) ) {
				if( $indexInProperty++ === $this->index ) {
					return $overallIndex;
				}
			}
			$overallIndex++;
		}

		// No claims with the same main snak property exist.
		return $this->index;
	}

}
