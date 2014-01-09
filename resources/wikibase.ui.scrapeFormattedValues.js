/**
 * Scrape initial formatted value from static HTML.
 * By intention, only formatted values of the Quantity data value are scraped. Instead of adding
 * additional flexibility to the scraping mechanism, front-end widgets should be initialized
 * properly on the static HTML rendering this file and its resource loader module obsolete.
 * @since 0.5
 * @licence GNU GPL v2+
 *
 * @author: H. Snater < mediawiki@snater.com >
 */
( function( $, mw, wb ) {
	'use strict';

	// TODO: This whole file along with its resource loader module should be be removed. This
	// requires initializing the ui widgets on top of the DOM generated by the backend instead of
	// re-constructing the DOM in JavaScript.

	mw.hook( 'wikipage.content' ).add( function() {

		if( mw.config.get( 'wbEntity' ) === null ) {
			return;
		}

		wb.__formattedValues = {};
		$.each( wb.entity.getClaims(), function( i, claim ) {
			var $claim = null,
				mainSnakDataValue = claim.getMainSnak().getValue(),
				$qualifierValues = null,
				iQualifiers = 0,
				$referenceValues = null,
				iReferences = 0;

			if( mainSnakDataValue.getType() === 'quantity' ) {
				$claim = getClaimNode( claim.getGuid() );

				wb.__formattedValues[JSON.stringify( claim.getMainSnak().getValue().toJSON() )]
					= $claim.children( '.wb-claim-mainsnak' ).find( '.wb-snak-value' ).text();
			}

			$.each( claim.getQualifiers().getGroupedSnakLists(), function( j, snakList ) {
				snakList.each( function( k, snak ) {
					if( snak.getValue().getType() === 'quantity' ) {
						$claim = $claim || getClaimNode( claim.getGuid() );
						$qualifierValues = $qualifierValues || getQualifierValueNodes( $claim );

						wb.__formattedValues[JSON.stringify( snak.getValue().toJSON() )]
							= $qualifierValues.eq( iQualifiers++ ).text();
					}
				} );
			} );

			$.each( claim.getReferences(), function( j, reference ) {
				$.each( reference.getSnaks().getGroupedSnakLists(), function( j, snakList ) {
					snakList.each( function( k, snak ) {
						if( snak.getValue().getType() === 'quantity' ) {
							$referenceValues
								= $referenceValues || getReferenceValueNodes( reference.getHash() );

							wb.__formattedValues[JSON.stringify( snak.getValue().toJSON() )]
								= $referenceValues.eq( iReferences++ ).text();
						}
					} );
				} );

			} );

		} );

	} );

	/**
	 * @param {String} guid
	 * @return {jQuery}
	 */
	function getClaimNode( guid ) {
		return $( document.getElementsByClassName( 'wb-claim-' + guid )[0] );
	}

	/**
	 * @param {jQuery} $claim
	 * @return {jQuery}
	 */
	function getQualifierValueNodes( $claim ) {
		return $claim.children( '.wb-claim-qualifiers' ).find( '.wb-snak-value' );
	}

	/**
	 * @param {String} hash
	 * @return {jQuery}
	 */
	function getReferenceValueNodes( hash ) {
		var $reference = $( document.getElementsByClassName( 'wb-referenceview-' + hash )[0] );
		return $reference.find( '.wb-snak-value' );
	}

} )( jQuery, mediaWiki, wikibase );





