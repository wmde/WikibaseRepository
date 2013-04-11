<?php

/**
 * This file holds registration of experimental features part of the Wikibase Repo extension.
 *
 * This file is NOT an entry point the Wikibase extension. Use Wikibase.php.
 * It should furthermore not be included from outside the extension.
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
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 */

if ( !defined( 'WB_VERSION' ) || !defined( 'WB_EXPERIMENTAL_FEATURES' ) ) {
	die( 'Not an entry point.' );
}

// Include the Database component if that hasn't been done yet.
if ( !defined( 'WIKIBASE_DATABASE_VERSION' ) && defined( 'WB_EXPERIMENTAL_FEATURES' ) ) {
	@include_once( __DIR__ . '/../../Database/Database.php' );
}

// Include the Query component if that hasn't been done yet.
if ( !defined( 'WIKIBASE_QUERY_VERSION' ) && defined( 'WB_EXPERIMENTAL_FEATURES' ) ) {
	@include_once( __DIR__ . '/../../QueryEngine/QueryEngine.php' );
}

$dir = __DIR__ . '/../';

$wgAutoloadClasses['Wikibase\Api\RemoveQualifiers'] 	= $dir . 'includes/api/RemoveQualifiers.php';
$wgAutoloadClasses['Wikibase\Api\SetQualifier'] 		= $dir . 'includes/api/SetQualifier.php';
$wgAutoloadClasses['Wikibase\Api\SetStatementRank']		= $dir . 'includes/api/SetStatementRank.php';

$wgAutoloadClasses['SpecialEntityData'] 				= $dir . 'includes/specials/SpecialEntityData.php';

$wgAutoloadClasses['Wikibase\QueryContent'] 			= $dir . 'includes/content/QueryContent.php';
$wgAutoloadClasses['Wikibase\QueryHandler'] 			= $dir . 'includes/content/QueryHandler.php';

unset( $dir );

$wgAPIModules['wbremovequalifiers'] 				= 'Wikibase\Api\RemoveQualifiers';
$wgAPIModules['wbsetqualifier'] 					= 'Wikibase\Api\SetQualifier';
$wgAPIModules['wbsetstatementrank'] 				= 'Wikibase\Api\SetStatementRank';

$wgSpecialPages['EntityData'] 						= 'SpecialEntityData';

$wgContentHandlers[CONTENT_MODEL_WIKIBASE_QUERY] 	= '\Wikibase\QueryHandler';

/**
 * Hook to add PHPUnit test cases.
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
 *
 * @since 0.3
 *
 * @param array &$files
 *
 * @return boolean
 */
$wgHooks['UnitTestsList'][] = function( array &$files ) {
	// @codeCoverageIgnoreStart
	$testFiles = array(
		'api/RemoveQualifiers',
		'api/SetStatementRank',
		'api/SetQualifier',

		'content/QueryContent',
		'content/QueryHandler',

		'specials/SpecialEntityData',

	);

	foreach ( $testFiles as $file ) {
		$files[] = __DIR__ . '/../tests/phpunit/includes/' . $file . 'Test.php';
	}

	return true;
	// @codeCoverageIgnoreEnd
};
