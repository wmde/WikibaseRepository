/**
 * JavaScript for 'Wikibase' ui elements.
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner at wikimedia.de >
 */
( function( mw, wb, $, undefined ) {
'use strict';

/**
 * Base for prototypes handling frontend functionality.
 * Brings some convenience functions similar to jQuery's 'Widget'.
 * @constructor
 * @since 0.1
 *
 * @param subject jQuery
 */
wb.ui.Base = function( subject ) {
	if( subject !== undefined ) {
		this.init.apply( this, arguments );
	}
};
wb.ui.Base.prototype = {
	/**
	 * @const
	 * Class which marks the subject. This can also be used to mark structures within the subject with more specific
	 * classes like this.UI_CLASS + '-part'
	 */
	UI_CLASS: 'wb-ui',

	/**
	 * @const
	 * Secondary UI classes (separated by space) which will also mark the subject.
	 */
	SECONDARY_UI_CLASSES: '',

	/**
	 * The root element of this UI object.
	 * @var jQuery
	 */
	_subject: null,

	/**
	 * Initializes the objects UI functionality for the given element.
	 * Usually this is called by the constructor, except if not all required parameters were given to the constructor
	 * or if the constructor was not used at all e.g. when using Object.create()
	 *
	 * If called on a already initialized object, this will destroy the object by calling destroy() and then initialize
	 * it again on the given subject. It is not encouraged to do so though. Normally a new object should be initialized
	 * to avoid problems with insufficient destroy() implementations.
	 *
	 * @see constructor for parameter description
	 * @see _init() which should be overwritten instead of this one
	 * @final
	 */
	init: function( subject ) {
		if( this.isInitialized() ) {
			this.destroy();
		}
		var uiClasses = this.UI_CLASS + ' ' + this.SECONDARY_UI_CLASSES;

		// make sure we have a jQuery object, not a plain DOM node:
		this._subject = arguments[0] = subject = $( subject );

		// add UI classes to subject.
		// They should be set before _init() so UI element styles are applied during initialization. This could be
		// necessary when dealing with measurement of elements which would fail if styles are not active already.
		subject.addClass( uiClasses );

		// call _init() for inherited prototypes to implement their custom initialization:
		var result = this._init.apply( this, arguments );

		// check whether subject has changed by custom _init():
		if( !this._subject.is( subject ) ) {
			// subject has changed, so we remove classes from original subject and add them to the new subject.
			// NOTE: subject should usually not be changed within! Another solution would be to add a function for
			//       choosing the subject first to allow changing the subject without conflicts.
			this._subject.addClass( uiClasses ); // add classes to real subject...
			subject.removeClass( uiClasses ); // ... but remove classes from original subject
		}

		return result;
	},

	/**
	 * Should be overwritten instead of init() if additional functionality should be added.
	 * All arguments given to init() will be available.
	 * @private
	 */
	_init: function( subject ) {},

	/**
	 * Returns true if the init() function was called or all necessary parameters have been passed to the constructor.
	 * If destroy() was called already, this will return false.
	 *
	 * @return Boolean
	 */
	isInitialized: function() {
		return this._subject !== null && !this.isDestroyed();
	},

	/**
	 * Destroys the UI functionality provided by this object
	 * @see _destroy() which should be overwritten instead of this one
	 * @final
	 */
	destroy: function() {
		var result = this._destroy.apply( this, arguments ); // should be overwritten rather than destroy()
		this._isDestroyed = true;
		this._subject.removeClass( this.UI_CLASS + ' ' + this.SECONDARY_UI_CLASSES );
		return result
		// do not remove reference to subject since this could still be useful for the outside world!
	},

	/**
	 * Should be overwritten instead of destroy() if additional functionality should be added.
	 * All arguments given to destroy() will be available.
	 * @private
	 */
	_destroy: function() {
	},

	/**
	 * Returns whether the destroy() function was called.
	 *
	 * @return Boolean
	 */
	isDestroyed: function() {
		return !!this._isDestroyed;
	},

	/**
	 * The root element of this UI object.
	 *
	 * @return jQuery
	 */
	getSubject: function() {
		return this._subject;
	}
};

} )( mediaWiki, wikibase, jQuery );
