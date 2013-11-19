<?php

namespace Wikibase\Repo\Specials;

use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\EntityView;
use Wikibase\ItemContent;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Specials\SpecialWikibasePage;
use Wikibase\Repo\WikibaseRepo;

/**
 * Base for special pages that resolve certain arguments to an item.
 *
 * @since 0.1
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
	 * @param ItemContent $itemContent
	 */
	protected function displayItem( ItemContent $itemContent ) {
		$formatterOptions = new FormatterOptions(); //TODO: Language Fallback
		$formatterOptions->setOption( ValueFormatter::OPT_LANG, $this->getContext()->getLanguage()->getCode() );

		$snakFormatter = WikibaseRepo::getDefaultInstance()->getSnakFormatterFactory()
			->getFormatter( SnakFormatter::FORMAT_HTML_WIDGET, $formatterOptions );

		$dataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
		$entityLoader = WikibaseRepo::getDefaultInstance()->getStore()->getEntityLookup();

		$view = EntityView::newForEntityContent( $itemContent, $snakFormatter, $dataTypeLookup, $entityLoader );
		$view->render( $itemContent );

		$this->getOutput()->setPageTitle( $itemContent->getItem()->getLabel( $this->getLanguage()->getCode() ) );
	}

}
