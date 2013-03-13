<?php

namespace Wikibase;
use LoggedUpdateMaintenance;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for rebuilding the search key of the TermSQLCache.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.2
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class RebuildTermsSearchKey extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->mDescription = 'Rebuild the search key of the TermSQLCache';

		$this->addOption( 'only-missing', "Update only missing keys (per default, all keys are updated)" );
		$this->addOption( 'start-row', "The ID of the first row to update (useful for continuing aborted runs)", false, true );
		$this->addOption( 'batch-size', "Number of rows to update per database transaction (100 per default)", false, true );
	}

	/**
	 * @see LoggedUpdateMaintenance::doDBUpdates
	 *
	 * @return boolean
	 */
	public function doDBUpdates() {
		if ( !defined( 'WB_VERSION' ) ) {
			$this->output( "You need to have Wikibase enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$reporter = new \ObservableMessageReporter();
		$reporter->registerReporterCallback(
			array( $this, 'report' )
		);

		$table = StoreFactory::getStore( 'sqlstore' )->newTermCache();
		$builder = new TermSearchKeyBuilder( $table );
		$builder->setReporter( $reporter );

		$builder->setBatchSize( intval( $this->getOption( 'batch-size', 100 ) ) );
		$builder->setRebuildAll( !$this->getOption( 'only-missing', false ) );
		$builder->setFromId( intval( $this->getOption( 'start-row', 1 ) ) );

		$n = $builder->rebuildSearchKey();

		$this->output( "Done. Updated $n search keys.\n" );

		return true;
	}

	/**
	 * @see LoggedUpdateMaintenance::getUpdateKey
	 *
	 * @return string
	 */
	public function getUpdateKey() {
		return 'Wikibase\RebuildTermsSearchKey';
	}

	/**
	 * Outputs a message vis the output() method.
	 *
	 * @since 0.4
	 *
	 * @param $msg
	 */
	public function report( $msg ) {
		$this->output( "$msg\n" );
	}

}

$maintClass = 'Wikibase\RebuildTermsSearchKey';
require_once( RUN_MAINTENANCE_IF_MAIN );
