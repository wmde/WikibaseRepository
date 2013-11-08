<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use Wikibase\ItemContent;
use Wikibase\Lib\ClaimGuidGenerator;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpsMerge {

	private $fromItemContent;
	private $toItemContent;
	private $fromChangeOps;
	private $toChangeOps;
	/** @var array */
	private $ignoreConflicts;

	/**
	 * @param ItemContent $fromItemContent
	 * @param ItemContent $toItemContent
	 * @param array $ignoreConflicts list of elements to ignore conflicts for
	 *   can only contain 'label' and or 'description' and or 'sitelink'
	 */
	public function __construct(
		ItemContent $fromItemContent,
		ItemContent $toItemContent,
		$ignoreConflicts = array()
	) {
		$this->fromItemContent = $fromItemContent;
		$this->toItemContent = $toItemContent;
		$this->fromChangeOps = new ChangeOps();
		$this->toChangeOps = new ChangeOps();
		$this->ignoreConflicts = $ignoreConflicts;
		$this->assertValidIgnoreConflictValues();
	}

	private function assertValidIgnoreConflictValues() {
		if( !is_array( $this->ignoreConflicts ) ){
			throw new InvalidArgumentException( '$ignoreConflicts must be an array' );
		}
		foreach( $this->ignoreConflicts as $ignoreConflict ){
			if( $ignoreConflict !== 'label' && $ignoreConflict !== 'description' && $ignoreConflict !== 'sitelink' ){
				throw new InvalidArgumentException( '$ignoreConflicts array can only contain "label", "description" and or "sitelink" values' );
			}
		}
	}

	public function apply() {
		$this->generateChangeOps();
		$this->fromChangeOps->apply( $this->fromItemContent->getItem() );
		$this->toChangeOps->apply( $this->toItemContent->getItem() );
	}

	private function generateChangeOps() {
		$this->generateLabelsChangeOps();
		$this->generateDescriptionsChangeOps();
		$this->generateAliasesChangeOps();
		$this->generateSitelinksChangeOps();
		$this->generateClaimsChangeOps();
	}

	private function generateLabelsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getLabels() as $langCode => $label ){
			$toLabel = $this->toItemContent->getItem()->getLabel( $langCode );
			if( $toLabel === false || $toLabel === $label ){
				$this->fromChangeOps->add( new ChangeOpLabel( $langCode, null ) );
				$this->toChangeOps->add( new ChangeOpLabel( $langCode, $label ) );
			} else {
				//todo add the option to merge conflicting labels into the aliases
				if( !in_array( 'label', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting labels for language {$langCode}" );
				}
			}
		}
	}

	private function generateDescriptionsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getDescriptions() as $langCode => $desc ){
			$toDescription = $this->toItemContent->getItem()->getDescription( $langCode );
			if( $toDescription === false || $toDescription === $desc ){
				$this->fromChangeOps->add( new ChangeOpDescription( $langCode, null ) );
				$this->toChangeOps->add( new ChangeOpDescription( $langCode, $desc ) );
			} else {
				//todo add the option to ignore description conflicts, or prioritise one
				if( !in_array( 'description', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting descriptions for language {$langCode}" );
				}
			}
		}
	}

	private function generateAliasesChangeOps() {
		foreach( $this->fromItemContent->getItem()->getAllAliases() as $langCode => $aliases ){
			$this->fromChangeOps->add( new ChangeOpAliases( $langCode, $aliases, 'remove' ) );
			$this->toChangeOps->add( new ChangeOpAliases( $langCode, $aliases, 'add' ) );
		}
	}

	private function generateSitelinksChangeOps() {
		foreach( $this->fromItemContent->getItem()->getSimpleSiteLinks() as $simpleSiteLink ){
			$siteId = $simpleSiteLink->getSiteId();
			if( !$this->toItemContent->getItem()->hasLinkToSite( $siteId ) ){
				$this->fromChangeOps->add( new ChangeOpSiteLink( $siteId, null ) );
				$this->toChangeOps->add( new ChangeOpSiteLink( $siteId, $simpleSiteLink->getPageName() ) );
			} else {
				if( !in_array( 'sitelink', $this->ignoreConflicts ) ){
					throw new ChangeOpException( "Conflicting sitelinks for {$siteId}" );
				}
			}
		}
	}

	private function generateClaimsChangeOps() {
		foreach( $this->fromItemContent->getItem()->getClaims() as $fromClaim ){
			$this->fromChangeOps->add( new ChangeOpMainSnak(
				$fromClaim->getGuid(),
				null,
				new ClaimGuidGenerator( $this->fromItemContent->getItem()->getId() )
			) );

			$toClaim = clone $fromClaim;
			$toClaim->setGuid( null );

			$this->toChangeOps->add( new ChangeOpClaim(
				$toClaim ,
				new ClaimGuidGenerator( $this->toItemContent->getItem()->getId() )
			) );
		}
	}

}