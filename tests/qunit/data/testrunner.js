/**
 * Wikibase QUnit test environment
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater
 */

( function ( $, mw, wb, QUnit, undefined ) {
	'use strict';

	/**
	 * Reset basic configuration for each test(); Makes sure that global configuration stuff and cached stuff will be
	 * resetted befor each test. Also allows to set additional 'setup' and 'teardown' commands.
	 *
	 * @see QUnit.newMwEnvironment
	 *
	 * @param Object custom test environment variables according to QUnit.newMwEnvironment
	 *
	 * @example:
	 * <code>
	 * module( ..., newWbEnvironment() );
	 *
	 * test( ..., function () {
	 *     mw.config.set( 'wbSiteDetails', ... ); // just for this test
	 *     wikibase.getSites(); // will return sites set above
	 * } );
	 *
	 * test( ..., function () {
	 *     wikibase.getSites(); // will return {} since wbSiteDetails global is reset now
	 * } );
	 *
	 *
	 * module( ..., newMwEnvironment( { config: { 'wbSiteDetails', ... } } ) );
	 *
	 * test( ..., function () {
	 *     wikibase.getSites(); // from the set above
	 *     wikibase._siteList = null // removes cached values
	 *     mw.config.set( 'wbSiteDetails', ... );
	 * } );
	 *
	 * test( ..., function () {
	 *     wikibase.getSites(); // returns the ones set in module() again
	 * } );
	 *
	 *
	 * // additional setup and teardown:
	 * module( ..., newMwEnvironment( { setup: .. , teardown: ... } ) );
	 * </code>
	 */
	QUnit.newWbEnvironment = ( function () {

		return function ( custom ) {
			if ( custom === undefined ) {
				custom = {};
			}

			// init a new MW environment first, so we clean up the basic config stuff
			var mwEnv = new QUnit.newMwEnvironment( {
				config: custom.config,
				messages: custom.messages
			} );

			return {
				setup: function () {
					mwEnv.setup();

					// remove interfering global events
					$( wb ).off( 'newItemCreated' );
					$( wb ).off( 'startItemPageEditMode' );
					$( wb ).off( 'stopItemPageEditMode' );

					wb._siteList = null; // empty cache of wikibases site details
					if ( custom.setup !== undefined ) {
						custom.setup.apply( this, arguments );
					}
				},
				teardown: function () {
					mwEnv.teardown();
					if ( custom.teardown !== undefined ) {
						custom.teardown.apply( this, arguments );
					}
				}
			};
		};
	}() );

})( jQuery, mediaWiki, wikibase, QUnit );
