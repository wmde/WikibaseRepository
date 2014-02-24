<?php

/**
 * Entry point for the Wikibase Repository extension.
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( defined( 'WB_VERSION' ) ) {
	// Do not initialize more than once.
	return 1;
}

define( 'WB_VERSION', '0.5 alpha'
	. ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ? '/experimental' : '' ) );

if ( version_compare( $GLOBALS['wgVersion'], '1.20c', '<' ) ) { // Needs to be 1.20c because version_compare() works in confusing ways.
	die( "<b>Error:</b> Wikibase requires MediaWiki 1.20 or above.\n" );
}

/**
 * @deprecated since 0.5 This is a global registry that provides no control over object lifecycle
 */
$GLOBALS['wgValueParsers'] = array();

// Include the WikibaseLib extension if that hasn't been done yet, since it's required for Wikibase to work.
if ( !defined( 'WBL_VERSION' ) ) {
	include_once( __DIR__ . '/../lib/WikibaseLib.php' );
}

if ( !defined( 'WBL_VERSION' ) ) {
	throw new Exception( 'Wikibase depends on the WikibaseLib extension.' );
}

call_user_func( function() {
	global $wgExtensionCredits, $wgGroupPermissions, $wgExtensionMessagesFiles;
	global $wgAPIModules, $wgSpecialPages, $wgSpecialPageGroups, $wgHooks, $wgContentHandlers;
	global $wgWBStores, $wgWBRepoSettings, $wgResourceModules, $wgValueParsers;

	$wgExtensionCredits['wikibase'][] = array(
		'path' => __DIR__,
		'name' => 'Wikibase Repository',
		'version' => WB_VERSION,
		'author' => array(
			'The Wikidata team',
		),
		'url' => 'https://www.mediawiki.org/wiki/Extension:Wikibase',
		'descriptionmsg' => 'wikibase-desc'
	);

	// constants
	define( 'CONTENT_MODEL_WIKIBASE_ITEM', "wikibase-item" );
	define( 'CONTENT_MODEL_WIKIBASE_PROPERTY', "wikibase-property" );

	// rights
	// names should be according to other naming scheme
	$wgGroupPermissions['*']['item-override']		= true;
	$wgGroupPermissions['*']['item-create']			= true;
	$wgGroupPermissions['*']['item-remove']			= true;
	$wgGroupPermissions['*']['item-merge']			= true;
	$wgGroupPermissions['*']['property-override']	= true;
	$wgGroupPermissions['*']['property-create']		= true;
	$wgGroupPermissions['*']['property-remove']		= true;
	$wgGroupPermissions['*']['alias-update']		= true;
	$wgGroupPermissions['*']['alias-remove']		= true;
	$wgGroupPermissions['*']['sitelink-remove']		= true;
	$wgGroupPermissions['*']['sitelink-update']		= true;
	$wgGroupPermissions['*']['linktitles-update']	= true;
	$wgGroupPermissions['*']['label-remove']		= true;
	$wgGroupPermissions['*']['label-update']		= true;
	$wgGroupPermissions['*']['description-remove']	= true;
	$wgGroupPermissions['*']['description-update']	= true;

	// i18n
	$wgExtensionMessagesFiles['Wikibase'] 				= __DIR__ . '/Wikibase.i18n.php';
	$wgExtensionMessagesFiles['WikibaseAlias'] 			= __DIR__ . '/Wikibase.i18n.alias.php';
	$wgExtensionMessagesFiles['WikibaseNS'] 			= __DIR__ . '/Wikibase.i18n.namespaces.php';

	// This is somewhat hackish, make WikibaseValueParserBuilders, analogous to WikibaseValueFormatterBuilders
	$wgValueParsers['wikibase-entityid'] = function( ValueParsers\ParserOptions $options ) {
		//TODO: make ID builders configurable.
		$builders = \Wikibase\DataModel\Entity\BasicEntityIdParser::getBuilders();
		return new \Wikibase\Lib\EntityIdValueParser(
			new \Wikibase\DataModel\Entity\DispatchingEntityIdParser( $builders, $options ),
			$options
		);
	};

	$wgValueParsers['quantity'] = function( ValueParsers\ParserOptions $options ) {
		$unlocalizer = new Wikibase\Lib\MediaWikiNumberUnlocalizer();
		return new \ValueParsers\QuantityParser(
			new \ValueParsers\DecimalParser( $options, $unlocalizer ),
			$options );
	};

	$wgValueParsers['bool'] = 'ValueParsers\BoolParser';
	$wgValueParsers['float'] = 'ValueParsers\FloatParser';
	$wgValueParsers['globecoordinate'] = 'ValueParsers\GlobeCoordinateParser';
	$wgValueParsers['int'] = 'ValueParsers\IntParser';
	$wgValueParsers['null'] = 'ValueParsers\NullParser';
	$wgValueParsers['decimal'] = 'ValueParsers\DecimalParser';

	// API module registration
	$wgAPIModules['wbgetentities'] 						= 'Wikibase\Api\GetEntities';
	$wgAPIModules['wbsetlabel'] 						= 'Wikibase\Api\SetLabel';
	$wgAPIModules['wbsetdescription'] 					= 'Wikibase\Api\SetDescription';
	$wgAPIModules['wbsearchentities'] 					= 'Wikibase\Api\SearchEntities';
	$wgAPIModules['wbsetaliases'] 						= 'Wikibase\Api\SetAliases';
	$wgAPIModules['wbeditentity'] 						= 'Wikibase\Api\EditEntity';
	$wgAPIModules['wblinktitles'] 						= 'Wikibase\Api\LinkTitles';
	$wgAPIModules['wbsetsitelink'] 						= 'Wikibase\Api\SetSiteLink';
	$wgAPIModules['wbcreateclaim'] 						= 'Wikibase\Api\CreateClaim';
	$wgAPIModules['wbgetclaims'] 						= 'Wikibase\Api\GetClaims';
	$wgAPIModules['wbremoveclaims'] 					= 'Wikibase\Api\RemoveClaims';
	$wgAPIModules['wbsetclaimvalue'] 					= 'Wikibase\Api\SetClaimValue';
	$wgAPIModules['wbsetreference'] 					= 'Wikibase\Api\SetReference';
	$wgAPIModules['wbremovereferences'] 				= 'Wikibase\Api\RemoveReferences';
	$wgAPIModules['wbsetclaim'] 						= 'Wikibase\Api\SetClaim';
	$wgAPIModules['wbremovequalifiers']					= 'Wikibase\Api\RemoveQualifiers';
	$wgAPIModules['wbsetqualifier']						= 'Wikibase\Api\SetQualifier';
	$wgAPIModules['wbmergeitems']						= 'Wikibase\Api\MergeItems';
	$wgAPIModules['wbformatvalue']						= 'Wikibase\Api\FormatSnakValue';
	$wgAPIModules['wbparsevalue']						= 'Wikibase\Api\ParseValue';

	// Special page registration
	$wgSpecialPages['NewItem'] 							= 'Wikibase\Repo\Specials\SpecialNewItem';
	$wgSpecialPages['NewProperty'] 						= 'Wikibase\Repo\Specials\SpecialNewProperty';
	$wgSpecialPages['ItemByTitle'] 						= 'Wikibase\Repo\Specials\SpecialItemByTitle';
	$wgSpecialPages['ItemDisambiguation'] 				= 'Wikibase\Repo\Specials\SpecialItemDisambiguation';
	$wgSpecialPages['ItemsWithoutSitelinks']			= 'Wikibase\Repo\Specials\SpecialItemsWithoutSitelinks';
	$wgSpecialPages['SetLabel'] 						= 'Wikibase\Repo\Specials\SpecialSetLabel';
	$wgSpecialPages['SetDescription'] 					= 'Wikibase\Repo\Specials\SpecialSetDescription';
	$wgSpecialPages['SetAliases'] 						= 'Wikibase\Repo\Specials\SpecialSetAliases';
	$wgSpecialPages['SetSiteLink']						= 'Wikibase\Repo\Specials\SpecialSetSiteLink';
	$wgSpecialPages['EntitiesWithoutLabel'] 			= 'Wikibase\Repo\Specials\SpecialEntitiesWithoutLabel';
	$wgSpecialPages['EntitiesWithoutDescription']		= 'Wikibase\Repo\Specials\SpecialEntitiesWithoutDescription';
	$wgSpecialPages['ListDatatypes']					= 'Wikibase\Repo\Specials\SpecialListDatatypes';
	$wgSpecialPages['DispatchStats']					= 'Wikibase\Repo\Specials\SpecialDispatchStats';
	$wgSpecialPages['EntityData'] 						= 'Wikibase\Repo\Specials\SpecialEntityData';
	$wgSpecialPages['MyLanguageFallbackChain'] 			= 'Wikibase\Repo\Specials\SpecialMyLanguageFallbackChain';

	// Special page groups
	$wgSpecialPageGroups['NewItem']						= 'wikibaserepo';
	$wgSpecialPageGroups['NewProperty']					= 'wikibaserepo';
	$wgSpecialPageGroups['ItemByTitle']					= 'wikibaserepo';
	$wgSpecialPageGroups['ItemDisambiguation']			= 'wikibaserepo';
	$wgSpecialPageGroups['ItemsWithoutSitelinks']		= 'wikibaserepo';
	$wgSpecialPageGroups['SetLabel']					= 'wikibaserepo';
	$wgSpecialPageGroups['SetDescription']				= 'wikibaserepo';
	$wgSpecialPageGroups['SetAliases']					= 'wikibaserepo';
	$wgSpecialPageGroups['SetSiteLink']					= 'wikibaserepo';
	$wgSpecialPageGroups['EntitiesWithoutLabel']		= 'wikibaserepo';
	$wgSpecialPageGroups['EntitiesWithoutDescription']	= 'wikibaserepo';
	$wgSpecialPageGroups['ListDatatypes']				= 'wikibaserepo';
	$wgSpecialPageGroups['DispatchStats']				= 'wikibaserepo';
	$wgSpecialPageGroups['EntityData']					= 'wikibaserepo';
	$wgSpecialPageGroups['MyLanguageFallbackChain'] 	= 'wikibaserepo';

	// Hooks
	$wgHooks['BeforePageDisplay'][]						= 'Wikibase\RepoHooks::onBeforePageDisplay';
	$wgHooks['LoadExtensionSchemaUpdates'][] 			= 'Wikibase\RepoHooks::onSchemaUpdate';
	$wgHooks['UnitTestsList'][] 						= 'Wikibase\RepoHooks::registerUnitTests';
	$wgHooks['NamespaceIsMovable'][]					= 'Wikibase\RepoHooks::onNamespaceIsMovable';
	$wgHooks['NewRevisionFromEditComplete'][]			= 'Wikibase\RepoHooks::onNewRevisionFromEditComplete';
	$wgHooks['SkinTemplateNavigation'][] 				= 'Wikibase\RepoHooks::onPageTabs';
	$wgHooks['RecentChange_save'][]						= 'Wikibase\RepoHooks::onRecentChangeSave';
	$wgHooks['ArticleDeleteComplete'][] 				= 'Wikibase\RepoHooks::onArticleDeleteComplete';
	$wgHooks['ArticleUndelete'][]						= 'Wikibase\RepoHooks::onArticleUndelete';
	$wgHooks['GetPreferences'][]						= 'Wikibase\RepoHooks::onGetPreferences';
	$wgHooks['LinkBegin'][] 							= 'Wikibase\RepoHooks::onLinkBegin';
	$wgHooks['OutputPageBodyAttributes'][] 				= 'Wikibase\RepoHooks::onOutputPageBodyAttributes';
	//FIXME: handle other types of entities with autocomments too!
	$wgHooks['FormatAutocomments'][]					= array( 'Wikibase\RepoHooks::onFormat', array( CONTENT_MODEL_WIKIBASE_ITEM, "wikibase-item" ) );
	$wgHooks['FormatAutocomments'][]					= array( 'Wikibase\RepoHooks::onFormat', array( CONTENT_MODEL_WIKIBASE_PROPERTY, "wikibase-property" ) );
	$wgHooks['PageHistoryLineEnding'][]					= 'Wikibase\RepoHooks::onPageHistoryLineEnding';
	$wgHooks['WikibaseRebuildData'][] 					= 'Wikibase\RepoHooks::onWikibaseRebuildData';
	$wgHooks['WikibaseDeleteData'][] 					= 'Wikibase\RepoHooks::onWikibaseDeleteData';
	$wgHooks['ApiCheckCanExecute'][] 					= 'Wikibase\RepoHooks::onApiCheckCanExecute';
	$wgHooks['SetupAfterCache'][] 						= 'Wikibase\RepoHooks::onSetupAfterCache';
	$wgHooks['ShowSearchHit'][] 						= 'Wikibase\RepoHooks::onShowSearchHit';
	$wgHooks['TitleGetRestrictionTypes'][]				= 'Wikibase\RepoHooks::onTitleGetRestrictionTypes';
	$wgHooks['AbuseFilter-contentToString'][]			= 'Wikibase\RepoHooks::onAbuseFilterContentToString';
	$wgHooks['SpecialPage_reorderPages'][]				= 'Wikibase\RepoHooks::onSpecialPage_reorderPages';
	$wgHooks['OutputPageParserOutput'][]				= 'Wikibase\RepoHooks::onOutputPageParserOutput';
	$wgHooks['ContentModelCanBeUsedOn'][]				= 'Wikibase\RepoHooks::onContentModelCanBeUsedOn';
	$wgHooks['OutputPageBeforeHTML'][]				= 'Wikibase\RepoHooks::onOutputPageBeforeHTML';

	// Resource Loader Modules:
	$wgResourceModules = array_merge( $wgResourceModules, include( __DIR__ . "/resources/Resources.php" ) );

	// register hooks and handlers
	$wgContentHandlers[CONTENT_MODEL_WIKIBASE_ITEM] 		= '\Wikibase\ItemHandler';
	$wgContentHandlers[CONTENT_MODEL_WIKIBASE_PROPERTY] 	= '\Wikibase\PropertyHandler';

	$wgWBStores = array();

	$wgWBStores['sqlstore'] = 'Wikibase\SqlStore';

	$wgWBRepoSettings = array_merge(
		require( __DIR__ . '/../lib/config/WikibaseLib.default.php' ),
		require( __DIR__ . '/config/Wikibase.default.php' )
	);

	if ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ) {
		include_once( __DIR__ . '/config/Wikibase.experimental.php' );
	}
} );

$wgWBSettings =& $wgWBRepoSettings; // B/C alias
