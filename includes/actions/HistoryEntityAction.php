<?php
namespace Wikibase;

/**
 * Handles the history action for Wikibase entities.
 *
 * @since 0.3
 *
 * @file
 * @ingroup WikibaseRepo
 * @ingroup Action
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 */
class HistoryEntityAction extends \HistoryAction {

	/**
	 * Returns the content of the page being viewed.
	 *
	 * @since 0.3
	 *
	 * @return EntityContent|null
	 */
	protected function getContent() {
		return $this->getArticle()->getPage()->getContent();
	}

	/**
	 * Return a string for use as title.
	 *
	 * @since 0.3
	 *
	 * @return \Article
	 */
	protected function getPageTitle() {
		$content = $this->getContent();

		if ( !$content ) {
			// page does not exist
			return parent::getPageTitle();
		}

		$entity = $content->getEntity();
		$langCode = $this->getContext()->getLanguage()->getCode();
		$prefixedId = ucfirst( $entity->getPrefixedId() );
		$labelText = $entity->getLabel( $langCode );
		if ( isset( $labelText ) ) {
			return $this->msg( 'wikibase-history-title-with-label', $prefixedId, $labelText )->text();
		}
		else {
			return $this->msg( 'wikibase-history-title-without-label', $prefixedId )->text();
		}
	}
}
