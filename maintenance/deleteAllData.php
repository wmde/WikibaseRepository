<?php

namespace Wikibase;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : dirname( __FILE__ ) . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for deleting all Wikibase data.
 *
 * @since 0.1
 *
 * @file DeleteAllData.php
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DeleteAllData extends \Maintenance {

	public function __construct() {
		$this->mDescription = 'Delete the Wikidata data';

		parent::__construct();
	}

	public function execute() {
		echo "Are you really really sure you want to delete all the Wikibase data?? If so, type YES\n";

		if ( $this->readconsole() !== 'YES' ) {
			return;
		}

		echo 'Deleting revisions from Data NS...';

		$dbw = wfGetDB( DB_MASTER );

		$dbw->deleteJoin(
			'revision', 'page',
			'rev_page', 'page_id',
			array( 'page_namespace' => WB_NS_DATA )
		);

		echo "done!\n";

		echo 'Deleting pages from Data NS...';

		$dbw->delete(
			'page',
			array( 'page_namespace' => WB_NS_DATA )
		);

		echo "done!\n";

		$tables = array(
			'wb_changes',
		);

		if ( defined( 'WB_VERSION' ) ) {
			$tables = array_merge( $tables, array(
				'wb_items',
				'wb_items_per_site',
				'wb_texts_per_lang',
				'wb_aliases',
			) );
		}

		if ( defined( 'WBC_VERSION' ) ) {
			$tables = array_merge( $tables, array(
				'wbc_local_items',
			) );
		}

		foreach ( $tables as $table ) {
			echo "Truncating table $table...";

			$dbw->query( 'TRUNCATE TABLE ' . $dbw->tableName( $table ) );

			echo "done!\n";
		}

		echo <<<EOT


	                 ......                             ..          ,,
                 ..=~..                             ZD.   ....:=,.
                 ..:++=..     .,.     .:        .....M....,=+++...
                 ...=+++~......:~.....~= ..   ...~==.7.:+++++=.. .
                  . :+++++~.. .,+=...,++...  ..~++=..:++++++~...
                    .+++++++~..:++=..~++,.. .:++++~===+++++=.
                    .~++++++++~:++++:=++=..~++++++++:=++++:..
           ....     ..++++++++++++=+M7IM~+++++=+=+++8++++:.
           .=:...... .,++++++++++:M:::::~D~~:M...I++M+++:.
          ...,=+=,... .=++++++++++++?N ......M. .?++I++=..
            ...:+++=,...+++++++++O.M..  .O= ,. +..++~+=.
        .MD. ....+++++==~+++++++:...M,M,NMMMMM, ...~=+,..           ....
     .=.$:::~.....+++++++++++++++M.7M.MMMMMMMMM.   Z+~.......      ..,==..
     .M::::::M... .~+++++++++++++++.7MMMMMMMMMM.   M+,,~==+,.....~=++=.
     .M:::::::?..:=+++++++++++++++=, .:MMMMMMMM  ..8+++++:...,==+++=...
       M~::::::M...=+++++++++++++++=,...MMMMMMN ..?7=+++,.~+++++=:...
       .M:::~~~8....,++++++++++++++=M   MMMMMMM...77N+++++++++=,..
       ...:+=NOM. ....~+++++++++==I++Z  .?MMMN  .Z7I7=++++++=,..
            ..?OZ~++++++++++++~N==++++?,.......=O77I7O+++++~......
        ......,~OO?+++++++++I++++++++++DIZ8DDZ7777777O++++,..........
       ..~++++=::8ON=++++=N=+++++++++++=77777777777777~+++++++++++++++=:..
          ,+++++++DZ8=+8=+++++++++++++++D7I77777777777N+++++++++++++++++:.
          .~+++++++:8O?++++++++++++++++++II777777777777=++++++++++=:......
          ..=+++++++=DON=++++++++++++++++D77777777777I7=++++++~,..
          ...=++++++++8O8=+++++++++++++++OI777777777777=++=:......
            ..:++++++++~OO??++++++++++++~?I777777777777=++=,.
......... .....,++++++++~8ZN++++++++++++~77777777777777=++++:...................

                                  DELETED
                             ALL OF THE DATAS!

EOT;
	}

}

$maintClass = 'Wikibase\DeleteAllData';
require_once( RUN_MAINTENANCE_IF_MAIN );
