<?php

namespace Wikibase\Repo\Localizer;

use DataValues\DataValue;
use Language;
use SiteStore;
use ValueFormatters\FormattingException;
use ValueFormatters\NumberLocalizer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\SiteLink;
use Wikibase\EntityTitleLookup;
use Wikibase\Lib\MediaWikiNumberLocalizer;

/**
 * ValueFormatter for formatting objects that may be encountered in
 * parameters of ValueValidators\Error objects as wikitext.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class MessageParameterFormatter implements ValueFormatter {

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var SiteStore
	 */
	private $sites;

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @var NumberLocalizer
	 */
	private $valueLocalizer;

	/**
	 * @param ValueFormatter $dataValueFormatter A formatter for turning DataValues into wikitext.
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param SiteStore $sites
	 * @param Language $language
	 */
	function __construct(
		ValueFormatter $dataValueFormatter,
		EntityTitleLookup $entityTitleLookup,
		SiteStore $sites,
		Language $language
	) {
		$this->dataValueFormatter = $dataValueFormatter;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->sites = $sites;
		$this->language = $language;

		$this->valueLocalizer = new MediaWikiNumberLocalizer( $language );
	}

	/**
	 * Formats a value.
	 *
	 * @since 0.1
	 *
	 * @param mixed $value The value to format
	 *
	 * @return string The formatted value (as wikitext).
	 * @throws FormattingException
	 */
	public function format( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $this->valueLocalizer->localizeNumber( $value );
		} elseif ( $value instanceof DataValue ) {
			return $this->dataValueFormatter->format( $value );
		} elseif ( is_object( $value ) ) {
			return $this->formatObject( $value );
		} elseif ( is_array( $value ) ) {
			return $this->formatValueList( $value );
		}

		return wfEscapeWikiText( strval( $value ) );
	}

	/**
	 * @param array $values
	 *
	 * @return string[]
	 */
	private function formatValueList( $values ) {
		$formatted = array();

		foreach ( $values as $key => $value ) {
			$formatted[$key] = $this->format( $value );
		}

		//XXX: commaList should really be in the Localizer interface.
		return $this->language->commaList( $formatted );
	}

	/**
	 * @param object $value
	 *
	 * @return string The formatted value (as wikitext).
	 */
	private function formatObject( $value ) {
		if ( $value instanceof EntityId ) {
			return $this->formatEntityId( $value );
		} elseif ( $value instanceof SiteLink ) {
			return $this->formatSiteLink( $value );
		}

		// hope we can interpolate, and just fail if we can't
		return wfEscapeWikiText( strval( $value ) );
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return string The formatted ID (as a wikitext link).
	 */
	private function formatEntityId( EntityId $entityId ) {
		// @todo: this should use TitleValue + MediaWikiPageLinkRenderer!
		$title = $this->entityTitleLookup->getTitleForId( $entityId );

		$target = $title->getFullText();
		$text = wfEscapeWikiText( $entityId->getSerialization() );

		return "[[$target|$text]]";
	}

	/**
	 * @param SiteLink $link
	 *
	 * @return string The formatted link (as a wikitext link).
	 */
	private function formatSiteLink( SiteLink $link ) {
		$siteId = $link->getSiteId();
		$page = $link->getPageName();

		$site = $this->sites->getSite( $link->getSiteId() );
		$url = $site->getPageUrl( $link->getPageName() );

		return "[$url $siteId:$page]";
	}
}
