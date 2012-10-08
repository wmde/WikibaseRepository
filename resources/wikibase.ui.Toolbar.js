/**
 * JavaScript for toolbars used in 'Wikibase' extensions.
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

/**
 * Gives basic edit toolbar functionality, serves the "[edit]" button as well as the "[cancel|save]"
 * buttons and other related stuff.
 * @constructor
 * @since 0.1
 */
wb.ui.Toolbar = function( uiClass ) {
	this.init( uiClass );
};
wb.ui.Toolbar.prototype = {
	/**
	 * @const
	 * Class which marks the element within the site html.
	 */
	UI_CLASS: 'wb-ui-toolbar',

	/**
	 * additional css class to be assigned to the toolbar container
	 * @var String
	 */
	_additionalUiClass: '',

	/**
	 * @var jQuery
	 */
	_parent: null,

	/**
	 * The toolbar element in the dom
	 * @var jQuery
	 */
	_elem: null,

	/**
	 * @var Array
	 * items that are rendered inside the toolbar like buttons, labels, tooltips or groups of such items
	 */
	_items: null,

	/**
	 * @var string
	 * initial css display value that is stored for re-showing when hiding the toolbar
	 */
	_display: null,

	/**
	 * Initializes the edit toolbar for the given element.
	 * This should normally be called directly by the constructor.
	 */
	init: function( uiClass ) {
		if( this._elem !== null ) {
			// initializing twice should never happen, have to destroy first!
			this.destroy();
		}
		this._items = [];
		if ( typeof uiClass === 'string' ) {
			this._additionalUiClass = uiClass + '-toolbar';
		}
		this.draw(); // draw first to have toolbar wrapper
		this._initToolbar();
	},

	/**
	 * Initializes elements within the toolbar if any should be there from the beginning.
	 */
	_initToolbar: function() {},

	/**
	 * Function for (re)rendering the element
	 */
	draw: function() {
		this._drawToolbar();
		this._drawToolbarElements();
	},

	appendTo: function( elem ) {
		if( this._elem === null ) {
			this.draw(); // this will generate the toolbar
		}
		this._elem.appendTo( elem );
		this._parent = this._elem.parent();
	},

	/**
	 * Draws the toolbar element itself without its content
	 */
	_drawToolbar: function() {
		var parent = null;
		if( this._elem !== null ) {
			this._elem.children().detach(); // only detach so elements can be attached somewhere else
			parent = this._elem.parent();
			this._elem.remove(); // remove element after parent is known
		}
		this._elem = $( '<div/>', {
			'class': this.UI_CLASS + ' ' + this._additionalUiClass
		} );
		if( parent !== null ) { // if not known yet, appendTo() wasn't called so far
			parent.append( this._elem );
		}
	},

	/**
	 * Draws the toolbar elements like buttons and labels
	 */
	_drawToolbarElements: function() {
		var i = -1;
		for( i in this._items ) {
			if( this.renderItemSeparators && i != 0 ) {
				this._elem.append( '|' );
			}
			this._elem.append( this._items[i]._elem );
		}

		// only render brackets if we have any content
		if( this.renderItemSeparators && i > -1 ) {
			this._elem
			.prepend( '[' )
			.append( ']' );
		}
	},

	/**
	 * This will add a toolbar element, e.g. a label or a button to the toolbar at the given index.
	 *
	 * @param Object elem toolbar content element (e.g. a group, button or label).
	 * @param index where to add the element (use negative values to specify the position from the end).
	 */
	addElement: function( elem, index ) {
		if( index === undefined ) {
			// add elem as last one
			this._items.push( elem );
		} else {
			// add elem at certain index
			this._items.splice( index, 0, elem);
		}
		this.draw(); // TODO: could be more efficient when just adding one element
	},

	/**
	 * Removes an element from the toolbar
	 *
	 * @param Object elem the element to remove
	 * @return bool false if element isn't part of this element
	 */
	removeElement: function( elem ) {
		var index = this.getIndexOf( elem );
		if( index < 0 ) {
			return false;
		}
		this._items.splice( index, 1 );

		this.draw(); // TODO: could be more efficient when just removing one element
		return true;
	},

	/**
	 * Returns whether the given element is represented within the toolbar.
	 *
	 * @return bool
	 */
	hasElement: function( elem ) {
		return this.getIndexOf( elem ) > -1;
	},

	/**
	 * returns the index of an element within the toolbar, -1 in case the element is not represented.
	 *
	 * @return int
	 */
	getIndexOf: function( elem ) {
		return $.inArray( elem, this._items );
	},

	/**
	 * Determine whether the state (disabled, enabled) of any toolbar element can be changed.
	 *
	 * @return bool whether the state of any toolbar element can be changed
	 */
	isStateChangeable: function() {
		var stateChangeable = false;
		$.each( this._items, function( i, item ) {
			if ( item.isStateChangeable() ) {
				stateChangeable = true;
			}
		} );
		return stateChangeable;
	},

	destroy: function() {
		if( this._items !== null ) {
			for( var i in this._items ) {
				this._items[i].destroy();
			}
			this._items = null;
		}
		if( this._elem !== null ) {
			this._elem.remove();
			this._elem = null;
		}
	},

	/**
	 * hide the toolbar
	 *
	 * @return bool whether toolbar is hidden
	 */
	hide: function() {
		if ( this._display === null || this._display === 'none' ) {
			this._display = this._elem.css( 'display' );
		}
		this._elem.css( 'display', 'none' );
		return this.isHidden();
	},

	/**
	 * show the toolbar
	 *
	 * @return whether toolbar is visible
	 */
	show: function() {
		this._elem.css( 'display', ( this._display === null ) ? 'block' : this._display );
		return !this.isHidden();
	},

	/**
	 * determine whether this toolbar is hidden
	 *
	 * @return bool
	 */
	isHidden: function() {
		return ( this._elem.css( 'display' ) == 'none' );
	},

	/////////////////
	// CONFIGURABLE:
	/////////////////

	/**
	 * Defines whether the toolbar should be displayed with separators "|" between each item. In that
	 * case everything will also be wrapped within "[" and "]".
	 * This is particulary interesting for wikibase.ui.Toolbar.Group toolbar groups
	 * @var bool
	 */
	renderItemSeparators: false
};

// add disable/enable functionality overwriting required functions
wb.utilities.ui.StateExtension.useWith( wb.ui.Toolbar, {
	/**
	 * Determines the state (disabled, enabled or mixed) of all toolbar elements.
	 * @see wb.utilities.ui.StateExtension.getState
	 */
	getState: function() {
		var disabled = true, enabled = true;
		$.each( this._items, function( i, item ) {
			// loop through all sub-toolbars and check dedicated toolbar elements
			if ( item instanceof wikibase.ui.Toolbar || item.stateChangeable ) {
				if ( item.isDisabled() ) {
					enabled = false;
				} else if ( !item.isDisabled() ) {
					disabled = false;
				}
			}
		} );
		if ( disabled === true ) {
			return this.STATE.DISABLED;
		} else if ( enabled === true ) {
			return this.STATE.ENABLED;
		} else {
			return this.STATE.MIXED;
		}
	},

	/**
	 * @see wb.utilities.ui.StateExtension._setState
	 */
	_setState: function( state ) {
		var success = true;
		$.each( this._items, function( i, item ) {
			success = item.setState( state ) && success;
		} );
		return success;
	}

} );

} )( mediaWiki, wikibase, jQuery );
