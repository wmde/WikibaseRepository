/**
 * JavasSript for a part of an editable property value
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 * 
 * @since 0.1
 * @file wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.js
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater
 * @author Daniel Werner
 */
"use strict";

/**
 * Serves the input interface to choose a wiki page from some MediaWiki installation as part of an
 * editable value
 * 
 * @param jQuery subject
 */
window.wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface = function( subject, site ) {
	window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.apply( this, arguments );
};
window.wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.prototype = new window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface();
$.extend( window.wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.prototype, {
	/**
	 * Information for which site this autocomplete interface should serve input suggestions
	 * @var wikibase.Site
	 */
	_site: null,
	
	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface._init()
	 * 
	 * @param site wikibase.Site as source for the page suggestions
	 */
	_init: function( subject, site ) {
		window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.prototype._init.apply( this, arguments );		
		this.setSite( site );
	},
	
	/**
	 * Allows to set the site, the pages should be selected from.
	 *
	 * @param site wikibase.Site
	 */
	setSite: function( site ) {
		if( typeof site !== 'object' ) {
			return;
		}
		if( this._site !== null && this._site.getId() === site.getId() ) {
			return; // no change
		}
		
		this.url = site.getApi();
		this._site = site;
				
		this._currentResults = []; // empty current suggestions...		
		if( this.isInEditMode() ) {
			this._inputElem.autocomplete( "search" ); // ...and get new suggestions
			
			/* // TODO: this should be done after "search" is finished, apparently, there is no callback for that currently...
			if( ! this.isValid() ) {
				this.setValue( '' );
			}
			*/		   
		}
	},
	
	/**
	 * Returns the site set to select pages from.
	 *
	 * @return wikibase.Site
	 */
	getSite: function() {
		return this._site;		
	},

	/**
	 * validate input
	 * 
	 * @param String value
	 */
	validate: function( value ) {
		window.wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.prototype.validate.call( this, value );
		for ( var i in this._currentResults ) {
			if ( value === this._currentResults[i] ) {
				return true;
			}
		}
	},
	
	setValue: function( value ) {
		if( this.isInEditMode() ) {
			this._inputElem.attr( 'value', value );
		} else {
			this._getValueContainer()
			.empty()
			.append( // insert link to site in site
				this._site.getLinkTo( value )
			);
		}
	}

} );
