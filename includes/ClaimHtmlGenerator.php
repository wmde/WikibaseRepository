<?php

namespace Wikibase;

use InvalidArgumentException;
use Wikibase\Lib\FormattingException;
use Wikibase\Lib\PropertyNotFoundException;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\SnakFormatter;

/**
 * Base class for generating the HTML for a Claim in Entity View.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 *
 * @author H. Snater < mediawiki@snater.com >
 * @author Pragunbhutani
 * @author Katie Filbert < aude.wiki@gmail.com>
 */
class ClaimHtmlGenerator {

	/**
	 * @since 0.4
	 *
	 * @var SnakFormatter
	 */
	protected $snakFormatter;

	/**
	 * @since 0.5
	 *
	 * @var EntityTitleLookup
	 */
	protected $entityTitleLookup;

	/**
	 * Array of property labels indexed by serialized property ids
	 * @since 0.5
	 *
	 * @var string[]
	 */
	protected $propertyLabels;

	/**
	 * Constructor.
	 *
	 * @param SnakFormatter $snakFormatter
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param string[] $propertyLabels
	 */
	public function __construct(
		SnakFormatter $snakFormatter,
		EntityTitleLookup $entityTitleLookup,
		$propertyLabels = array()
	) {
		$this->snakFormatter = $snakFormatter;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->propertyLabels = $propertyLabels;
	}

	/**
	 * Returns the Html for the main Snak.
	 *
	 * @param string $formattedValue
	 * @return string
	 */
	protected function getMainSnakHtml( $formattedValue ) {
		$mainSnakHtml = wfTemplate( 'wb-snak',
			'wb-mainsnak',
			'', // Link to property. NOTE: we don't display this ever (instead, we generate it on
				// Claim group level) If this was a public function, this should be generated
				// anyhow since important when displaying a Claim on its own.
			'', // type selector, JS only
			( $formattedValue === '' ) ? '&nbsp;' : $formattedValue
		);

		return $mainSnakHtml;
	}

	/**
	 * Builds and returns the HTML representing a single WikibaseEntity's claim.
	 *
	 * @since 0.4
	 *
	 * @param Claim $claim the claim to render
	 * @param null|string $editSectionHtml has the html for the edit section
	 *
	 * @return string
	 */
	public function getHtmlForClaim( Claim $claim, $editSectionHtml = null ) {
		wfProfileIn( __METHOD__ );

		$mainSnakHtml = $this->getMainSnakHtml(
			$this->getFormattedSnakValue( $claim->getMainSnak() )
		);

		$rankHtml = '';
		$referencesHeading = '';
		$referencesHtml = '';

		if( is_a( $claim, 'Wikibase\Statement' ) ) {
			$serializedRank = ClaimSerializer::serializeRank( $claim->getRank() );

			// Messages: wikibase-statementview-rank-preferred, wikibase-statementview-rank-normal,
			// wikibase-statementview-rank-deprecated
			$rankHtml = wfTemplate( 'wb-rankselector',
				'ui-state-disabled',
				'wb-rankselector-' . $serializedRank,
				wfMessage( 'wikibase-statementview-rank-' . $serializedRank )->text()
			);

			$referenceList = $claim->getReferences();
			$referencesHeading = wfMessage(
				'wikibase-ui-pendingquantitycounter-nonpending',
				wfMessage(
					'wikibase-statementview-referencesheading-pendingcountersubject',
					count( $referenceList )
				)->text(),
				count( $referenceList )
			)->text();

			$referencesHtml = $this->getHtmlForReferences( $claim->getReferences() );
		}

		// @todo: Use 'wb-claim' or 'wb-statement' template accordingly
		// @todo: get rid of usage of global wfTemplate function
		$claimHtml = wfTemplate( 'wb-statement',
			$rankHtml,
			$claim->getGuid(),
			$mainSnakHtml,
			$this->getHtmlForQualifiers( $claim->getQualifiers() ),
			$editSectionHtml,
			$referencesHeading,
			$referencesHtml
		);

		wfProfileOut( __METHOD__ );
		return $claimHtml;
	}

	/**
	 * Generates and returns the HTML representing a claim's qualifiers.
	 *
	 * @param Snaks $qualifiers
	 * @return string
	 */
	protected function getHtmlForQualifiers( Snaks $qualifiers ) {
		$qualifiersByProperty = new ByPropertyIdArray( $qualifiers );
		$qualifiersByProperty->buildIndex();

		$snaklistviewsHtml = '';

		foreach( $qualifiersByProperty->getPropertyIds() as $propertyId ) {
			$snaklistviewsHtml .= $this->getSnaklistviewHtml(
				$qualifiersByProperty->getByPropertyId( $propertyId )
			);
		}

		return $this->wrapInListview( $snaklistviewsHtml );
	}

	/**
	 * Generates the HTML for a ReferenceList object.
	 *
	 * @param ReferenceList $referenceList
	 * @return string
	 */
	protected function getHtmlForReferences( ReferenceList $referenceList ) {
		$referencesHtml = '';

		foreach( $referenceList as $reference ) {
			$referencesHtml .= $this->getHtmlForReference( $reference );
		}

		return $this->wrapInListview( $referencesHtml );
	}

	private function wrapInListview( $listviewContent ) {
		if( $listviewContent !== '' ) {
			return wfTemplate( 'wb-listview', $listviewContent );
		} else {
			return '';
		}
	}

	/**
	 * Generates the HTML for a Reference object.
	 *
	 * @param Reference $reference
	 * @return string
	 */
	protected function getHtmlForReference( $reference ) {
		$referenceSnaksByProperty = new ByPropertyIdArray( $reference->getSnaks() );
		$referenceSnaksByProperty->buildIndex();

		$snaklistviewsHtml = '';

		foreach( $referenceSnaksByProperty->getPropertyIds() as $propertyId ) {
			$snaklistviewsHtml .= $this->getSnaklistviewHtml(
				$referenceSnaksByProperty->getByPropertyId( $propertyId )
			);
		}

		return wfTemplate( 'wb-referenceview',
			'wb-referenceview-' . $reference->getHash(),
			$snaklistviewsHtml
		);
	}

	/**
	 * Generates the HTML for a list of snaks.
	 *
	 * @param Snak[] $snaks
	 * @return string
	 */
	protected function getSnaklistviewHtml( $snaks ) {
		$snaksHtml = '';
		$i = 0;

		foreach( $snaks as $snak ) {
			$snaksHtml .= $this->getSnakHtml( $snak, ( $i++ === 0 ) );
		}

		return wfTemplate(
			'wb-snaklistview',
			$snaksHtml
		);
	}

	/**
	 * Generates the HTML for a single snak.
	 *
	 * @param Snak $snak
	 * @param boolean $showPropertyLink
	 * @return string
	 */
	protected function getSnakHtml( $snak, $showPropertyLink = false ) {
		$propertyLink = '';

		if( $showPropertyLink ) {
			$propertyId = $snak->getPropertyId();
			$propertyKey = $propertyId->getSerialization();
			$propertyLabel = isset( $this->propertyLabels[$propertyKey] )
				? $this->propertyLabels[$propertyKey]
				: $propertyKey;
			$propertyLink = \Linker::link(
				$this->entityTitleLookup->getTitleForId( $propertyId ),
				htmlspecialchars( $propertyLabel )
			);
		}

		return wfTemplate( 'wb-snak',
			'wb-snakview',
			// Display property link only once for snaks featuring the same property:
			$propertyLink,
			'',
			( $snak->getType() === 'value' ) ? $this->getFormattedSnakValue( $snak ) : ''
		);
	}

	/**
	 * @fixme handle errors more consistently as done in JS UI, and perhaps add
	 * localised exception messages.
	 *
	 * @param Snak $snak
	 * @return string
	 */
	protected function getFormattedSnakValue( $snak ) {
		try {
			$formattedSnak = $this->snakFormatter->formatSnak( $snak );
		} catch ( FormattingException $ex ) {
			return $this->getInvalidSnakMessage();
		} catch ( PropertyNotFoundException $ex ) {
			return $this->getPropertyNotFoundMessage();
		} catch ( InvalidArgumentException $ex ) {
			return $this->getInvalidSnakMessage();
		}

		return $formattedSnak;
	}

	/**
	 * @return string
	 */
	private function getInvalidSnakMessage() {
		return wfMessage( 'wikibase-snakformat-invalid-value' )->parse();
	}

	/**
	 * @return string
	 */
	private function getPropertyNotFoundMessage() {
		return wfMessage ( 'wikibase-snakformat-propertynotfound' )->parse();
	}
}
