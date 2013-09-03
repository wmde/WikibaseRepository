/**
 * JavaScript for 'wikibase' extension, initializing some stuff when ready. This is the main
 * entry point for initializing edit tools for editing entities on entity pages.
 *
 * @since 0.1
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner at wikimedia.de >
 *
 * TODO: Refactor this huge single function into smaller pieces of code.
 */

( function( $, mw, wb ) {
	'use strict';
	/* jshint nonew: false */

	$( document ).ready( function() {
		// remove HTML edit links with links to special pages
		// for site-links we don't want to remove the table cell representing the edit section
		$( 'td.wb-editsection' ).empty();
		// for all other values we remove the whole edit section
		$( 'span.wb-editsection, div.wb-editsection' ).remove();

		// remove all infos about empty values which are displayed in non-JS
		$( '.wb-value-empty' ).empty().removeClass( 'wb-value-empty' );

		// add an edit tool for the main label. This will be integrated into the heading nicely:
		if ( $( '.wb-firstHeading' ).length ) { // Special pages do not have a custom wb heading
			var labelEditTool = new wb.ui.LabelEditTool( $( '.wb-firstHeading' )[0] ),
				editableLabel = labelEditTool.getValues( true )[0]; // [0] will always be set

			// make sure we update the 'title' tag of the page when label changes
			editableLabel.on( 'afterStopEditing', function() {
				var value;

				if( editableLabel.isEmpty() ) {
					value = mw.config.get( 'wgTitle' );
				} else {
					value = editableLabel.getValue()[0];
				}
				value += ' - ' + mw.config.get( 'wgSiteName' );

				// update 'title' tag
				$( 'html title' ).text( value );
			} );
		}

		// add an edit tool for all properties in the data view:
		$( '.wb-property-container' ).each( function() {
			// TODO: Make this nicer when we have implemented the data model
			if( $( this ).children( '.wb-property-container-key' ).attr( 'title' ) === 'description' ) {
				new wb.ui.DescriptionEditTool( this );
			} else {
				new wb.ui.PropertyEditTool( this );
			}
		} );

		var termsValueTools = [];

		$( 'tr.wb-terms-label, tr.wb-terms-description' ).each( function() {
			var $termsRow = $( this ),
				editTool = wb.ui.PropertyEditTool[
					$termsRow.hasClass( 'wb-terms-label' )
						? 'EditableLabel'
						: 'EditableDescription'
				],
				$toolbar = mw.template( 'wikibase-toolbar', '', '' ).toolbar(),
				toolbar = $toolbar.data( 'toolbar' ),
				$editGroup = mw.template( 'wikibase-toolbareditgroup', '', '' ).toolbareditgroup();

			toolbar.addElement( $editGroup );
			toolbar.$editGroup = $editGroup; // TODO: EditableLabel should not assume that this is set

			termsValueTools.push( editTool.newFromDom( $termsRow, {}, toolbar ) );
		} );

		if( mw.config.get( 'wbEntity' ) !== null ) {
			var entityJSON = $.evalJSON( mw.config.get( 'wbEntity' ) ),
				usedEntitiesJSON = $.evalJSON( mw.config.get( 'wbUsedEntities' ) ),
				unserializerFactory = new wb.serialization.SerializerFactory(),
				entityUnserializer = unserializerFactory.newUnserializerFor( wb.Entity );

			// unserializer for fetched content whose content is a wb.Entity:
			var fetchedEntityUnserializer = unserializerFactory.newUnserializerFor(
				wb.store.FetchedContent, {
					contentUnserializer: entityUnserializer
				}
			);

			wb.entity = entityUnserializer.unserialize( entityJSON );
			entityJSON = null;

			$.each( usedEntitiesJSON, function( id, fetchedEntityJSON ) {
				wb.fetchedEntities[ id ] = fetchedEntityUnserializer.unserialize( fetchedEntityJSON );
			} );

			// if there are no aliases yet, the DOM structure for creating new ones is created manually since it is not
			// needed for running the page without JS
			$( '.wb-aliases-empty' )
			.each( function() {
				$( this ).replaceWith( wb.ui.AliasesEditTool.getEmptyStructure() );
			} );

			// edit tool for aliases:
			$( '.wb-aliases' ).each( function() {
				new wb.ui.AliasesEditTool( this );
			} );

			// remove all the headings except the site link section headings
			$( '.wb-section-heading' ).not( '.wb-sitelinks-heading' ).remove();

			// BUILD CLAIMS VIEW:
			// Note: $.entityview() only works for claims right now, the goal is to use it for more
			var $claims = $( '.wb-claims' ).first(),
				$claimsParent = $claims.parent();

			$claims.detach().entityview( { // take widget subject out of DOM while initializing
				value: wb.entity
			} ).appendTo( $claimsParent );

			// add 'wb-claim' id to entity page's Claims heading:
			$( '.wb-claimlist' )
				.prev( '.wb-section-heading' )
				.first()
				.attr( 'id', 'claims' );

			// removing site links heading to rebuild it with value counter
			$( 'table.wb-sitelinks' ).each( function() {
				var group = $( this ).data( 'wb-sitelinks-group' ),
					$sitesCounterContainer = $( '<span/>' );

				$( this ).prev().append( $sitesCounterContainer );

				// actual initialization
				new wb.ui.SiteLinksEditTool( $( this ), {
					allowedSites: wb.getSitesOfGroup( group ),
					counterContainers: $sitesCounterContainer
				} );
			} );

			$( '.wb-entity' )
			.toolbarcontroller( { // BUILD TOOLBARS
				addtoolbar: ['claimlistview', 'claimsection', 'claim-qualifiers-snak', 'references', 'referenceview-snakview'],
				edittoolbar: ['statementview', 'referenceview'],
				removetoolbar: ['claim-qualifiers-snak', 'referenceview-snakview-remove']
			} )
			.claimgrouplabelscroll();
		}

		// Handle edit restrictions:
		$( wb )
		.on( 'restrictEntityPageActions blockEntityPageActions', function( event ) {
			$( '.wikibase-toolbarbutton' ).each( function( i, node ) {
				var toolbarButton = $( node ).data( 'toolbarbutton' );

				toolbarButton.disable();

				var messageId = ( event.type === 'blockEntityPageActions' )
					? 'wikibase-blockeduser-tooltip-message'
					: 'wikibase-restrictionedit-tooltip-message';

				toolbarButton.element.wbtooltip( {
					content: mw.message( messageId ).escaped(),
					gravity: 'nw'
				} );
			} );
		} );

		if (
			mw.config.get( 'wgRestrictionEdit' ) !== null &&
			mw.config.get( 'wgRestrictionEdit' ).length === 1
		) { // editing is restricted
			if (
				$.inArray(
					mw.config.get( 'wgRestrictionEdit' )[0],
					mw.config.get( 'wgUserGroups' )
				) === -1
			) {
				// user is not allowed to edit
				$( wb ).triggerHandler( 'restrictEntityPageActions' );
			}
		}

		if ( !mw.config.get( 'wbUserCanEdit' ) ) {
			$( wb ).triggerHandler( 'restrictEntityPageActions' );
		} else if ( mw.config.get( 'wbUserIsBlocked' ) ) {
			$( wb ).triggerHandler( 'blockEntityPageActions' );
		}

		if( !mw.config.get( 'wbIsEditView' ) ) {
			// no need to implement a 'disableEntityPageActions' since hiding all the toolbars directly like this is
			// not really worse than hacking the Toolbar prototype to achieve this:
			$( '.wikibase-toolbar' ).hide();
			$( 'body' ).addClass( 'wb-editing-disabled' );
			// make it even harder to edit stuff, e.g. if someone is trying to be smart, using
			// firebug to show hidden nodes again to click on them:
			$( wb ).triggerHandler( 'restrictEntityPageActions' );
		}

		$( wb ).on( 'startItemPageEditMode', function( event, origin, options ) {
			// disable language terms table's editable value or mark it as the active one if it is
			// the one being edited by the user and therefore the origin of the event
			$.each( termsValueTools, function( i, termValueTool ) {
				if ( !( origin instanceof wb.ui.PropertyEditTool.EditableValue )
					|| origin.getSubject() !== termValueTool.getSubject()
				) {
					termValueTool.disable();
				} else if ( origin && origin.getSubject() === termValueTool.getSubject() ) {
					$( 'table.wb-terms' ).addClass( 'wb-edit' );
				}
			} );

			// Display anonymous user edit warning:
			if ( mw.user && mw.user.isAnon()
				&& $.find( '.mw-notification-content' ).length === 0
				&& !$.cookie( 'wikibase-no-anonymouseditwarning' ) ) {
				mw.notify(
					mw.msg( 'wikibase-anonymouseditwarning',
						mw.msg( 'wikibase-entity-' + wb.entity.getType() )
					)
				);
			}

			// add copyright warning to 'save' button if there is one:
			if( mw.config.exists( 'wbCopyright' ) ) {

				var copyRight = mw.config.get( 'wbCopyright' ),
					copyRightVersion = copyRight.version,
					copyRightMessageHtml = copyRight.messageHtml,
					cookieKey = 'wikibase.acknowledgedcopyrightversion';

				if( copyRightVersion === $.cookie( cookieKey ) ) {
					return;
				}

				var $message = $( '<span><p>' + copyRightMessageHtml + '</p></span>' );
				var $activeToolbar = $( '.wb-edit' )
					// label/description of EditableValue always in edit mode if empty, 2nd '.wb-edit'
					// on PropertyEditTool only appended when really being edited by the user though
					.not( '.wb-ui-propertyedittool-editablevalue-ineditmode' )
					.find( '.wikibase-toolbareditgroup-ineditmode' );

				if( !$activeToolbar.length ) {
					return; // no toolbar for some reason, just stop
				}

				var toolbar = $activeToolbar.data( 'toolbareditgroup' )
					|| $activeToolbar.data( 'toolbar' );

				var $hideMessage = $( '<a/>', {
						text: mw.msg( 'wikibase-copyrighttooltip-acknowledge' )
					} ).appendTo( $message );

				var gravity = ( options && options.wbCopyrightWarningGravity )
					? options.wbCopyrightWarningGravity
					: 'nw';

				// Tooltip gets its own anchor since other elements might have their own tooltip.
				// we don't even have to add this new toolbar element to the toolbar, we only use it
				// to manage the tooltip which will have the 'save' button as element to point to.
				// The 'save' button can still have its own tooltip though.
				var $messageAnchor = $( '<span/>' )
					.appendTo( 'body' )
					.toolbarlabel()
					.wbtooltip( {
						content: $message,
						permanent: true,
						gravity: gravity,
						$anchor: toolbar.$btnSave
					} );

				$hideMessage.on( 'click', function( event ) {
					event.preventDefault();
					$messageAnchor.data( 'wbtooltip' ).degrade( true );
					$.cookie( cookieKey, copyRightVersion, { 'expires': 365*3, 'path': '/' } );
				} );

				$messageAnchor.data( 'wbtooltip' ).show();

				// destroy tooltip after edit mode gets closed again:
				$( wb ).one( 'stopItemPageEditMode', function( event ) {
					$messageAnchor.data( 'wbtooltip' ).degrade( true );
				} );
			}
		} );

		$( wb ).on( 'stopItemPageEditMode', function( event ) {
			$( 'table.wb-terms' ).removeClass( 'wb-edit' );
			$.each( termsValueTools, function( i, termValueTool ) {
				termValueTool.enable();
			} );
		} );

		// remove loading spinner after JavaScript has kicked in
		$( '.wb-entity' ).fadeTo( 0, 1 );
		$( '.wb-entity-spinner' ).remove();

	} );

} )( jQuery, mediaWiki, wikibase );
