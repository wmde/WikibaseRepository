<?php
/**
 * Wikibase Repo ResourceLoader modules
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {
	$moduleTemplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => '..' . substr( __DIR__, strlen( $GLOBALS['IP'] ) ),
		'position' => 'top' // reducing the time between DOM construction and JS initialisation
	);

	$modules = array(
		'wikibase.ui.entityViewInit' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.entityViewInit.js' // should probably be adjusted for more modularity
			),
			'dependencies' => array(
				'mediawiki.api',
				'mediawiki.user',
				'wikibase.ui.PropertyEditTool',
				'jquery.wikibase.entityview',
				'jquery.wikibase.toolbarcontroller',
				'jquery.wikibase.wbtooltip',
				'jquery.cookie',
				'jquery.wikibase.claimgrouplabelscroll',
				'wikibase.ui.initEntity',
				'wikibase.ui.initTermBox',
				'wikibase.ui.scrapeFormattedValues',
			),
			'messages' => array(
				'wikibase-statements',
				'wikibase-copyrighttooltip-acknowledge',
				'wikibase-anonymouseditwarning',
				'wikibase-entity-item',
				'wikibase-entity-property',
				'wikibase-restrictionedit-tooltip-message',
				'wikibase-blockeduser-tooltip-message',
			)
		),

		'wikibase.ui.initEntity' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.initEntity.js',
			),
			'dependencies' => array(
				'jquery.json',
				'wikibase',
				'wikibase.datamodel',
				'wikibase.serialization',
				'wikibase.serialization.entities',
				'wikibase.serialization.fetchedcontent',
				'wikibase.store.FetchedContent',
			),
		),

		'wikibase.ui.initTermBox' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.initTermBox.js',
			),
			'dependencies' => array(
				'jquery.wikibase.toolbar',
				'jquery.wikibase.toolbareditgroup',
				'mediawiki.Title',
				'wikibase',
				'wikibase.templates',
				'wikibase.ui.initEntity',
				'wikibase.ui.PropertyEditTool',
			),
			'messages' => array(
				'wikibase-terms',
			)
		),

		'wikibase.ui.scrapeFormattedValues' => $moduleTemplate + array(
				'scripts' => array(
					'wikibase.ui.scrapeFormattedValues.js',
				),
				'dependencies' => array(
					'wikibase',
					'wikibase.ui.initEntity',
				),
			),

		'wikibase.ui.entitysearch' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.ui.entitysearch.js',
			),
			'styles' => array(
				'themes/default/wikibase.ui.entitysearch.css',
			),
			'dependencies' => array(
				'jquery.eachchange',
				'jquery.wikibase.entityselector',
			),
			'messages' => array(
				'searchsuggest-containing',
			)
		),

		/* Wikibase special pages */

		'wikibase.special' => $moduleTemplate + array(
			'styles' => array(
				'wikibase.special/wikibase.special.css'
			),
			'dependencies' => array(
				'wikibase'
			)
		),

		'wikibase.special.entitiesWithout' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.special/wikibase.special.entitiesWithout.js'
			),
			'dependencies' => array(
				'wikibase.special',
				'jquery.ui.suggester'
			)
		),

		'wikibase.special.itemByTitle' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.special/wikibase.special.itemByTitle.js'
			),
			'dependencies' => array(
				'wikibase.special',
				'jquery.ui.suggester'
			)
		),

		'wikibase.special.itemDisambiguation' => $moduleTemplate + array(
			'scripts' => array(
				'wikibase.special/wikibase.special.itemDisambiguation.js'
			),
			'dependencies' => array(
				'wikibase.special',
				'jquery.ui.suggester'
			)
		),

		'wikibase.toc' => $moduleTemplate + array(
			'styles' => array(
				'themes/default/wikibase.toc.css',
			),
		),
	);

	if ( defined( 'ULS_VERSION' ) ) {
		$modules['wikibase.ui.initTermBox']['dependencies'][] = 'ext.uls.displaysettings';
		$modules['wikibase.ui.initTermBox']['dependencies'][] = 'jquery.uls.data';
		$modules['wikibase.special.itemDisambiguation']['dependencies'][] = 'jquery.uls.data';
		$modules['wikibase.special.entitiesWithout']['dependencies'][] = 'jquery.uls.data';
	}

	return $modules;
} );
// @codeCoverageIgnoreEnd
