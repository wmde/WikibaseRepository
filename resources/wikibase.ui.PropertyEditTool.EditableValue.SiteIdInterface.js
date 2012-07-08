/**
 * JavaScript for a part of an editable property value for the input for a site id
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.js
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater
 */
"use strict";

/**
 * Serves the input interface to write a site code or to selectone. This will also validate whether
 * the site code is existing and will display the full site name if it is.
 *
 * @param jQuery subject
 */
window.wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface = function( subject ) {
	window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.apply( this, arguments );
};
window.wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.prototype = new window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface();
$.extend( window.wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.prototype, {

	_init: function( subject ) {
		if( this._subject !== null ) {
			// initializing twice should never happen, have to destroy first!
			this.destroy();
		}
		this._subject = $( subject );
		this._initSiteList();
		this._subject = null; // necessary so original _init() won't destroy this

		// NOTE: this isn't too nice since we don't call the parent _init() which would override the list we create
		//       with _initSiteList() which we also can't call afterwards since the original _init() already requires
		//       it when normalizing the initial value.
		window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype._init.call( this, subject );
	},

	/**
	 * initialize input box
	 * @see wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface._initInputElement
	 */
	_initInputElement: function() {
		window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.prototype._initInputElement.call( this );
		/**
		 * when leaving the input box, set displayed value to from any allowed input value to correct display value
		 *
		 * @param event
		 */
		this._inputElem.on( 'blur', $.proxy( function( event ) {
			if ( this.getSelectedSiteId() !== null ) {
				/*
				 loop through complete result set since the autocomplete widget's narrowed result set
				 is not reliable / too slow; e.g. do not do this:
				 widget.data( 'menu' ).activate( event, widget.children().filter(':first') );
				 this._inputElem.val( widget.data( 'menu' ).active.data( 'item.autocomplete' ).value );
				 */
				$.each( this._currentResults, $.proxy( function( index, element ) {
					if ( element.site.getId() == this.getSelectedSiteId() ) {
						this._inputElem.val(element.value );
					}
				}, this ) );
				this._onInputRegistered();
			}
		}, this ) );
	},


	/**
	 * build autocomplete input box and define input handling
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype._buildInputElement
	 */
	_buildInputElement: function() {
		// get basic input box
		var inputElement
			= window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype._buildInputElement.call( this );

		// extend input element with autocomplete
		if ( this._currentResults !== null ) {
			inputElement.wikibaseAutocomplete( {
				source: $.proxy( function( request, response ) {
					// just matching from the beginning (autocomplete would match anywhere within the string)
					var results = $.grep( this._currentResults, function( result, i ) {
						return (
							result.label.toLowerCase().indexOf( request.term.toLowerCase() ) == 0
								|| result.site.getId().indexOf( request.term.toLowerCase() ) == 0
						);
					} );
					/*
					if some site id is specified exactly, move that site to the top for it will be the one picked
					when leaving the input field
					 */
					var additionallyFiltered = $.grep( results, function( result, i ) {
						return ( request.term == result.site.getId() );
					} );
					if ( additionallyFiltered.length > 0 ) { // remove site from original result set
						for ( var i in results ) {
							if ( results[i].site.getId() == additionallyFiltered[0].site.getId() ) {
								results.splice( i, 1 );
								break;
							}
						}
					}
					// put site with exactly hit site id to beginning of complete result set
					$.merge( additionallyFiltered, results );
					response( additionallyFiltered );
				}, this ),
				close: $.proxy( function( event, ui ) {
					this._onInputRegistered();
				}, this )
			} );
		}

		inputElement.on( 'autocompleteopen', $.proxy( function( event ) {
			this._highlightMatchingCharacters();
		}, this ) );

		return inputElement;
	},

	/**
	 * highlight matching input characters in results
	 * @see wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface._highlightMatchingCharacters
	 */
	_highlightMatchingCharacters: function() {
		var regExp = new RegExp( '^(' + $.ui.autocomplete.escapeRegex( this._inputElem.val() ) + ')', 'i' );
		var regExpCode = new RegExp(
			'\\((' + $.ui.autocomplete.escapeRegex( this._inputElem.val() ) + ')(\\S*)\\)',
			'i'
		); // check for direct language code hit
		this._inputElem.data( 'autocomplete' ).menu.element.children().each( function( i ) {
			var node = $( this ).find( 'a' );
			if ( regExpCode.test( node.text() ) ) {
				node.html( node.text().replace( regExpCode, '(<b>$1</b>$2)' ) );
			} else {
				node.html( node.text().replace( regExp, '<b>$1</b>' ) );
			}
		} );
	},

	/**
	 * Builds a list of sites allowed to choose from
	 */
	_initSiteList: function() {
		var siteList = [];

		// make sure to allow choosing the currently selected site id even if it is in the list of
		// sites to ignore. This makes sense since it is selected already and it should be possible
		// to select it again.
		var ignoredSites = [];
		if ( this.ignoredSiteLinks !== null ) {
			ignoredSites = this.ignoredSiteLinks.slice();
		}
		var ownSite = this.getSelectedSite();
		if( ownSite !== null ) {
			// make sure currently selected site can still be selected even if on ignore list
			var ownSiteIndex = $.inArray( ownSite, ignoredSites );
			if( ownSiteIndex > -1 ) {
				ignoredSites.splice( ownSiteIndex, 1 );
			}
		}

		// find out which site ids should be selectable and add them as auto selct choice
		for ( var siteId in window.wikibase.getSites() ) {
			var site = window.wikibase.getSite( siteId );

			if( $.inArray( site, ignoredSites ) == -1 ) {
				siteList.push( {
					'label': site.getName() + ' (' + site.getId() + ')',
					'value': site.getShortName() + ' (' + site.getId() + ')',
					'site': site // additional reference to site object for validation
				} );
			}
		}
		this.setResultSet( siteList );
	},

	/**
	 * Returns the selected site
	 *
	 * @return wikibase.Site
	 */
	getSelectedSite: function() {
		var siteId = this.getSelectedSiteId();
		if( siteId === null ) {
			return null;
		}
		return window.wikibase.getSite( siteId );
	},

	/**
	 * Returns the selected sites site Id from currently specified value.
	 *
	 * @return string|null siteId or null if no valid selection has been made yet.
	 */
	getSelectedSiteId: function() {
		var id = this.getValue();
		if( id === '' ) {
			return null;
		}
		return id;
	},

	/**
	 * Returns the current value
	 *
	 * @return string
	 */
	getValue: function() {
		var value = window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.prototype.getValue.call( this );
		value = this.normalize( value ); // return id instead of actual value...
		return value ? value : ''; // ... but make sure this won't be null!
	},

	_setValue_inNonEditMode: function( value ) {
		// the actual value is the site id, displayed value though should be the whole site name and id in parentheses.
		var site = window.wikibase.getSite( value );
		if( site !== null ) {
			// site is null in case it was initialized empty and destroy() is called... so we just handle this
			value = site.getShortName() + ' (' + site.getId() + ')';
		}
		return window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.prototype._setValue_inNonEditMode.call( this, value );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.getRestulSetMatch
	 *
	 * @todo: might be nice to move this into wikibase.Site.getIdByString() or something.
	 */
	getRestulSetMatch: function( value ) {
		// trim and lower...
		value = $.trim( value ).toLowerCase();

		for( var i in this._currentResults ) {
			var currentItem = this._currentResults[i];
			if( value == currentItem.site.getId().toLowerCase() ||
				value == currentItem.site.getShortName().toLowerCase() ||
				value == currentItem.value.toLowerCase() ||
				value == currentItem.label.toLowerCase()
			) {
				return currentItem.site.getId();
			}
		}
		return null; // not found, invalid!
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.validate
	 */
	validate: function( value ) {
		return this.normalize( value ) !== null;
	},

	/**
	 * @see window.wikibase.ui.PropertyEditTool.EditableValue._normalize_fromCurrentResults
	 *
	 * Will return the site ID if any of the site names is given.
	 */
	_normalize_fromCurrentResults: function( value ) {
		return this.getRestulSetMatch( value ); // null in case it doesn't exist!
	},

	/////////////////
	// CONFIGURABLE:
	/////////////////

	/**
	 * Allows to specify an array with sites which should not be allowed to choose
	 */
	ignoredSiteLinks: null
} );
