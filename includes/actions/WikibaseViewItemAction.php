<?php

namespace Wikibase;
use Language;

/**
 * Handles the view action for Wikibase items.
 *
 * TODO: utilized CachedAction once in core
 *
 * @since 0.1
 *
 * @file WikibaseViewItemAction.php
 * @ingroup Wikibase
 * @ingroup Action
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ViewItemAction extends \FormlessAction {

	/**
	 * (non-PHPdoc)
	 * @see Action::getName()
	 */
	public function getName() {
		return 'view';
	}

	/**
	 * (non-PHPdoc)
	 * @see FormlessAction::onView()
	 */
	public function onView() {
		$content = $this->getContext()->getWikiPage()->getContent();

		if ( is_null( $content ) ) {
			// TODO: show ui for editing an empty item that does not have an ID yet.
		}
		else {
			// TODO: switch on type of content.
			$contentLangCode = $this->getLanguage()->getCode();

			$parserOutput = $content->getParserOutput( $this->getTitle() );

			$out = $this->getOutput();

			// make css available when JavaScript is disabled
			$out->addModuleStyles( array( 'wikibase.common' ) );

			$out->addHTML( $parserOutput->getText() );

			// make sure required client sided resources will be loaded:
			$out->addModules( 'wikibase.ui.PropertyEditTool' );

			// overwrite page title
			$out->setPageTitle( $content->getLabel( $contentLangCode ) );

			// hand over the itemId to JS
			$out->addJsConfigVars( 'wbItemId', $content->getId() );
			$out->addJsConfigVars( 'wbDataLangName', Language::fetchLanguageName( $contentLangCode ) );
			$sites = array();

			foreach ( Sites::singleton()->getGroup( 'wikipedia' ) as /* Wikibase\Site */ $site ) {
				$sites[$site->getId()] = array(
					'shortName' => Language::fetchLanguageName( $site->getId() ),
					'name' => Language::fetchLanguageName( $site->getId() ), // TODO: names should be configurable in settings
					'pageUrl' => $site->getPageUrlPath(),
					'apiUrl' => $site->getPath( 'api.php' ),

				);
			}
			
			$out->addJsConfigVars( 'wbSiteDetails', $sites );
		}

		return '';
	}

	/**
	 * (non-PHPdoc)
	 * @see Action::getDescription()
	 */
	protected function getDescription() {
		return '';
	}

}