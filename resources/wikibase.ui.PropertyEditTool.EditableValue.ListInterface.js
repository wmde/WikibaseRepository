/**
 * JavaScript for an list interface for EditableValue
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file wikibase.ui.PropertyEditTool.EditableValue.ListInterface.js
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 */
'use strict';

/**
 * Serves the input interface for a list of strings and handles the conversion between the pure html representation
 * and the interface itself in both directions. All values of the list belong together and must be edited at the same
 * time.
 *
 * @param jQuery subject
 */
window.wikibase.ui.PropertyEditTool.EditableValue.ListInterface = function( subject ) {
	window.wikibase.ui.PropertyEditTool.EditableValue.Interface.apply( this, arguments );
};
window.wikibase.ui.PropertyEditTool.EditableValue.ListInterface.prototype
	= Object.create( window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype );
$.extend( window.wikibase.ui.PropertyEditTool.EditableValue.ListInterface.prototype, {
	/**
	 * Css class which will be attached to all pieces of a value set with this interface.
	 * @const
	 */
	UI_VALUE_PIECE_CLASS: 'wb-ui-propertyedittool-editablevaluelistinterface-piece',


	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._initInputElement
	 */
	_initInputElement: function() {
		window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype._initInputElement.call( this );
		/*
		applying auto-expansion mechanism has to be done after tagadata's tagList has been placed within
		the DOM since no css rules of the specified css classes are applied by then - respectively jQuery's
		.css() function would return unexpected results
		*/
		var self = this;
		$.each( this._getTagadata().tagList.children( 'li' ).find( 'input' ), function( i, input ) {
			if ( $( input ).inputAutoExpand ) {
				$( input ).inputAutoExpand( {
					expandOnResize: false,
					maxWidth: 1000
					/* // TODO/FIXME: both solutions are not perfect, when tag larger than available space either the
					   // input will be auto-resized and not show the whole text or we still show the whole tag but it
					   // will break the site layout. A solution would be replacing input with textarea.
					maxWidth: function() {
						var tagList = self._getTagadata().tagList;
						var origCssDisplay = tagList.css( 'display' );
						tagList.css( 'display', 'block' );
						var width = tagList.width();
						tagList.css( 'display', origCssDisplay );
						return width;
					}
					*/
				} );
			}
		} );
	},

	/**
	 * create input element and initialize autocomplete
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._buildInputElement
	 *
	 * @return jQuery
	 */
	_buildInputElement: function() {
		// nodes will be removed and replaced with genereated input interface, so we clone them for initialization:
		var inputElement = this._subject.children( 'ul:first' ).clone()
			.addClass( this.UI_CLASS )
			.addClass( 'wb-ui-propertyedittool-editablevaluelistinterface' ); // additional UI class

		inputElement
		.on( 'tagadatataginserted', $.proxy( function( e, tag ) {
			$( tag ).find( 'input' ).on( 'keypress', $.proxy( function( event ) {
				this._onKeyPressed( event );
			}, this ) );
		}, this ) );

		inputElement.tagadata( {
			animate: false, // FIXME: when animated set to true, something won't work in there, fails silently then
			placeholderText: this.inputPlaceholder,
			tagRemoved: $.proxy( this._onInputRegistered, this )
		} )
		// register event after initial tags were added on tag-a-data initialization!
		.on( 'tagadatatagadded tagadatatagchanged', $.proxy( function( e, tag ) {
			this._onInputRegistered();
		}, this ) );

		return inputElement;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._destroyInputElement
	 */
	_destroyInputElement: function() {
		this._getTagadata().destroy();
		this._subject.children( 'li' ).removeClass( this.UI_VALUE_PIECE_CLASS + '-new' );
		this._inputElem = null;
	},

	/**
	 * Convenience function for getting the 'tagadata' jQuery plugin data related to the _inputElem
	 *
	 * @return wikibase.utilities.jQuery.ui.tagadata|null
	 */
	_getTagadata: function() {
		if( ! this._inputElem ) {
			return null;
		}
		return this._inputElem.data( 'tagadata' );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._getValue_inEditMode
	 *
	 * @param string[]
	 */
	_getValue_inEditMode: function() {
		var tagadata = this._getTagadata();
		var labels = [];
		if ( typeof tagadata !== 'undefined' ) {
			var values = tagadata.getTags();
			for( var i in values ) {
				labels.push( tagadata.getTagLabel( values[i] ) );
			}
		}
		return labels;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._getValue_inNonEditMode
	 *
	 * @param string[]
	 */
	_getValue_inNonEditMode: function() {
		var values = new Array();
		var valList = this._subject.children( 'ul:first' );

		valList.children( 'li' ).each( function() {
			values.push( $( this ).text() );
		} );

		return values;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.setValue
	 *
	 * @param string[] value
	 * @return string[]|null same as value but normalized, null in case the value was invalid
	 */
	setValue: function( value ) {
		value.sort(); // sort values. NOTE: could be made configurable!
		return window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype.setValue.call( this, value );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._setValue_inEditMode
	 *
	 * @param string[] value
	 * @return bool
	 */
	_setValue_inEditMode: function( value ) {
		var self = this;
		$.each( value, function( i, val ) {
			self._getTagadata().createTag( val, self.UI_VALUE_PIECE_CLASS );
		} );
		return false; // onInputRegistered event will be thrown by tagadata.onTagAdded
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._setValue_inNonEditMode
	 *
	 * @param string[] value
	 * @return bool
	 */
	_setValue_inNonEditMode: function( value ) {
		var valList = this._subject.children( 'ul:first' );
		valList.empty();

		var self = this;
		$.each( value, function( i, val ) {
			valList.append( $( '<li>', {
				'class': self.UI_VALUE_PIECE_CLASS,
				'text': val
			} ) );
		} );

		return true; // trigger onInputRegistered event
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._disableInputElement
	 */
	_disableInputElement: function() {
		this._getTagadata().disable();
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._enableInputElement
	 */
	_enableInputElement: function() {
		this._getTagadata().enable();
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.valueCompare
	 *
	 * Compares all values of the two lists, normalizes the lists first. This means the values can be in random and
	 * still be considered equal.
	 *
	 * @param String[] value1
	 * @param String[] value2 [optional] if not given, this will check whether value1 is empty
	 * @return bool true for equal/empty, false if not
	 */
	valueCompare: function( value1, value2 ) {
		var normalVal1 = this.normalize( value1 );

		if( !$.isArray( value2 ) ) {
			// check for empty value1
			return normalVal1.length < 1;
		}

		var normalVal2 = this.normalize( value2 );

		if( normalVal1.length !== normalVal2.length ) {
			return false;
		}

		for( var i in normalVal1 ) {
			if( normalVal1[ i ] !== normalVal2[ i ] ) {
				return false;
			}
		}
		return true;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.isEmpty
	 *
	 * @return bool whether this interface is empty
	 */
	isEmpty: function() {
		return this.getValue().length == 0;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._enableInputElement
	 *
	 * @return bool whether this interface is valid
	 */
	validate: function( value ) {
		var normalized = this.normalize( value );
		return normalized.length > 0;
	},

	/**
	 * Normalizes a set of values. If any of the values pieces is invalid, the piece will be removed.
	 * If in the end no piece is left because all pieces were invalid, an empty array will be returned.
	 *
	 * @param String[] value
	 * @return String[] all parts of the value which are valid, can be an empty array
	 */
	normalize: function( value ) {
		var validValue = new Array();
		var self = this;
		$.each( value, function( i, val ) {
			val = self.normalizePiece( val );
			if( val !== null ) {
				// add valid values to result
				validValue.push( val );
			}
		} );
		validValue.sort(); // TODO: make this configurable or move to somewhere else perhaps
		return validValue;
	},

	/**
	 * Validates a piece of a list value.
	 *
	 * @param String value
	 * @return Bool
	 */
	validatePiece: function( value ) {
		var normalized = this.normalizePiece( value );
		return  normalized !== null;
	},

	/**
	 * Normalizes a string so it is sufficient for setting it as value for this interface.
	 * This will be done automatically when using setValue().
	 * In case the given value is invalid, null will be returned.
	 *
	 * @param String value
	 * @return String|null
	 */
	normalizePiece: function( value ) {
		var normalized = window.wikibase.ui.PropertyEditTool.EditableValue.Interface.prototype.normalize.call( this, value );
		if( normalized === '' ) {
			return null;
		}
		return normalized;
	}

} );

