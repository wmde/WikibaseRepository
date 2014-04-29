<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Summary;

/**
 * Class for sitelink change operation
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Michał Łazowik
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ChangeOpSiteLink extends ChangeOpBase {

	/**
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $siteId;

	/**
	 * @since 0.4
	 *
	 * @var string|null
	 */
	protected $pageName;

	/**
	 * @since 0.5
	 *
	 * @var ItemId[]|null
	 */
	 protected $badges;

	/**
	 * @since 0.4
	 *
	 * @param string $siteId
	 * @param string|null $pageName Null in case the link with the provided siteId should be removed
	 * @param array|null $badges
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $siteId, $pageName, $badges = null ) {
		if ( !is_string( $siteId ) ) {
			throw new InvalidArgumentException( '$siteId needs to be a string' );
		}

		if ( !is_string( $pageName ) && $pageName !== null ) {
			throw new InvalidArgumentException( '$linkPage needs to be a string or null' );
		}

		if ( !is_array( $badges ) && $badges !== null ) {
			throw new InvalidArgumentException( '$badges need to be an array of ItemIds or null' );
		}

		if ( $badges !== null ) {
			$this->validateBadges( $badges );

			$badges = $this->removeDuplicateBadges( $badges );
		}

		$this->siteId = $siteId;
		$this->pageName = $pageName;
		$this->badges = $badges;
	}

	/**
	 * @param array $badges
	 *
	 * @throws InvalidArgumentException
	 */
	private function validateBadges( array $badges ) {
		$badgeItems = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'badgeItems' );

		foreach ( $badges as $badge ) {
			if ( !( $badge instanceof ItemId ) ) {
				throw new InvalidArgumentException( '$badge needs to be an ItemId' );
			}

			if ( !array_key_exists( $badge->getPrefixedId(), $badgeItems ) ) {
				throw new InvalidArgumentException( 'Only items specified in the config can be badges' );
			}
		}
	}

	/**
	 * Removes duplicate badges from the array and returns the new list of badges.
	 *
	 * @param ItemId[] $badges
	 *
	 * @return ItemId[]
	 */
	private function removeDuplicateBadges( array $badges ) {
		$final = array();
		foreach ( $badges as $badge ) {
			if ( !$this->containsBadge( $final, $badge ) ) {
				$final[] = $badge;
			}
		}
		return $final;
	}

	/**
	 * Checks whether the list of badges contains the badge.
	 *
	 * @param ItemId[] $badges
	 * @param ItemId $badge
	 *
	 * @return boolean
	 */
	private function containsBadge( array $badges, ItemId $badge ) {
		foreach ( $badges as $other ) {
			if ( $badge->equals( $other ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @see ChangeOp::apply()
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		if ( !( $entity instanceof Item ) ) {
			throw new InvalidArgumentException( 'ChangeOpSiteLink can only be applied to Item instances' );
		}

		if ( ( $this->pageName === null && $this->badges === null ) || $this->pageName === '' ) {
			if ( $entity->hasLinkToSite( $this->siteId ) ) {
				$this->updateSummary( $summary, 'remove', $this->siteId, $entity->getSiteLink( $this->siteId )->getPageName() );
				$entity->removeSiteLink( $this->siteId );
			} else {
				//TODO: throw error, or ignore silently?
			}
		} else {
			$commentArgs = array();

			if ( $this->pageName === null ) {
				// If page name is not set (but badges are) make sure that it remains intact
				if ( $entity->hasLinkToSite( $this->siteId ) ) {
					$pageName = $entity->getSiteLink( $this->siteId )->getPageName();
				} else {
					throw new InvalidArgumentException( 'The sitelink does not exist' );
				}
			} else {
				$pageName = $this->pageName;
				$commentArgs[] = $pageName;
			}

			if ( $this->badges === null ) {
				// If badges are not set in the change make sure that they remain intact
				if ( $entity->hasLinkToSite( $this->siteId ) ) {
					$badges = $entity->getSiteLink( $this->siteId )->getBadges();
				} else {
					$badges = array();
				}
			} else {
				$badges = $this->badges;
				$commentArgs[] = $badges;
			}

			$action = $entity->hasLinkToSite( $this->siteId ) ? 'set' : 'add';

			if ( $this->pageName === null ) {
				$action .= '-badges';
			} elseif ( $this->badges !== null ) {
				$action .= '-both';
			}

			$this->updateSummary( $summary, $action, $this->siteId, $commentArgs );

			$entity->addSiteLink( new SiteLink( $this->siteId, $pageName, $badges ) );
		}

		return true;
	}
}
