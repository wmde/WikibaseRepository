<?php

namespace Wikibase\Api;

use Site;
use SiteList;
use SiteStore;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 *
 * @author Daniel K
 * @author Adam Shorland
 * @author Marius Hoch < hoo@online.de >
 */
class SiteLinkTargetProvider {

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var array
	 */
	private $specialSiteGroups;

	/**
	 * @param SiteStore $siteStore
	 * @param array $specialSiteGroups
	 */
	public function __construct( SiteStore $siteStore, array $specialSiteGroups ) {
		$this->siteStore = $siteStore;
		$this->specialSiteGroups = $specialSiteGroups;
	}

	/**
	 * Returns the list of sites that is suitable as a sitelink target.
	 *
	 * @param string[] $groups sitelink groups to get
	 *
	 * @return SiteList
	 */
	public function getSiteList( array $groups ) {
		// As the special sitelink group actually just wraps multiple groups
		// into one we have to replace it with the actual groups
		$this->substituteSpecialSiteGroups( $groups );

		$sites = new SiteList();
		$allSites = $this->siteStore->getSites();

		/** @var Site $site */
		foreach ( $allSites as $site ) {
			if ( in_array( $site->getGroup(), $groups ) ) {
				$sites->append( $site );
			}
		}

		return $sites;
	}

	/**
	 * @param array &$groups
	 */
	private function substituteSpecialSiteGroups( &$groups ) {
		if ( !in_array( 'special', $groups ) ) {
			return;
		}

		$groups = array_diff( $groups, array( 'special' ) );
		$groups = array_merge( $groups, $this->specialSiteGroups );
	}
}
