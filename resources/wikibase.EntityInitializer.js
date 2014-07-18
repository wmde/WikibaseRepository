/**
 * @licence GNU GPL v2+
 *
 * @author Adrian Lang <adrian.lang@wikimedia.de>
 * @author: H. Snater < mediawiki@snater.com >
 */
( function( $, mw, wb ) {
	'use strict';

	/**
	 * Entity initializer.
	 * Unserializes the entity passed to JavaScript via mw.config variable.
	 * @constructor
	 * @since 0.5
	 *
	 * @param {string} configVarName
	 *
	 * @throws {Error} if required parameter is not specified properly.
	 */
	var EntityInitializer = wb.EntityInitializer = function( configVarName ) {
		if( typeof configVarName !== 'string' ) {
			throw new Error( 'Config variable name needs to be specified' );
		}
		this._configVarName = configVarName;
	};

	$.extend( EntityInitializer.prototype, {
		/**
		 * Name of the mw.config variable featuring the serialized entity.
		 * @type {string}
		 */
		_configVarName: null,

		/**
		 * @type {wikibase.datamodel.Entity|null}
		 */
		_value: null,

		/**
		 * Retrieves an entity from mw.config.
		 *
		 * @return {Object} jQuery Promise
		 *         Resolved parameters:
		 *         - {wikibase.datamodel.Entity}
		 *         No rejected parameters.
		 */
		getEntity: function() {
			var self = this,
				deferred = $.Deferred();

			if( this._value ) {
				return deferred.resolve( this._value ).promise();
			}

			this._getFromConfig()
			.done( function( value ) {
				self._value = value;
				deferred.resolve( self._value );
			} )
			.fail( $.proxy( deferred.reject, deferred ) );

			return deferred.promise();
		},

		/**
		 * @return {Object} jQuery promise
		 *         Resolved parameters:
		 *         - {wikibase.datamodel.Entity}
		 *         No rejected parameters.
		 */
		_getFromConfig: function() {
			var self = this,
				deferred = $.Deferred();

			mw.hook( 'wikipage.content' ).add( function() {
				var serializedEntity = mw.config.get( self._configVarName );

				if( serializedEntity === null ) {
					deferred.reject();
					return;
				}

				var entityJSON = JSON.parse( serializedEntity ),
					unserializerFactory = new wb.serialization.SerializerFactory(),
					entityUnserializer = unserializerFactory.newUnserializerFor(
						wb.datamodel.Entity
					);

				deferred.resolve( entityUnserializer.unserialize( entityJSON ) );
				entityJSON = null;
			} );

			return deferred.promise();
		}
	} );

} )( jQuery, mediaWiki, wikibase );
