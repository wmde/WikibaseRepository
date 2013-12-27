<?php

namespace Wikibase\Repo\Specials;

use Html;
use Language;
use Wikibase\Item;
use Wikibase\ItemDisambiguation;
use Wikibase\Repo\WikibaseRepo;

/**
 * Enables accessing items by providing the label of the item and the language of the label.
 * A result page is shown, disambiguating between multiple results if necessary.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SpecialItemDisambiguation extends SpecialItemResolver {

	/**
	 * Constructor.
	 *
	 * @see SpecialItemResolver::__construct
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// args $name, $restriction, $listed
		parent::__construct( 'ItemDisambiguation', '', true );
	}

	/**
	 * @see SpecialItemResolver::execute
	 *
	 * @since 0.1
	 */
	public function execute( $subPage ) {
		if ( !parent::execute( $subPage ) ) {
			return false;
		}

		// Setup
		$request = $this->getRequest();
		$parts = $subPage === '' ? array() : explode( '/', $subPage, 2 );
		$language = $request->getVal( 'language', isset( $parts[0] ) ? $parts[0] : '' );

		if ( $language === '' ) {
			$language = $this->getLanguage()->getCode();
		}

		if ( $request->getCheck( 'label' ) ) {
			$label = $request->getText( 'label' );
		}
		else {
			$label = isset( $parts[1] ) ? str_replace( '_', ' ', $parts[1] ) : '';
		}

		$this->switchForm( $language, $label );

		// Display the result set
		if ( isset( $language ) && isset( $label ) && $label !== '' ) {
			// TODO: should search over aliases as well, not just labels
			$itemContents = WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getFromLabel(
				$language,
				$label,
				null,
				Item::ENTITY_TYPE,
				true
			);

			if ( 0 < count( $itemContents ) ) {
				$this->getOutput()->setPageTitle( $this->msg( 'wikibase-disambiguation-title', $label )->escaped() );
				$this->displayDisambiguationPage( $itemContents, $language );
			} else {
				// No results found
				if ( ( Language::isValidBuiltInCode( $language ) && ( Language::fetchLanguageName( $language ) !== "" ) ) ) {
					// No valid language code
					$this->getOutput()->addWikiMsg( 'wikibase-itemdisambiguation-nothing-found' );

					if ( $language === $this->getLanguage()->getCode() ) {
						$this->getOutput()->addWikiMsg(
							'wikibase-itemdisambiguation-search',
							urlencode( $label )
						);
						$this->getOutput()->addWikiMsg(
							'wikibase-itemdisambiguation-create',
							urlencode( $label )
						);
					}
				} else {
					$this->getOutput()->addWikiMsg( 'wikibase-itemdisambiguation-invalid-langcode' );
				}
			}
		}

		return true;
	}

	/**
	 * Display disambiguation page.
	 *
	 * @since 0.1
	 *
	 * @param array $items
	 * @param string $langCode
	 */
	protected function displayDisambiguationPage( array /* of ItemContent */ $items, $langCode ) {
		$disambiguationList = new ItemDisambiguation( $items, $langCode, $this->getContext() );
		$disambiguationList->display();
	}

	/**
	 * Output a form to allow searching for labels
	 *
	 * @since 0.1
	 *
	 * @param string|null $langCode
	 * @param string|null $label
	 */
	protected function switchForm( $langCode, $label ) {
		$this->getOutput()->addModules( 'wikibase.special.itemDisambiguation' );

		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				array(
					'method' => 'get',
					'action' => $this->getPageTitle()->getFullUrl(),
					'name' => 'itemdisambiguation',
					'id' => 'wb-itemdisambiguation-form1'
				)
			)
			. Html::openElement( 'fieldset' )
			. Html::element(
				'legend',
				array(),
				$this->msg( 'wikibase-itemdisambiguation-lookup-fieldset' )->text()
			)
			. Html::element(
				'label',
				array( 'for' => 'wb-itemdisambiguation-languagename' ),
				$this->msg( 'wikibase-itemdisambiguation-lookup-language' )->text()
			)
			. Html::input(
				'language',
				$langCode ? $langCode : '',
				'text',
				array(
					'id' => 'wb-itemdisambiguation-languagename',
					'size' => 12,
					'class' => 'wb-input-text'
				)
			)
			. ' '
			. Html::element(
				'label',
				array( 'for' => 'labelname' ),
				$this->msg( 'wikibase-itemdisambiguation-lookup-label' )->text()
			)
			. Html::input(
				'label',
				$label ? $label : '',
				'text',
				array(
					'id' => 'labelname',
					'size' => 36,
					'class' => 'wb-input-text',
					'autofocus'
				)
			)
			. Html::input(
				'submit',
				$this->msg( 'wikibase-itemdisambiguation-submit' )->text(),
				'submit',
				array(
					'id' => 'wb-itembytitle-submit',
					'class' => 'wb-input-button'
				)
			)
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' )
		);
	}

}
