<?php

namespace Wikibase;
use Html, ParserOutput, Title, Language, OutputPage, Sites, MediaWikiSite;

/**
 * Class for creating views for Wikibase\Item instances.
 * For the Wikibase\Item this basically is what the Parser is for WikitextContent.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Werner
 */
class ItemView extends EntityView {

	/**
	 * @see EntityView::getInnerHtml
	 */
	public function getInnerHtml( EntityContent $entity, Language $lang = null, $editable = true ) {
		$html = parent::getInnerHtml( $entity, $lang, $editable );

		// add site-links to default entity stuff
		$html .= $this->getHtmlForSiteLinks( $entity, $lang, $editable );

		return $html;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's site-links.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $item the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForSiteLinks( EntityContent $item, Language $lang = null, $editable = true ) {
		$siteLinks = $item->getItem()->getSiteLinks();
		$html = $thead = $tbody = $tfoot = '';

		$html .= wfTemplate( 'wb-section-heading', wfMessage( 'wikibase-sitelinks' ) );

		if( !empty( $siteLinks ) ) {
			$thead = wfTemplate( 'wb-sitelinks-thead',
				wfMessage( 'wikibase-sitelinks-sitename-columnheading' ),
				wfMessage( 'wikibase-sitelinks-siteid-columnheading' ),
				wfMessage( 'wikibase-sitelinks-link-columnheading' )
			);
		}

		$i = 0;

		// Batch load the sites we need info about during the building of the sitelink list.
		$sites = Sites::singleton()->getSites();

		// Sort the sitelinks according to their global id
		$safetyCopy = $siteLinks; // keep a shallow copy;
		$sortOk = usort(
			$siteLinks,
			function( $a, $b ) {
				return strcmp($a->getSite()->getGlobalId(), $b->getSite()->getGlobalId() );
			}
		);
		if ( !$sortOk ) {
			$siteLinks = $safetyCopy;
		}

		/**
		 * @var SiteLink $link
		 */
		foreach( $siteLinks as $link ) {
			$alternatingClass = ( $i++ % 2 ) ? 'even' : 'uneven';

			$site = $link->getSite();

			if ( $site->getDomain() === '' ) {
				// the link is pointing to an unknown site.
				// XXX: hide it? make it red? strike it out?

				$tbody .= wfTemplate( 'wb-sitelink-unknown',
					$alternatingClass,
					htmlspecialchars( $link->getSite()->getGlobalId() ),
					htmlspecialchars( $link->getPage() ),
					$this->getHtmlForEditSection( $item, $lang, '', 'td' ) // TODO: add link to SpecialPage
				);

			} else {
				$languageCode = $site->getLanguageCode();

				// TODO: for non-JS, also set the dir attribute on the link cell;
				// but do not build language objects for each site since it causes too much load
				// and will fail when having too much site links
				$tbody .= wfTemplate( 'wb-sitelink',
					$languageCode,
					$alternatingClass,
					htmlspecialchars( Utils::fetchLanguageName( $languageCode ) ), // TODO: get an actual site name rather then just the language
					htmlspecialchars( $languageCode ), // TODO: get an actual site id rather then just the language code
					htmlspecialchars( $link->getUrl() ),
					htmlspecialchars( $link->getPage() ),
					$this->getHtmlForEditSection( $item, $lang, '', 'td' ) // TODO: add link to SpecialPage
				);
			}
		}

		// built table footer with button to add site-links, consider list could be complete!
		$isFull = count( $siteLinks ) >= count( $sites );

		$tfoot = wfTemplate( 'wb-sitelinks-tfoot',
			$isFull ? wfMessage( 'wikibase-sitelinksedittool-full' )->text() : '',
			$this->getHtmlForEditSection( $item, $lang, '', 'td', 'add', !$isFull ) // TODO: add link to SpecialPage
		);

		return $html . wfTemplate( 'wb-sitelinks-table', $thead, $tbody, $tfoot );
	}

}
