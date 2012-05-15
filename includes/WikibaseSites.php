<?php

/**
 * Interface to access sitelink related configuration. Right now this is read
 * from the configuration in WBSettings, but later on we might get this from the db.
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class WikibaseSites implements SeekableIterator {

	/**
	 * Holds the sites with the keys being their identifiers and the
	 * values being arrays with url, filepath, urlpath, type and group keys.
	 * @var array
	 */
	protected $sites;

	/**
	 * Holds the group names (keys) pointing to an array with the identifiers of the sites they contain.
	 * @var array
	 */
	protected $groups;

	/**
	 * Position of the iterator in the sites field.
	 * @var string
	 */
	protected $key;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param array $sites
	 * @param array $groups
	 */
	protected function __construct( array $sites, array $groups ) {
		$this->sites = $sites;
		$this->groups = $groups;
		$this->rewind();
	}

	/**
	 *
	 *
	 * @since 0.1
	 *
	 * @param array $siteGroups
	 * @param string $globalPathDefault
	 * @param string $globalUrlDefault
	 * @param string $globalTypeDefault
	 *
	 * @return WikibaseSites
	 */
	protected static function newFromConfig( array $siteGroups, $globalPathDefault, $globalUrlDefault, $globalTypeDefault ) {
		$groups = array();
		$sites = array();

		foreach ( $siteGroups as $groupName => $siteGroup ) {
			$groups[$groupName] = array_keys( $siteGroup['sites'] );

			$filePathDefault = array_key_exists( 'defaultSiteFilePath', $siteGroup ) ? $siteGroup['defaultSiteFilePath'] : $globalPathDefault;
			$urlPathDefault = array_key_exists( 'defaultSiteUrlPath', $siteGroup ) ? $siteGroup['defaultSiteUrlPath'] : $globalUrlDefault;
			$typeDefault = array_key_exists( 'defaultSiteType', $siteGroup ) ? $siteGroup['defaultSiteType'] : $globalTypeDefault;

			foreach ( $siteGroup['sites'] as $identifier => $data ) {
				if ( !is_array( $data ) ) {
					$data = array( 'url' => $data );
				}

				if ( !array_key_exists( 'filepath', $data ) ) {
					$data['filepath'] = $filePathDefault;
				}

				if ( !array_key_exists( 'urlpath', $data ) ) {
					$data['urlpath'] = $urlPathDefault;
				}

				if ( !array_key_exists( 'type', $data ) ) {
					$data['type'] = $typeDefault;
				}

				$data['group'] = $groupName;

				$sites[$identifier] = $data;
			}
		}

		return new static( $sites, $groups );
	}

	/**
	 * Returns an instance of WikibaseSites.
	 *
	 * @since 0.1
	 *
	 * @return WikibaseSites
	 */
	public static function singleton() {
		static $instance = false;

		if ( $instance === false ) {
			$instance = static::newFromConfig(
				WBSettings::get( 'siteIdentifiers' ),
				WBSettings::get( 'defaultSiteUrlPath' ),
				WBSettings::get( 'defaultSiteFilePath' ),
				WBSettings::get( 'defaultSiteType' )
			);
		}

		return $instance;
	}

	/**
	 * Returns all the site identifiers.
	 * Optionally only those belonging to the specified group.
	 *
	 * @since 0.1
	 *
	 * @param string|null $groupName
	 *
	 * @return array
	 * @throws MWException
	 */
	public function getIdentifiers( $groupName = null ) {
		if ( is_null( $groupName ) ) {
			if ( !array_key_exists( $groupName, $this->groups ) ) {
				throw new MWException( "No site group with name '$groupName' exists" );
			}

			return $this->groups[$groupName];
		}
		else {
			return array_keys( $this->sites );
		}
	}

	/**
	 * Returns a WikibaseSites containing only the sites of the specified group.
	 *
	 * @since 0.1
	 *
	 * @param string $groupName
	 *
	 * @return WikibaseSites
	 */
	public function getGroup( $groupName ) {
		if ( array_key_exists( $groupName, $this->groups ) ) {
			$sites = array();

			foreach ( $this->groups[$groupName] as $siteId ) {
				$sites[$siteId] = $this->sites[$siteId];
			}
		}
		else {
			$sites = array();
		}

		return new static(
			$sites,
			array( $groupName )
		);
	}

	/**
	 * Returns the site with the provided id.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 *
	 * @return WikibaseSite
	 * @throws MWException
	 */
	public function getSite( $siteId ) {
		if ( !array_key_exists( $siteId, $this->sites ) ) {
			throw new MWException( "There is no site with identifier '$siteId'." );
		}

		return WikibaseSite::newFromArray( $siteId, $this->sites[$siteId] );
	}

	/**
	 * Returns if the site with the provided id exists.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 *
	 * @return boolean
	 */
	public function hasSite( $siteId ) {
		return array_key_exists( $siteId, $this->sites );
	}

	/**
	 * Returns the full url for the specified site.
	 * A page can also be provided, which is then added to the url.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 *
	 * @return false|string
	 */
	public function getUrl( $siteId, $pageName = '' ) {
		if ( !array_key_exists( $siteId, $this->sites ) ) {
			return false;
		}

		return WikibaseSite::newFromArray( $siteId, $this->sites[$siteId] )->getUrl( $pageName );
	}

	/**
	 * Returns the full path for the specified site.
	 * A path can also be provided, which is then added to the base path.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $path
	 *
	 * @return false|string
	 */
	public function getPath( $siteId, $path = '' ) {
		if ( !array_key_exists( $siteId, $this->sites ) ) {
			return false;
		}

		return WikibaseSite::newFromArray( $siteId, $this->sites[$siteId] )->getPath( $path );
	}

	/**
	 * @return integer
	 */
	public function count() {
		return count( $this->sites );
	}

	/**
	 * @return boolean
	 */
	public function isEmpty() {
		return $this->sites === array();
	}

	/**
	 * @return WikibaseSite
	 */
	public function current() {
		return WikibaseSite::newFromArray( $this->key, $this->sites[$this->key] );
	}

	/**
	 * @return integer
	 */
	public function key() {
		return $this->key;
	}

	public function next() {
		next( $this->sites );
		$this->key = key( $this->sites );
	}

	public function rewind() {
		reset( $this->sites );
		$this->key = key( $this->sites );
	}

	/**
	 * @return boolean
	 */
	public function valid() {
		return $this->key !== false && isset( $this->sites[$this->key] );
	}

	/**
	 * @param string $position
	 * @throws MWException
	 */
	function seek( $position ) {
		if ( !array_key_exists( $position, $this->sites ) ) {
			throw new MWException( "Cannot seek to non-existing key '$position'." );
		}

		$this->key = $position;
	}

}