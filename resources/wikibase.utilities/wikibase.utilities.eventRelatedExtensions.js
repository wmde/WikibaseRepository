/**
 * Holds extensions for event delegation, part of wb.utilities
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 */
( function( mw, wb, $, undefined ) {
	'use strict';

	/**
	 * Object extension which can add ability for event handling to other constructors/Objects.
	 * @constructor
	 * @extension
	 *
	 * @since 0.1
	 */
	wb.utilities.ObservableObject = wb.utilities.newExtension( {
		/**
		 * Triggers an event on the object, similar to jQuery.trigger()
		 *
		 * @see jQuery.trigger
		 */
		trigger: function() {
			$.fn.trigger.apply( $( this ), arguments );
		},

		/**
		 * Convenience function for jQuery( this ).on()
		 *
		 * @see jQuery.on
		 */
		on: function() {
			$.fn.on.apply( $( this ), arguments );
		},

		/**
		 * Convenience function for jQuery( this ).one()
		 *
		 * @see jQuery.one
		 */
		one: function() {
			$.fn.one.apply( $( this ), arguments );
		},

		/**
		 * Convenience function for jQuery( this ).off()
		 *
		 * @see jQuery.off
		 */
		off: function() {
			$.fn.off.apply( $( this ), arguments );
		}
	} );

} )( mediaWiki, wikibase, jQuery );
