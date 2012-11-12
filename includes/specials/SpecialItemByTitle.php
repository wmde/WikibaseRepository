<?php

/**
 * Enables accessing items by providing the identifier of a site and the title
 * of the corresponding page on that site.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SpecialItemByTitle extends SpecialItemResolver {

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		parent::__construct( 'ItemByTitle' );
	}

	/**
	 * Main method.
	 *
	 * @since 0.1
	 *
	 * @param string|null $subPage
	 *
	 * @return boolean
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		// Setup
		$request = $this->getRequest();
		$parts = ( $subPage === '' ) ? array() : explode( '/', $subPage, 2 );
		$siteId = $request->getVal( 'site', isset( $parts[0] ) ? $parts[0] : '' );
		$page = $request->getVal( 'page', isset( $parts[1] ) ? $parts[1] : '' );

		$pageTitle = '';
		$itemContent = null;

		if ( !empty( $page ) ) {
			$title = \Title::newFromText( $page );

			if ( $title !== null ) {
				$pageTitle = $title->getText();
			} else {
				// TODO: throw error, page title contains invalid chars
				$pageTitle = '';
			}

			// Create an item view
			if ( isset( $siteId ) && isset( $pageTitle ) ) {
				$itemContent = \Wikibase\ItemHandler::singleton()->getFromSiteLink( $siteId, $pageTitle );

				if ( $itemContent !== null ) {
					$itemUrl = $itemContent->getTitle()->getFullUrl();
					$this->getOutput()->redirect( $itemUrl );
				}
			}
		}

		// If there is no item content post the switch form
		if ( $itemContent === null ) {
			$this->switchForm( $siteId, $pageTitle );
		}
	}

	/**
	 * Output a form to allow searching for a page
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $page
	 */
	protected function switchForm( $siteId, $page ) {

		$group = \Wikibase\Settings::get( 'siteLinkGroup' );
		$sites = \Sites::singleton()->getSiteGroup( $group );

		$siteExists = $sites->hasSite( $siteId );

		$this->getOutput()->addModules( 'wikibase.special.itemByTitle' );

		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				array(
					'method' => 'get',
					'action' => $this->getTitle()->getFullUrl(),
					'name' => 'itembytitle',
					'id' => 'wb-itembytitle-form1'
				)
			)
			. Html::openElement( 'fieldset' )
			. Html::element(
				'legend',
				array(),
				$this->msg( 'wikibase-itembytitle-lookup-fieldset' )->text()
			)
			. Html::element(
				'label',
				array( 'for' => 'wb-itembytitle-sitename' ),
				$this->msg( 'wikibase-itembytitle-lookup-site' )->text()
			)
			. Html::input(
				'site',
				$siteId ? htmlspecialchars( $siteId ) : '',
				'text',
				array(
					'id' => 'wb-itembytitle-sitename',
					'size' => 12
				)
			)
			. ' '
			. Html::element(
				'label',
				array( 'for' => 'pagename' ),
				$this->msg( 'wikibase-itembytitle-lookup-page' )->text()
			)
			. Html::input(
				'page',
				$page ? htmlspecialchars( $page ) : '',
				'text',
				array(
					'id' => 'pagename',
					'size' => 36,
					'class' => 'wb-input-text'
				)
			)
			. Html::input(
				'submit',
				$this->msg( 'wikibase-itembytitle-submit' )->text(),
				'submit',
				array(
					'id' => 'wb-itembytitle-submit',
					'class' => 'wb-input-button'
				)
			)
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' )
		);
		
		if ( $siteExists && isset( $page ) ) {
			$this->getOutput()->addHTML(
				Html::openElement( 'div' )
				. $this->msg( 'wikibase-itembytitle-create' )
					->params(
						wfUrlencode( $siteId ? $siteId : '' ),
						wfUrlencode( $page ? $page : '' )
					)
					->parse()
				. Html::closeElement( 'div' )
			);
		}

	}

}
