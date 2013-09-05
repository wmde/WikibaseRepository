<?php

use \ValueFormatters\ValueFormatterFactory;

/**
 * Base for special pages that resolve certain arguments to an item.
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
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class SpecialItemResolver extends SpecialWikibasePage {

	// TODO: would we benefit from using cached page here?

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param string $name
	 * @param string $restriction
	 * @param boolean $listed
	 */
	public function __construct( $name = '', $restriction = '', $listed = true ) {
		parent::__construct( $name, $restriction, $listed );
	}

	/**
	 * @see SpecialWikibasePagePage::$subPage
	 *
	 * @since 0.1
	 */
	public $subPage;

	/**
	 * @see SpecialPage::getDescription
	 *
	 * @since 0.1
	 */
	public function getDescription() {
		return $this->msg( 'special-' . strtolower( $this->getName() ) )->text();
	}

	/**
	 * @see SpecialPage::setHeaders
	 *
	 * @since 0.1
	 */
	public function setHeaders() {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setPageTitle( $this->getDescription() );
	}

	/**
	 * @see SpecialPage::execute
	 *
	 * @since 0.1
	 */
	public function execute( $subPage ) {
		$subPage = is_null( $subPage ) ? '' : $subPage;
		$this->subPage = trim( str_replace( '_', ' ', $subPage ) );

		$this->setHeaders();
		$this->outputHeader();

		// If the user is authorized, display the page, if not, show an error.
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return false;
		}

		return true;
	}

	/**
	 * Displays the item.
	 *
	 * @since 0.1
	 *
	 * @param Wikibase\ItemContent $itemContent
	 */
	protected function displayItem( Wikibase\ItemContent $itemContent ) {
		$valueFormatters = new ValueFormatterFactory( $GLOBALS['wgValueFormatters'] );

		$view = \Wikibase\EntityView::newForEntityContent( $itemContent, $valueFormatters, $this->getContext() );
		$view->render( $itemContent );

		$this->getOutput()->setPageTitle( $itemContent->getItem()->getLabel( $this->getLanguage()->getCode() ) );
	}

}
