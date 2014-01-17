<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use Wikibase\DataModel\Claim\Claims;
use Wikibase\DataModel\Claim\Statement;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\References;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Summary;

/**
 * Class for reference change operation
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class ChangeOpReference extends ChangeOpBase {

	/**
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $claimGuid;

	/**
	 * @since 0.4
	 *
	 * @var Reference|null
	 */
	protected $reference;

	/**
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $referenceHash;

	/**
	 * @since 0.5
	 *
	 * @var int|null
	 */
	protected $index;

	/**
	 * Constructs a new reference change operation
	 *
	 * @since 0.4
	 *
	 * @param string $claimGuid
	 * @param Reference|null $reference
	 * @param string $referenceHash
	 * @param int|null $index
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $claimGuid, $reference, $referenceHash, $index = null ) {
		if ( !is_string( $claimGuid ) || $claimGuid === '' ) {
			throw new InvalidArgumentException( '$claimGuid needs to be a string and must not be empty' );
		}

		if ( !is_string( $referenceHash ) ) {
			throw new InvalidArgumentException( '$referenceHash needs to be a string' );
		}

		if ( !( $reference instanceof Reference ) && !is_null( $reference ) ) {
			throw new InvalidArgumentException( '$reference needs to be an instance of Reference or null' );
		}

		if ( $referenceHash === '' && $reference === null ) {
			throw new InvalidArgumentException( 'Either $referenceHash or $reference needs to be set' );
		}

		if( !is_null( $index ) && !is_integer( $index ) ) {
			throw new InvalidArgumentException( '$index needs to be null or an integer value' );
		}

		$this->claimGuid = $claimGuid;
		$this->reference = $reference;
		$this->referenceHash = $referenceHash;
		$this->index = $index;
	}

	/**
	 * @see ChangeOp::apply()
	 * - the reference gets removed when $referenceHash is set and $reference is not set
	 * - a new reference gets added when $referenceHash is empty and $reference is set
	 * - the reference gets set to $reference when $referenceHash and $reference are set
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		$claims = new Claims( $entity->getClaims() );

		if( !$claims->hasClaimWithGuid( $this->claimGuid ) ) {
			throw new ChangeOpException( "Entity does not have claim with GUID $this->claimGuid" );
		}

		$claim = $claims->getClaimWithGuid( $this->claimGuid );

		if ( ! ( $claim instanceof Statement ) ) {
			throw new ChangeOpException( 'The referenced claim is not a statement and thus cannot have references' );
		}

		$references = $claim->getReferences();

		if ( $this->referenceHash === '' ) {
			$this->addReference( $references, $summary );
		} else {
			if ( $this->reference != null ) {
				$this->setReference( $references, $summary );
			} else {
				$this->removeReference( $references, $summary );
			}
		}

		if ( $summary !== null ) {
			$summary->addAutoSummaryArgs( $this->getSnakSummaryArgs( $claim->getMainSnak() ) );
		}

		$claim->setReferences( $references );
		$entity->setClaims( $claims );

		return true;
	}

	/**
	 * @since 0.4
	 *
	 * @param References $references
	 * @param Summary $summary
	 *
	 * @throws ChangeOpException
	 */
	protected function addReference( References $references, Summary $summary = null ) {
		if ( $references->hasReference( $this->reference ) ) {
			$hash = $this->reference->getHash();
			throw new ChangeOpException( "Claim has already a reference with hash $hash" );
		}
		$references->addReference( $this->reference, $this->index );
		$this->updateSummary( $summary, 'add' );
	}

	/**
	 * @since 0.4
	 *
	 * @param References $references
	 * @param Summary $summary
	 *
	 * @throws ChangeOpException
	 */
	protected function setReference( References $references, Summary $summary = null ) {
		if ( !$references->hasReferenceHash( $this->referenceHash ) ) {
			throw new ChangeOpException( "Reference with hash $this->referenceHash does not exist" );
		}

		$currentIndex = $references->indexOf( $this->reference );

		if( is_null( $this->index ) && $currentIndex !== false ) {
			// Set index to current index to not have the reference removed and appended but
			// retain its position within the list of references.
			$this->index = $currentIndex;
		}

		if ( $references->hasReference( $this->reference ) && $this->index === $currentIndex ) {
			throw new ChangeOpException( "Claim has already a reference with hash "
			. "{$this->reference->getHash()} and index ($currentIndex) is not changed" );
		}
		$references->removeReferenceHash( $this->referenceHash );
		$references->addReference( $this->reference, $this->index );
		$this->updateSummary( $summary, 'set' );
	}

	/**
	 * @since 0.4
	 *
	 * @param References $references
	 * @param Summary $summary
	 *
	 * @throws ChangeOpException
	 */
	protected function removeReference( References $references, Summary $summary = null ) {
		if ( !$references->hasReferenceHash( $this->referenceHash ) ) {
			throw new ChangeOpException( "Reference with hash $this->referenceHash does not exist" );
		}
		$references->removeReferenceHash( $this->referenceHash );
		$this->updateSummary( $summary, 'remove' );
		if ( $summary !== null ) {
			$summary->addAutoCommentArgs( 1 ); //atomic edit, only one reference changed
		}
	}

	/**
	 * @since 0.4
	 *
	 * @param Snak $snak
	 * @return array
	 */
	protected function getSnakSummaryArgs( Snak $snak ) {
		$propertyId = $snak->getPropertyId();

		return array( array( $propertyId->getPrefixedId() => $snak ) );
	}
}
