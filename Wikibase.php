<?php

/**
 * Initialization file for the Wikibase extension.
 *
 * Documentation:	 		https://www.mediawiki.org/wiki/Extension:Wikibase
 * Support					https://www.mediawiki.org/wiki/Extension_talk:Wikibase
 * Source code:				https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/extensions/WikidataRepo.git
 *
 * @file Wikibase.php
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Werner < daniel.werner at wikimedia.de >
 * @author Daniel Kinzler
 */

/**
 * This documentation group collects source code files belonging to Wikibase.
 *
 * @defgroup Wikibase Wikibase
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( version_compare( $wgVersion, '1.19c', '<' ) ) { // Needs to be 1.19c because version_compare() works in confusing ways.
	die( '<b>Error:</b> Wikibase requires MediaWiki 1.19 or above.' );
}

// TODO: enable
//if ( !array_key_exists( 'CountryNames', $wgAutoloadClasses ) ) { // No version constant to check against :/
//	die( '<b>Error:</b> Wikibase depends on the <a href="https://www.mediawiki.org/wiki/Extension:CLDR">CLDR</a> extension.' );
//}

define( 'WB_VERSION', '0.1 alpha' );

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Wikibase',
	'version' => WB_VERSION,
	'author' => array(
		'The Wikidata team', // TODO: link?
	),
	'url' => 'https://www.mediawiki.org/wiki/Extension:Wikibase',
	'descriptionmsg' => 'wikibase-desc'
);

$dir = dirname( __FILE__ ) . '/';



// i18n
$wgExtensionMessagesFiles['Wikibase'] 				= $dir . 'Wikibase.i18n.php';
$wgExtensionMessagesFiles['WikibaseAlias'] 			= $dir . 'Wikibase.i18n.alias.php';
$wgExtensionMessagesFiles['WikibaseNS'] 			= $dir . 'Wikibase.i18n.ns.php';



// Autoloading
$wgAutoloadClasses['WBSettings'] 					= $dir . 'Wikibase.settings.php';
$wgAutoloadClasses['WikibaseHooks'] 				= $dir . 'Wikibase.hooks.php';

// includes
$wgAutoloadClasses['WikibaseContentHandler'] 		= $dir . 'includes/WikibaseContentHandler.php';
$wgAutoloadClasses['WikibaseDifferenceEngine'] 		= $dir . 'includes/WikibaseDifferenceEngine.php';
$wgAutoloadClasses['WikibaseUtils'] 				= $dir . 'includes/WikibaseUtils.php';
$wgAutoloadClasses['WikibaseItem'] 					= $dir . 'includes/WikibaseItem.php';
$wgAutoloadClasses['WikibaseEntity'] 				= $dir . 'includes/WikibaseEntity.php';
$wgAutoloadClasses['WikibaseItemStructuredSave'] 	= $dir . 'includes/WikibaseItemStructuredSave.php';
$wgAutoloadClasses['WikibaseItemView'] 				= $dir . 'includes/WikibaseItemView.php';

// includes/actions
$wgAutoloadClasses['WikibaseViewItemAction'] 		= $dir . 'includes/actions/WikibaseViewItemAction.php';
$wgAutoloadClasses['WikibaseEditItemAction'] 		= $dir . 'includes/actions/WikibaseEditItemAction.php';

// includes/api
$wgAutoloadClasses['ApiWikibaseGetItems'] 			= $dir . 'includes/api/ApiWikibaseGetItems.php';
$wgAutoloadClasses['ApiWikibaseGetItemId'] 			= $dir . 'includes/api/ApiWikibaseGetItemId.php';
$wgAutoloadClasses['ApiWikibaseGetSiteLinks'] 		= $dir . 'includes/api/ApiWikibaseGetSiteLinks.php';
$wgAutoloadClasses['ApiWikibaseSetLanguageAttribute'] = $dir . 'includes/api/ApiWikibaseSetLanguageAttribute.php';
$wgAutoloadClasses['ApiWikibaseDeleteLanguageAttribute'] = $dir . 'includes/api/ApiWikibaseDeleteLanguageAttribute.php';
$wgAutoloadClasses['ApiWikibaseModifyItem'] 		= $dir . 'includes/api/ApiWikibaseModifyItem.php';
$wgAutoloadClasses['ApiWikibaseLinkSite'] 			= $dir . 'includes/api/ApiWikibaseLinkSite.php';
$wgAutoloadClasses['ApiWikibaseSetAlias'] 			= $dir . 'includes/api/ApiWikibaseSetAlias.php';
$wgAutoloadClasses['ApiWikibaseSetItem'] 			= $dir . 'includes/api/ApiWikibaseSetItem.php';

// includes/specials
$wgAutoloadClasses['SpecialCreateItem'] 			= $dir . 'includes/specials/SpecialCreateItem.php';
$wgAutoloadClasses['SpecialItemByTitle'] 			= $dir . 'includes/specials/SpecialItemByTitle.php';
$wgAutoloadClasses['SpecialItemResolver'] 			= $dir . 'includes/specials/SpecialItemResolver.php';
$wgAutoloadClasses['SpecialItemByLabel'] 			= $dir . 'includes/specials/SpecialItemByLabel.php';



// API module registration
$wgAPIModules['wbgetitems'] 						= 'ApiWikibaseGetItems';
$wgAPIModules['wbgetitemid'] 						= 'ApiWikibaseGetItemId';
$wgAPIModules['wbsetlanguageattribute'] 			= 'ApiWikibaseSetLanguageAttribute';
$wgAPIModules['wbdeletelanguageattribute'] 			= 'ApiWikibaseDeleteLanguageAttribute';
$wgAPIModules['wbgetsitelinks'] 					= 'ApiWikibaseGetSiteLinks';
$wgAPIModules['wblinksite'] 						= 'ApiWikibaseLinkSite';
$wgAPIModules['wbsetalias'] 						= 'ApiWikibaseSetAlias';
$wgAPIModules['wbsetitem'] 							= 'ApiWikibaseSetItem';



// Special page registration
//$wgSpecialPages['CreateItem'] 						= 'SpecialCreateItem';
//$wgSpecialPages['ItemByTitle'] 						= 'SpecialItemByTitle';
//$wgSpecialPages['ItemByLabel'] 						= 'SpecialItemByLabel';

// Hooks
$wgHooks['LoadExtensionSchemaUpdates'][] 			= 'WikibaseHooks::onSchemaUpdate';
$wgHooks['UnitTestsList'][] 						= 'WikibaseHooks::registerUnitTests';
$wgHooks['PageContentLanguage'][]					= 'WikibaseHooks::onPageContentLanguage';
$wgHooks['ResourceLoaderTestModules'][]				= 'WikibaseHooks::onResourceLoaderTestModules';
$wgHooks['AbortMove'][]								= 'WikibaseHooks::onAbortMove';


// Resource loader modules
$moduleTemplate = array(
	'localBasePath' => dirname( __FILE__ ) . '/resources',
	'remoteExtPath' => preg_replace( '%^.*[/\\\](.+)$%', '$1', dirname( __FILE__ ) ) . '/resources'
);

$wgResourceModules['wikibase'] = $moduleTemplate + array(
	'scripts' => array(
		'wikibase.js',
		'wikibase.Site.js',
		'wikibase.ui.js',
		'wikibase.ui.PropertyEditTool.js',
		'wikibase.ui.PropertyEditTool.Toolbar.js',
		'wikibase.ui.PropertyEditTool.Toolbar.Group.js',
		'wikibase.ui.PropertyEditTool.Toolbar.EditGroup.js',
		'wikibase.ui.PropertyEditTool.Toolbar.Label.js',
		'wikibase.ui.PropertyEditTool.Toolbar.Button.js',
		'wikibase.ui.PropertyEditTool.Toolbar.Tooltip.js',
		'wikibase.ui.PropertyEditTool.EditableValue.js',
		'wikibase.ui.PropertyEditTool.EditableValue.Interface.js',
		'wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.js',
		'wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.js',
		'wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.js',
		'wikibase.ui.PropertyEditTool.EditableDescription.js',
		'wikibase.ui.PropertyEditTool.EditableLabel.js',
		'wikibase.ui.PropertyEditTool.EditableSiteLink.js',
		'wikibase.ui.HeadingEditTool.js',
		'wikibase.ui.DescriptionEditTool.js',
		'wikibase.ui.SiteLinksEditTool.js',
		'wikibase.startup.js'
	),
	'styles' => array(
		'wikibase.ui.PropertyEditTool.css'
	),
	'dependencies' => array(
		'jquery.tipsy',
		'jquery.ui.autocomplete',
		'mediawiki.legacy.shared',
		'mediawiki.Title'
	),
	'messages' => array(
		'wikibase-sitelinks',
		'wikibase-cancel',
		'wikibase-edit',
		'wikibase-save',
		'wikibase-add',
		'wikibase-label-edit-placeholder',
		'wikibase-description-edit-placeholder',
		'wikibase-sitelink-site-edit-placeholder',
		'wikibase-sitelink-page-edit-placeholder',
		'wikibase-label-input-help-message',
		'wikibase-description-input-help-message',
		'wikibase-sitelinks-input-help-message',
		'wikibase-remove',
		'wikibase-propertyedittool-full',
		'wikibase-sitelinksedittool-full',
	)
);

unset( $moduleTemplate );

// register hooks and handlers
define( 'CONTENT_MODEL_WIKIBASE', 'wikibase' );
$wgContentHandlers[CONTENT_MODEL_WIKIBASE] = 'WikibaseContentHandler';

define( 'WB_NS_DATA', 100 );
define( 'WB_NS_DATA_TALK', 101 );

$wgExtraNamespaces[WB_NS_DATA] = 'Data';
$wgExtraNamespaces[WB_NS_DATA_TALK] = 'Data_talk';

$wgNamespaceContentModels[WB_NS_DATA] = CONTENT_MODEL_WIKIBASE;


$egWBSettings = array();
