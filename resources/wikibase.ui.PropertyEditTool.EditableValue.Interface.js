/**
 * JavaScript for a part of an editable property value
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater
 */
( function( mw, wb, $, undefined ) {
'use strict';
var $PARENT = wb.ui.Base;

/**
 * Serves the input interface for a part of a value like a property value and also takes care of the
 * conversion between the pure html representation and the interface itself in both directions
 * @constructor
 * @see wikibase.ui.Base
 * @since 0.1
 */
wb.ui.PropertyEditTool.EditableValue.Interface = wb.utilities.inherit( $PARENT,
	// Overwritten constructor:
	function( subject, toolbar ) {
		if( arguments.length > 0 ) {
			this.init.apply( this, arguments );
		}
	}, {
	/**
	 * @const
	 * Class which marks the element within the site html.
	 */
	UI_CLASS: 'wb-ui-propertyedittool-editablevalueinterface',

	/**
	 * Element representing the editable value. This element will either hold the value or the input
	 * box in case it is activated for edit.
	 * @var jQuery
	 */
	_subject: null,

	/**
	 * This is true if the input interface is initialized at the time.
	 * @var bool
	 */
	_isInEditMode: false,

	/**
	 * If true, the input interface will be loaded on startEditing(), otherwise the value will remain
	 * uneditable.
	 * @var bool
	 */
	_isActive: true,

	/**
	 * Holds the input element in case this is in edit mode
	 * @var null|jQuery
	 */
	_inputElem: null,

	/**
	 * @see wb.ui._init
	 *
	 * @param jQuery subject
	 */
	_init: function( subject ) {
		// make sure the value is normalized when initialized:
		this.setValue( this.getValue() );

		// disable interface when editing is restricted
		$( wb ).on(
			'restrictItemPageActions blockItemPageActions',
			$.proxy( function( event ) {
				this.disable();
			}, this )
		);

	},

	/**
	 * @see wb.ui._destroy
	 */
	_destroy: function() {
		if( this.isInEditMode() ) {
			this.stopEditing( false );
		}
		if ( this.tooltip !== null ) {
			this.removeTooltip();
		}
	},

	/**
	 * By calling this, the editable value will be made editable for the user.
	 * Call stopEditing() to save or cancel the editing process.
	 * Basically this initializes the input box as sub element of the subject and uses the
	 * elements content as initial text.
	 *
	 * @return bool will return false if edit mode is active already.
	 */
	startEditing: function() {
		if( this.isInEditMode() || !this.isActive() ) {
			return false;
		}

		// initializes the input element into the DOM and removes the html representation
		this._initInputElement();

		this._isInEditMode = true;

		this._onInputRegistered(); // do this after setting _isInEditMode !
		this.setFocus();

		$( this ).triggerHandler( 'afterStartEditing' );

		return true;
	},

	/**
	 * Initializes the input element and appends it into the DOM when needed.
	 */
	_initInputElement: function() {
		var initialValue = this.getValue();
		this._inputElem = this._buildInputElement();
		this.updateLanguageAttributes();

		if( this.isDisabled() ) {
			// disable element properly if disabled from before edit mode
			this._disableInputElement();
		}

		// store original text value from before input box insertion:
		this._inputElem.data( this.UI_CLASS + '-initial-value', initialValue );

		var inputParent = this._getValueContainer();
		inputParent.empty();
		this._inputElem.appendTo( inputParent );

		if( this.autoExpand ) {
			/**
			 * FIXME: not the nicest way of getting these things via DOM, might be better to implement this into the
			 *        related EditableValue
			 */
			var evCls = wb.ui.PropertyEditTool.EditableValue.prototype.UI_CLASS,
				petCls = wb.ui.PropertyEditTool.prototype.UI_CLASS;

			this._inputElem.inputAutoExpand( {
				maxWidth: $.proxy( function() {
					var editableValNode = this._subject.closest( '.' + evCls ),
						propertyEditTool = editableValNode.closest( '.' + petCls ),
						toolbarParent = editableValNode.children( '.' + evCls + '-toolbarparent:first' );

					return propertyEditTool.width() - toolbarParent.outerWidth( true ) - 25;
				}, this )
			} );
		}
	},

	/**
	 * returns the input element for editing
	 * @return jQuery
	 */
	_buildInputElement: function() {
		return $( '<input/>', {
			'class': this.UI_CLASS,
			'type': 'text',
			'name': this._key,
			'value': this.getValue(),
			'keypress': $.proxy( this._onKeyPressed, this ),
			'keyup':    $.proxy( this._onKeyUp, this ),
			'keydown':  $.proxy( this._onKeyDown, this ),
			'focus':    $.proxy( this._onFocus, this ),
			'blur':     $.proxy( this._onBlur, this ),
			'change':   $.proxy( this._onChange, this )
		} )
		// setting placeholder inside the jQuery object initialisation does not work as of jQuery 1.8.0
		.attr( 'placeholder', this.inputPlaceholder )
		// on each change to this input check whether value was changed:
		.eachchange( $.proxy( function( e, oldValue ) {
			if( this.normalize( oldValue ) !== this.getValue() ) {
				this._onInputRegistered(); // only called if input really changed
			}
		}, this ) );
	},

	/**
	 * Update HTML language and directionality attributes.
	 */
	updateLanguageAttributes: function() {
		// apply subject's language attributes or attributes according to user language.
		if ( this._inputElem !== null ) {
			var lang = this.getSubject().attr( 'lang' );
			if ( lang === undefined ) {
				lang = mw.config.get( 'wgUserLanguage' );
			}
			var dir = this.getSubject().attr( 'dir' );
			if ( typeof dir === undefined ) {
				if ( $.uls.data.languages[lang] !== undefined ) {
					dir = ( $.uls.data.isRtl( lang ) ) ? 'rtl' : 'ltr';
				}
			}
			this._inputElem.attr( 'lang', lang ).attr( 'dir', dir );
		}
	},

	/**
	 * Set HTML language and directionality attributes.
	 *
	 * @param Object language
	 */
	setLanguageAttributes: function( language ) {
		this.getSubject().attr( 'lang', language.code ).attr( 'dir', language.dir );
		this.updateLanguageAttributes();
	},

	/**
	 * Returns the node holding the value. This node will also hold the input box when in edit mode.
	 * @return jQuery
	 */
	_getValueContainer: function() {
		return this._subject;
	},

	/**
	 * Called when the input changes in general for example on its initialization when setting
	 * its initial value or on setValue() while in edit mode.
	 */
	_onInputRegistered: function() {
		if( this.onInputRegistered !== null && this.onInputRegistered() === false ) { // callback
			return false; // cancel
		}
	},

	/**
	 * Called when a key is pressed inside the input interface
	 */
	_onKeyPressed: function( event ) {
		if( this.onKeyPressed !== null && this.onKeyPressed( event ) === false ) { // callback
			return false; // cancel
		}
	},

	_onKeyUp: function( event ) {
		if( this.onKeyUp !== null && this.onKeyUp( event ) === false ) { // callback
			return false; // cancel
		}
	},

	_onKeyDown: function( event ) {
		this._previousValue = this.getValue(); // remember current value before key changes text
		if( this.onKeyDown !== null && this.onKeyDown( event ) === false ) { // callback
			return false; // cancel
		}
	},

	_onFocus: function( event ) {
		if( this.onFocus !== null ) {
			this.onFocus( event ); // callback
		}
	},

	_onBlur: function( event ) {
		if( this.onBlur !== null ) {
			this.onBlur( event ); // callback
		}
	},

	_onChange: function( event ) {
		if( this.onChange !== null ) {
			this.onChange( event ); // callback
		}
	},

	/**
	 * Destroys the edit box and displays the original text or the inputs new value.
	 *
	 * @param bool save whether to save the new user given value
	 * @return bool whether the value has changed compared to the original value
	 */
	stopEditing: function( save ) {
		if( ! this.isInEditMode() ) {
			return false;
		}
		var initialValue = this.getInitialValue();

		var value = save ? this.getValue() : initialValue;

		this._destroyInputElement(); // remove input interface

		this._isInEditMode = false;

		// save but don't use setValue(), in case we cancelled, we don't want further normalization
		var fireEvent = this.isInEditMode()
			? this._setValue_inEditMode( value )
			: this._setValue_inNonEditMode( value );

		if( fireEvent !== false ) {
			this._onInputRegistered(); // new input
		}

		// any change at all compared to initial value?
		return initialValue !== value;
	},

	/**
	 * Destroys and removes input element (this._inputElem) from DOM and sets it to null.
	 */
	_destroyInputElement: function() {
		this._inputElem.empty().remove();
		this._inputElem = null;
	},

	/**
	 * Sets the focus to the input interface
	 */
	setFocus: function() {
		if( this._inputElem !== null ) {
			this._inputElem.focus();
		}
	},

	/**
	 * Removes the focus from the input interface
	 */
	removeFocus: function() {
		if( this._inputElem !== null ) {
			this._inputElem.blur();
		}
	},

	/**
	 * Returns whether the input interface is loaded currently
	 *
	 * @return bool
	 */
	isInEditMode: function() {
		return this._isInEditMode;
	},

	/**
	 * Returns the current value
	 *
	 * @return string
	 */
	getValue: function() {
		var value = this.isInEditMode()
			? this._getValue_inEditMode()
			: this._getValue_inNonEditMode();
			// if already set, the value should be normalized already.
			// if this is not the case in another inheriting interface, change it there BUT NOT HERE!

		return value === null ? '' : value; // don't allow this to be null!
	},

	/**
	 * Called by getValue() if the value has to be grabbed from the input interface in edit mode.
	 *
	 * @return string
	 */
	_getValue_inEditMode: function() {
		return this._inputElem.val();
	},

	/**
	 * Called by getValue() if the value has to be grabbed from the static DOM nodes.
	 *
	 * @return string
	 */
	_getValue_inNonEditMode: function() {
		return this._getValueContainer().text();
	},

	/**
	 * Sets a value.
	 * Returns the value really set in the end. This string can be different from the given value
	 * since it will go through some normalization first.
	 * Won't change the value and return in case it was invalid.
	 *
	 * @param string value
	 * @return string|null same as value but normalized, null in case the value was invalid
	 */
	setValue: function( value ) {
		// make sure the value is sufficient
		value = this.normalize( value );
		var oldVal = this.getValue();

		if( value === oldVal ) {
			// nothing changed
			return value;
		}

		var fireEvent = this.isInEditMode()
			? this._setValue_inEditMode( value )
			: this._setValue_inNonEditMode( value );

		if( fireEvent !== false ) {
			this._onInputRegistered(); // new input
		}
		return value;
	},

	/**
	 * Helper function comparing two values returned by getValue() or getInitialValue().
	 *
	 * @param String value1
	 * @param String value2 [optional] if not given, this will check whether value1 is empty
	 * @return bool true for equal/empty, false if not
	 */
	valueCompare: function( value1, value2 ) {
		value1 = this.normalize( value1 );

		if( value2 === undefined || value2 === null ) {
			// check for empty value1
			return value1 === '' || value1 === null;
		}
		return value1 === this.normalize( value2 );
	},

	/**
	 * Called by setValue() if the value has to be injected into the input interface in edit mode.
	 *
	 * @param string value
	 * @return bool whether the value has been changed
	 */
	_setValue_inEditMode: function( value ) {
		this._inputElem.attr( 'value', value );
		return true;
	},

	/**
	 * Called by setValue() if the value has to be injected into the static DOM nodes, not into input elements.
	 *
	 * @param string value
	 * @return bool whether the value has been changed
	 */
	_setValue_inNonEditMode: function( value ) {
		this._getValueContainer().text( value );
		return true;
	},

	/**
	 * Normalizes a string so it is sufficient for setting it as value for this interface.
	 * This will be done automatically when using setValue().
	 * In case the given value is invalid, null will be returned.
	 *
	 * @param string value
	 * @return string|null
	 */
	normalize: function( value ) {
		return $.trim( value );
		// NOTE: don't return null in case '' is given - FIXME: seems a bit off from the actual description!
	},

	/**
	 * Returns true if the interface is disabled.
	 *
	 * @return bool
	 */
	isDisabled: function() {
		return this._subject.hasClass( this.UI_CLASS + '-disabled' );
	},

	/**
	 * Convenience function to disable this interface.
	 *
	 * @return bool whether the operation was successful
	 */
	disable: function() {
		return this.setDisabled( true );
	},

	/**
	 * Convenience function to enable this interface.
	 *
	 * @return bool whether the operation was successful
	 */
	enable: function() {
		return this.setDisabled( false );
	},

	/**
	 * Disables or enables the element. Disabled is still visible but will be presented differently
	 * and might behave differently in some cases.
	 *
	 * @param bool disable true for disabling, false for enabling the element
	 * @return bool whether operation was successful
	 */
	setDisabled: function( disable ) {
		if( disable ) {
			this._subject.addClass( this.UI_CLASS + '-disabled' );
			if( this.isInEditMode() ) {
				this._disableInputElement();
			}
		} else {
			this._subject.removeClass( this.UI_CLASS + '-disabled' );
			if( this.isInEditMode() ) {
				this._enableInputElement();
			}
		}
		return true;
	},

	/**
	 * disable this interface's input element
	 */
	_disableInputElement: function() {
		this._inputElem.attr( 'disabled', 'true' );
	},

	/**
	 * enable this interface's input element
	 */
	_enableInputElement: function() {
		this._inputElem.removeAttr( 'disabled' );
	},

	/**
	 * Returns whether the interface is deactivated or active. If it is deactivated, the input
	 * interface will not be made available on startEditing()
	 *
	 * @return bool
	 */
	isActive: function() {
		return this._isActive;
	},

	/**
	 * Sets the interface active or inactive. If inactive, the interface will not be made available
	 * when startEditing() is called. If called to deactivate the interface but still in edit mode,
	 * the edit mode will be closed without saving.
	 *
	 * @return bool whether the state was changed or not.
	 */
	setActive: function( active ) {
		if( !active && this.isInEditMode() ) {
			this.stopEditing( false );
		}
		this._isActive = active;
	},

	/**
	 * If the input is in edit mode, this will return the value active before the edit mode was entered.
	 * If its not in edit mode, the current value will be returned.
	 *
	 * @return string|null
	 */
	getInitialValue: function() {
		if( ! this.isInEditMode() ) {
			return this.getValue();
		}
		return this._inputElem.data( this.UI_CLASS + '-initial-value' );
	},

	/**
	 * Returns true if there is currently no value assigned
	 *
	 * @return bool
	 */
	isEmpty: function() {
		return this.valueCompare( this.getValue() );
	},

	/**
	 * Returns whether the current value is valid
	 *
	 * @return bool
	 */
	isValid: function() {
		return this.validate( this.getValue() );
	},

	/**
	 * Velidates whether a certain value would be valid for this editable value.
	 *
	 * @param string text
	 * @return bool
	 */
	validate: function( value ) {
		var normalized = this.normalize( value );
		return  typeof( value ) == 'string' && normalized !== null && normalized !== '';
	},

	/////////////////
	// CONFIGURABLE:
	/////////////////

	/**
	 * Allows to define a default value appearing in the input box in case there is no value given
	 * @var string
	 */
	inputPlaceholder: '',

	/**
	 * When true, automatically expands width of input element according to containing text
	 * @var bool
	 */
	autoExpand: false,

	///////////
	// EVENTS:
	///////////

	/**
	 * Callback called when the input changes in general for example on its initialization when
	 * setting its initial value.
	 * @var Function|null
	 */
	onInputRegistered: null,

	onKeyPressed: null,

	onKeyUp: null,

	onKeyDown: null,

	onFocus: null,

	onBlur: null,

	onChange: null
} );

// add tooltip functionality to EditableValue:
wb.ui.Tooltip.Extension.useWith( wb.ui.PropertyEditTool.EditableValue.Interface, {
	// overwrite required functions:
	_getTooltipParent: function() {
		return this._subject;
	}
} );

} )( mediaWiki, wikibase, jQuery );
