/**
 * JavasScript for creating and managing a tooltip within the 'Wikibase' extension
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file wikibase.ui.Tooltip.js
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater
 */
'use strict';

/**
 * a generic tooltip
 *
 * @param jQuery subject tooltip will be attached to this node
 * @param string|object tooltipContent (may contain HTML markup), may also be an object describing an API error
 * @param object tipsyConfig (optional, default: { gravity: 'ne' }) custom tipsy tooltip configuration
 * @param object parentObject (only required for error tooltip, has to have an implementation of removeTooltip() )
 * 					parent object that the tooltip is referred from
 *
 * @event Hide called after the tooltip was hidden from a previously visible state.
 */
window.wikibase.ui.Tooltip = function( subject, tooltipContent, tipsyConfig, parentObject ) {
	if( typeof subject != 'undefined' ) {
		this._init( subject, tooltipContent, tipsyConfig, parentObject );
	}
};
window.wikibase.ui.Tooltip.prototype = {
	/**
	 * @const
	 * Class which marks the tooltip within the site html.
	 */
	UI_CLASS: 'wb-ui-tooltip',

	/**
	 * @var jQuery element the tooltip should be attached to
	 */
	_subject: null,

	/**
	 * @var Tipsy tipsy tooltip element
	 */
	_tipsy: null,

	/**
	 * @var Object tipsy tooltip configuration vars
	 */
	_tipsyConfig: null,

	/**
	 * @var bool used to determine if tooltip message is currently visible or not
	 */
	_isVisible: false,

	/**
	 * @var bool used to determine if tooltip should react on hovering or not
	 */
	_permanent: false,

	/**
	 * @var bool basically defines if the tooltip will appear in standard or error color schema
	 */
	_error: null,

	/**
	 * @var jQuery storing DOM content that should be displayed as tooltip bubble content
	 */
	_DomContent: null,

	/**
	 * @var object parent object the tooltip is referred from
	 */
	_parentObject: null,

	/**
	 * initializes ui element, called by the constructor
	 *
	 * @param jQuery subject tooltip will be attached to this node
	 * @param string|object tooltipContent (may contain HTML markup), may also be an object describing an API error
	 * @param object tipsyConfig (optional) custom tipsy tooltip configuration
	 * @param object parentObject (only required for error tooltip, has to have an implementation of removeTooltip() )
	 * 					parent object that the tooltip is referred from
	 */
	_init: function( subject, tooltipContent, tipsyConfig, parentObject ) {
		this._subject = subject;
		if ( typeof tooltipContent == 'string' ) {
			this._subject.attr( 'title', tooltipContent );
		} else {
			/* init tipsy with some placeholder since the tooltip message would not show without the title attribute
			being set; however, setting a complex HTML structure cannot be done via the title tag, so the content is
			stored in a custom variable that will be injected when the message is triggered to show */
			this._subject.attr( 'title', '.' );
			if ( typeof tooltipContent == 'object' && typeof tooltipContent.code != 'undefined' ) {
				this._error = tooltipContent;
			} else {
				this._DomContent = tooltipContent;
			}
		}
		if ( typeof tipsyConfig != 'undefined' ) {
			this._tipsyConfig = tipsyConfig;
		}
		if ( this._tipsyConfig == null || typeof this._tipsyConfig.gravity == undefined ) {
			this._tipsyConfig = {};
			this.setGravity( 'ne' );
		}
		this._parentObject = parentObject;
		this._initTooltip();

		jQuery.data( this._subject[0], 'wikibase.ui.tooltip', this );

		// reposition tooltip when resizing the browser window
		$( window ).off( '.wikibase.ui.tooltip' );
		$( window ).on( 'resize.wikibase.ui.tooltip', function( event ) {
			$( '[original-title]' ).each( function( i, node ) {
				if (
					typeof $( node ).data( 'wikibase.ui.tooltip' ) != 'undefined'
					&& $( node ).data( 'wikibase.ui.tooltip' )._isVisible
				) {
					var tooltip = $( node ).data( 'wikibase.ui.tooltip' );
					if ( tooltip._permanent ) {
						tooltip._isVisible = false;
						tooltip.showMessage( tooltip._permanent ); // trigger showMessage() to reposition
					}
				}
			} );
		} );
	},

	/**
	 * Initializes the tooltip for the given element.
	 * This should normally be called directly by the constructor.
	 *
	 * @param jQuery parent element
	 */
	_initTooltip: function() {
		this._subject.tipsy( {
			'gravity': this._tipsyConfig.gravity,
			'trigger': 'manual',
			'html': true
		} );
		this._tipsy = this._subject.data( 'tipsy' );
		this._toggleEvents( true );
	},

	/**
	 * construct DOM structure for an error tooltip
	 *
	 * @param object error error code and messages
	 */
	_buildErrorTooltip: function() {
		var content = (
			$( '<div/>', {
				'class': 'wb-error wb-tooltip-error',
				text: this._error.shortMessage
			} )
		);
		if ( this._error.message != '' ) { // append detailed error message
			content.addClass( 'wb-tooltip-error-top-message' );
			content = content.after( $( '<a/>', {
				'class': 'wb-tooltip-error-details-link',
				href: 'javascript:void(0);'
			} )
				.on( 'click', function( event ) {
					$( this ).parent().find( '.wb-tooltip-error-details' ).slideToggle();
				} )
				.toggle(
				function() {
					$( $( this ).children()[0] ).removeClass( 'ui-icon-triangle-1-e' );
					$( $( this ).children()[0] ).addClass( 'ui-icon-triangle-1-s' );
				},
				function() {
					$( $( this ).children()[0] ).removeClass( 'ui-icon-triangle-1-s' );
					$( $( this ).children()[0] ).addClass( 'ui-icon-triangle-1-e' );
				}
			)
				.append( $( '<span/>', {
				'class': 'ui-icon ui-icon-triangle-1-e'
			} ) )
				.append( $( '<span/>', {
				text: window.mw.msg( 'wikibase-tooltip-error-details' )
			} ) )
			)
				.after( $( '<div/>', {
				'class': 'wb-tooltip-error-details',
				text: this._error.message
			} ) )
				.after( $( '<div/>', {
				'class': 'wb-clear'
			} ) );
		}

		return content;
	},

	/**
	 * toogle tooltip events to achive a permanent state or hover functionality
	 *
	 * @param bool activate
	 */
	_toggleEvents: function( activate ) {
		if ( activate ) {
			// only attach events when not yet attached to prevent memory leak
			if (
				typeof this._subject.data( 'events' ) == 'undefined' ||
				( typeof this._subject.data( 'events' ).mouseover == 'undefined' &&
				typeof this._subject.data( 'events' ).mouseout == 'undefined' )
			) {
				this._subject.on( 'mouseover', jQuery.proxy( function() { this.show(); }, this ) );
				this._subject.on( 'mouseout', jQuery.proxy( function() { this.hide(); }, this ) );
			}
		} else {
			this._subject.off( 'mouseover' );
			this._subject.off( 'mouseout' );
		}
	},

	/**
	 * query whether hover events are attached
	 */
	_hasEvents: function() {
		if ( typeof this._subject.data( 'events' ) == 'undefined' ) {
			return false;
		} else {
			return (
				typeof this._subject.data( 'events' ).mouseover != 'undefined' &&
				typeof this._subject.data( 'events' ).mouseout != 'undefined'
			);
		}
	},

	/**
	 * Returns whether the tooltip is displayed currently.
	 *
	 * @return bool
	 */
	isVisible: function() {
		return this._isVisible();
	},

	/**
	 * show tooltip
	 *
	 * @param boolean permanent whether tooltip should be displayed permanently until hide() is being
	 *        called explicitly. false by default.
	 */
	show: function( permanent ) {
		if ( !this._isVisible ) {
			this._tipsy.show();
			if ( this._error != null ) {
				this._tipsy.$tip.addClass( 'wb-error' );

				// hide error tooltip when clicking outside of it
				this._tipsy.$tip.on( 'click', function( event ) {
					event.stopPropagation();
				} );
				$( window ).one( 'click', $.proxy( function( event ) {
					this._parentObject.removeTooltip();
				}, this ) );

				// will lose inner click event on resizing (Details link) when not re-constructed on show
				this._tipsy.$tip.find( '.tipsy-inner' ).empty().append( this._buildErrorTooltip() );
			} else if ( this._DomContent != null ) {
				this._tipsy.$tip.find( '.tipsy-inner' ).empty().append( this._DomContent );
			}
			this._isVisible = true;
		}
		if( permanent === true ) {
			this._toggleEvents( false );
			this._permanent = true;
		}
	},

	/**
	 * hide tooltip
	 */
	hide: function() {
		this._permanent = false;
		this._toggleEvents( false );
		if ( this._isVisible ) {
			this._tipsy.$tip.off( 'click' );
			this._tipsy.hide();
			this._isVisible = false;
			$( this ).triggerHandler( 'Hide' ); // call event
		}
	},

	/**
	 * set where the tooltip message shall appear
	 *
	 * @param String gravity
	 */
	setGravity: function( gravity ) {
		// flip horizontal direction in rtl language
		if ( document.documentElement.dir == 'rtl' ) {
			if ( gravity.search( /e/ ) != -1) {
				gravity = gravity.replace( /e/g, 'w' );
			} else {
				gravity = gravity.replace( /w/g, 'e' );
			}
		}
		this._tipsyConfig.gravity = gravity;
		if ( this._tipsy != null ) {
			this._tipsy.options.gravity = gravity;
		}
	},

	/**
	 * set tooltip message / HTML content
	 *
	 * @param jQuery|string content
	 */
	setContent: function( content ) {
		this._DomContent = null;
		if ( typeof content == 'string' ) {
			this._tipsy.$element.attr( 'original-title', content );
		} else {
			this._DomContent = content;
		}
	},

	/**
	 * destroy object
	 */
	destroy: function() {
		if ( this._isVisible ) {
			this.hide();
		}
		this._toggleEvents( false );
		this._tipsyConfig = null;
		this._tipsy = null;
	}

};