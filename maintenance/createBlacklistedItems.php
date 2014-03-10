<?php

namespace Wikibase;

use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for creating blacklisted items.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CreatedBlacklistedItems extends \Maintenance {

	public function __construct() {
		$this->mDescription = 'Created blacklisted items';

		parent::__construct();
	}

	public function execute() {
		global $wgUser;

		$user = $wgUser;
		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();

		if ( !defined( 'WB_VERSION' ) ) {
			$this->output( "You need to have Wikibase enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$report = function( $message ) {
			echo $message . "\n";
		};

		$items = array(
			//0 => 'Off-by-one error',
			1 => 'Universe',
			2 => 'Earth',
			3 => 'Life',
			4 => 'Death',
			5 => 'Human',
			8 => 'Happiness',
			13 => 'Triskaidekaphobia',
			23 => 'George Washington',
			24 => 'Jack Bauer',
			42 => 'Douglas Adams',
			80 => 'Tim Berners-Lee',
			666 => 'Number of the Beast',
			1337 => 'Leet',
			1868 => 'Paul Otlet',
			1971 => 'Imagine (song)',
			2001 => 'Stanley Kubrick',
			2012 => 'Maya calendar',
			2013 => 'Wikidata',
		);

		$report( 'Starting import...' );

		foreach ( $items as $id => $name ) {
			$report( "   Importing $name as item $id..." );

			$item = Item::newEmpty();

			$item->setId( $id );
			$item->setLabel( 'en', $name );
			$item->addSiteLink( new SimpleSiteLink( 'enwiki', $name ) );

			$store->saveEntity( $item, 'Import', $user, EDIT_NEW );
		}

		$report( 'Import completed.' );
	}

}

$maintClass = 'Wikibase\CreatedBlacklistedItems';
require_once( RUN_MAINTENANCE_IF_MAIN );
