<?php

namespace Wikibase;

use Html;
use ParserOptions;
use ParserOutput;
use Title;
use Language;
use IContextSource;
use OutputPage;
use MediaWikiSite;
use MWException;
use FormatJson;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Lib\PropertyDataTypeLookup;
use Wikibase\Lib\Serializers\EntitySerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;
use ValueFormatters\ValueFormatterFactory;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use ValueFormatters\TimeFormatter;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\MwTimeIsoFormatter;

/**
 * Base class for creating views for all different kinds of Wikibase\Entity.
 * For the Wikibase\Entity this basically is what the Parser is for WikitextContent.
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
 * @todo  We might want to re-design this at a later point, designing this as a more generic and encapsulated rendering
 *        of DataValue instances instead of having functions here for generating different parts of the HTML. Right now
 *        these functions require an EntityContent while a DataValue (if it were implemented) should be sufficient.
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki at snater.com >
 * @author Daniel Werner
 * @author Daniel Kinzler
 */
abstract class EntityView extends \ContextSource {

	/**
	 * @since 0.4
	 *
	 * @var ValueFormatterFactory
	 */
	protected $valueFormatters;

	/**
	 * @var EntityIdFormatter
	 */
	protected $idFormatter;

	/**
	 * @var EntityLookup
	 */
	protected $entityLookup;

	/**
	 * @var EntityTitleLookup
	 */
	protected $entityTitleLookup;

	/**
	 * @var PropertyDataTypeLookup
	 */
	protected $dataTypeLookup;

	/**
	 * Maps entity types to the corresponding entity view.
	 * FIXME: remove this stuff, big OCP violation
	 *
	 * @since 0.2
	 *
	 * @var array
	 */
	public static $typeMap = array(
		Item::ENTITY_TYPE => '\Wikibase\ItemView',
		Property::ENTITY_TYPE => '\Wikibase\PropertyView',

		// TODO: Query::ENTITY_TYPE
		'query' => '\Wikibase\QueryView',
	);

	/**
	 * @since    0.1
	 *
	 * @param IContextSource|null        $context
	 * @param ValueFormatterFactory      $valueFormatters
	 * @param Lib\PropertyDataTypeLookup $dataTypeLookup
	 * @param EntityLookup               $entityLookup
	 * @param EntityTitleLookup          $entityTitleLookup
	 * @param Lib\EntityIdFormatter      $idFormatter
	 */
	public function __construct(
		IContextSource $context,
		ValueFormatterFactory $valueFormatters,
		PropertyDataTypeLookup $dataTypeLookup,
		EntityLookup $entityLookup,
		EntityTitleLookup $entityTitleLookup,
		EntityIdFormatter $idFormatter
	) {
		$this->setContext( $context );
		$this->valueFormatters = $valueFormatters;
		$this->dataTypeLookup = $dataTypeLookup;
		$this->entityLookup = $entityLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->idFormatter = $idFormatter;
	}

	/**
	 * Builds and returns the HTML representing a whole WikibaseEntity.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtml( EntityContent $entity, Language $lang = null, $editable = true ) {
		wfProfileIn( __METHOD__ );

		//NOTE: even though $editable is unused at the moment, we will need it for the JS-less editing model.
		if ( !$lang ) {
			$lang = $this->getLanguage();
		}

		$entityId = $entity->getEntity()->getId() ?: 'new'; // if id is not set, use 'new' suffix for css classes
		$html = '';

		$html .= wfTemplate( 'wb-entity',
			$entity->getEntity()->getType(),
			$entityId,
			$lang->getCode(),
			$lang->getDir(),
			$this->getInnerHtml( $entity, $lang, $editable )
		);

		// show loading spinner as long as JavaScript is initialising;
		// the fastest way to show the loading spinner is placing the script right after the
		// corresponsing html
		$html .= Html::inlineScript( '
			$( ".wb-entity" ).fadeTo( 0, .3 ).after( function() {
				var $div = $( "<div/>" ).addClass( "wb-entity-spinner mw-small-spinner" );
				$div.css( "top", $div.height() + "px" );
				$div.css(
					( "' . $lang->getDir() . '" === "rtl" ) ? "right" : "left",
					( parseInt( $( this ).width() / 2 ) - $div.width() / 2 ) + "px"
				);
				return $div;
			} );

			// Remove loading spinner after a couple of seconds in any case. (e.g. some resource
			// might not have been loaded silently, so JavaScript is not initialising)
			// Additionally attaching to window.error would only make sense before any other
			// JavaScript is parsed. Since the JavaScript is loaded in the header, it does not make
			// any sense to attach to window.error here.
			window.setTimeout( function() {
				$( ".wb-entity" ).fadeTo( 0, 1 );
				$( ".wb-entity-spinner" ).remove();
			}, 7000 );
		' );

		wfProfileOut( __METHOD__ );
		return $html;
	}

	protected function getFormattedIdForEntity( Entity $entity ) {
		if ( !$entity->getId() ) {
			return ''; //XXX: should probably throw an exception
		}

		return $this->idFormatter->format( $entity->getId() );
	}

	/**
	 * Builds and returns the inner HTML for representing a whole WikibaseEntity. The difference to getHtml() is that
	 * this does not group all the HTMl within one parent node as one entity.
	 *
	 * @string
	 *
	 * @param EntityContent $entity
	 * @param \Language $lang
	 * @param bool $editable
	 * @return string
	 */
	public function getInnerHtml( EntityContent $entity, Language $lang = null, $editable = true ) {
		wfProfileIn( __METHOD__ );

		$claims = '';
		$languageTerms = '';

		if ( $entity->getEntity()->getType() === 'item' ) {
			$claims = $this->getHtmlForClaims( $entity, $lang, $editable );
		}

		$languageTerms = $this->getHtmlForLanguageTerms( $entity, $lang, $editable );

		$html = wfTemplate( 'wb-entity-content',
			$this->getHtmlForLabel( $entity, $lang, $editable ),
			$this->getHtmlForDescription( $entity, $lang, $editable ),
			$this->getHtmlForAliases( $entity, $lang, $editable ),
			$languageTerms,
			$claims
		);

		wfProfileOut( __METHOD__ );
		return $html;
	}

	protected function makeParserOptions( ) {
		$options = ParserOptions::newFromContext( $this );
		$options->setEditSection( false ); //NOTE: editing is disabled per default
		return $options;
	}

	/**
	 * Renders an entity into an ParserOutput object
	 *
	 * @since 0.1
	 *
	 * @param EntityContent       $entity the entity to analyze/render
	 * @param null|\ParserOptions $options parser options. If nto provided, the local context will be used to create generic parser options.
	 * @param bool                $generateHtml whether to generate HTML. Set to false if only interested in meta-info. default: true.
	 *
	 * @return ParserOutput
	 */
	public function getParserOutput( EntityContent $entity, ParserOptions $options = null, $generateHtml = true ) {
		wfProfileIn( __METHOD__ );

		if ( !$options ) {
			$options = $this->makeParserOptions();
		}

		$langCode = $options->getTargetLanguage();
		$editable = $options->getEditSection(); //XXX: apparently, EditSections isn't included in the parser cache key?!

		//@todo: would be nice to disable editing if the user isn't allowed to do that.
		//@todo: but this breaks the parser cache! So this needs to be done from the outside, per request.
		//if ( !$this->getTitle()->quickUserCan( "edit" ) ) {
		//	$editable = false;
		//}

		// fresh parser output with entity markup
		$pout = new ParserOutput();

		$allSnaks = $entity->getEntity()->getAllSnaks();

		// treat referenced entities as page links ------
		$refFinder = new ReferencedEntitiesFinder();
		$usedEntityIds = $refFinder->findSnakLinks( $allSnaks );

		$contentFactory = EntityContentFactory::singleton();

		foreach ( $usedEntityIds as $entityId ) {
			$pout->addLink( $this->entityTitleLookup->getTitleForId( $entityId ) );
		}

		// treat URL values as external links ------
		$urlFinder = new ReferencedUrlFinder( $this->dataTypeLookup );
		$usedUrls = $urlFinder->findSnakLinks( $allSnaks );

		foreach ( $usedUrls as $url ) {
			$pout->addExternalLink( $url );
		}

		if ( $generateHtml ) {
			$html = $this->getHtml( $entity, $langCode, $editable );
			$pout->setText( $html );
		}

		//@todo: record sitelinks as iwlinks
		//@todo: record CommnsMedia values as imagelinks

		// make css available for JavaScript-less browsers
		$pout->addModuleStyles( array(
			'wikibase.common',
			'jquery.wikibase.toolbar',
		) );

		// make sure required client sided resources will be loaded:
		$pout->addModules( 'wikibase.ui.entityViewInit' );

		//FIXME: some places, like Special:NewItem, don't want to override the page title.
		//       But we still want to use OutputPage::addParserOutput to apply the modules etc from the ParserOutput.
		//       So, for now, we leave it to the caller to override the display title, if desired.
		// set the display title
		//$pout->setTitleText( $entity>getLabel( $langCode ) );

		wfProfileOut( __METHOD__ );
		return $pout;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's label.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForLabel( EntityContent $entity, Language $lang = null, $editable = true ) {
		wfProfileIn( __METHOD__ );

		if ( !$lang ) {
			$lang = $this->getLanguage();
		}

		$label = $entity->getEntity()->getLabel( $lang->getCode() );
		$editUrl = $this->getEditUrl( 'SetLabel', $entity->getEntity(), $lang );
		$prefixedId = $this->getFormattedIdForEntity( $entity->getEntity() );

		$html = wfTemplate( 'wb-label',
			$prefixedId,
			wfTemplate( 'wb-property',
				$label === false ? 'wb-value-empty' : '',
				htmlspecialchars( $label === false ? wfMessage( 'wikibase-label-empty' )->text() : $label ),
				$this->getHtmlForEditSection( $entity, $lang, $editUrl )
			)
		);

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's description.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForDescription( EntityContent $entity, Language $lang = null, $editable = true ) {
		wfProfileIn( __METHOD__ );

		if ( !$lang ) {
			$lang = $this->getLanguage();
		}

		$description = $entity->getEntity()->getDescription( $lang->getCode() );
		$editUrl = $this->getEditUrl( 'SetDescription', $entity->getEntity(), $lang );

		$html = wfTemplate( 'wb-description',
			wfTemplate( 'wb-property',
				$description === false ? 'wb-value-empty' : '',
				htmlspecialchars( $description === false ? wfMessage( 'wikibase-description-empty' )->text() : $description ),
				$this->getHtmlForEditSection( $entity, $lang, $editUrl )
			)
		);

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's aliases.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForAliases( EntityContent $entity, Language $lang = null, $editable = true ) {
		wfProfileIn( __METHOD__ );

		if ( !$lang ) {
			$lang = $this->getLanguage();
		}

		$aliases = $entity->getEntity()->getAliases( $lang->getCode() );
		$editUrl = $this->getEditUrl( 'SetAliases', $entity->getEntity(), $lang );

		if ( empty( $aliases ) ) {
			$html = wfTemplate( 'wb-aliases-wrapper',
				'wb-aliases-empty',
				'wb-value-empty',
				wfMessage( 'wikibase-aliases-empty' )->text(),
				$this->getHtmlForEditSection( $entity, $lang, $editUrl, 'span', 'add' )
			);
		} else {
			$aliasesHtml = '';
			foreach( $aliases as $alias ) {
				$aliasesHtml .= wfTemplate( 'wb-alias', htmlspecialchars( $alias ) );
			}
			$aliasList = wfTemplate( 'wb-aliases', $aliasesHtml );

			$html = wfTemplate( 'wb-aliases-wrapper',
				'',
				'',
				wfMessage( 'wikibase-aliases-label' )->text(),
				$aliasList . $this->getHtmlForEditSection( $entity, $lang, $editUrl )
			);
		}

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Selects the languages for the terms to display on first try, based on the current user and
	 * the available languages.
	 *
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param \Language|null $lang
	 * @param \User $user
	 * @return string[] selected langcodes
	 */
	private function selectTerms( Entity $entity, \Language $lang = null, \User $user = null ) {
		wfProfileIn( __METHOD__ );
		$result = array();

		// if the Babel extension is installed, add all languages of the user
		if ( class_exists( 'Babel' ) && ( ! $user->isAnon() ) ) {
			$result = \Babel::getUserLanguages( $user );
			if( $lang !== null ) {
				$result = array_diff( $result, array( $lang->getCode() ) );
			}
		}
		wfProfileOut( __METHOD__ );
		return $result;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's collection of terms.
	 *
	 * @since 0.4
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForLanguageTerms( EntityContent $entity, \Language $lang = null, $editable = true ) {

		if ( $lang === null ) {
			$lang = $this->getLanguage();
		}

		$languages = $this->selectTerms( $entity->getEntity(), $lang, $this->getUser() );
		if ( count ( $languages ) === 0 ) {
			return '';
		}

		wfProfileIn( __METHOD__ );

		$html = $thead = $tbody = '';

		$labels = $entity->getEntity()->getLabels();
		$descriptions = $entity->getEntity()->getDescriptions();

		$html .= wfTemplate( 'wb-terms-heading', wfMessage( 'wikibase-terms' ) );

		$languages = $this->selectTerms( $entity->getEntity(), $lang, $this->getUser() );

		$specialLabelPage = \SpecialPageFactory::getPage( "SetLabel" );
		$specialDescriptionPage = \SpecialPageFactory::getPage( "SetDescription" );
		$rowNumber = 0;
		foreach( $languages as $language ) {

			$label = array_key_exists( $language, $labels ) ? $labels[$language] : false;
			$description = array_key_exists( $language, $descriptions ) ? $descriptions[$language] : false;

			$alternatingClass = ( $rowNumber++ % 2 ) ? 'even' : 'uneven';

			$editLabelLink = $specialLabelPage->getTitle()->getLocalURL()
				. '/' . $this->getFormattedIdForEntity( $entity->getEntity() ) . '/' . $language;

			// TODO: this if is here just until the SetDescription special page exists and
			// can be removed then
			if ( $specialDescriptionPage !== null ) {
				$editDescriptionLink = $specialDescriptionPage->getTitle()->getLocalURL()
					. '/' . $this->getFormattedIdForEntity( $entity->getEntity() ) . '/' . $language;
			} else {
				$editDescriptionLink = '';
			}

			$tbody .= wfTemplate( 'wb-term',
				$language,
				$alternatingClass,
				htmlspecialchars( Utils::fetchLanguageName( $language ) ),
				htmlspecialchars( $label !== false ? $label : wfMessage( 'wikibase-label-empty' ) ),
				htmlspecialchars( $description !== false ? $description : wfMessage( 'wikibase-description-empty' ) ),
				$this->getHtmlForEditSection( $entity, $lang, $editLabelLink ),
				$this->getHtmlForEditSection( $entity, $lang, $editDescriptionLink ),
				$label !== false ? '' : 'wb-value-empty',
				$description !== false ? '' : 'wb-value-empty',
				$this->getTitle()->getLocalURL() . '?setlang=' . $language
			);
		}

		$html = $html . wfTemplate( 'wb-terms-table', $tbody );

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's claims.
	 *
	 * @since 0.2
	 *
	 * @param EntityContent $entity the entity to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local
	 *        context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForClaims( EntityContent $entity, Language $lang = null, $editable = true ) {
		global $wgLang;

		wfProfileIn( __METHOD__ );

		$languageCode = isset( $lang ) ? $lang->getCode() : $wgLang->getCode();

		$claims = $entity->getEntity()->getClaims();
		$html = '';

		$html .= wfTemplate(
			'wb-section-heading',
			wfMessage( 'wikibase-statements' ),
			'claims' // ID - TODO: should not be added if output page is not the entity's page
		);

		// aggregate claims by properties
		$claimsByProperty = array();
		foreach( $claims as $claim ) {
			$propertyId = $claim->getMainSnak()->getPropertyId();
			$claimsByProperty[$propertyId->getNumericId()][] = $claim;
		}

		/**
		 * @var string $claimsHtml
		 */
		$claimsHtml = '';
		foreach( $claimsByProperty as $claims ) {
			$propertyHtml = '';

			$propertyId = $claims[0]->getMainSnak()->getPropertyId();
			$property = $this->entityLookup->getEntity( $propertyId );
			$propertyLink = '';
			if ( $property ) {
				$propertyLink = \Linker::link(
					$this->entityTitleLookup->getTitleForId( $property->getId() ),
					htmlspecialchars( $property->getLabel( $languageCode ) )
				);
			}

			$i = 0;
			foreach( $claims as $claim ) {
				$propertyHtml .= $this->getHtmlForClaim( $entity, $claim, $lang, $editable );
			}

			$propertyHtml .= wfTemplate( 'wikibase-toolbar',
				'wb-addtoolbar',
				// TODO: add link to SpecialPage
				$this->getHtmlForEditSection( $entity, $lang, '', 'span', 'add' )
			);

			$claimsHtml .= wfTemplate( 'wb-claim-section',
				$propertyId,
				$propertyLink,
				$propertyHtml
			);

		}

		// TODO: Add link to SpecialPage that allows adding a new claim.
		$html = $html . wfTemplate( 'wb-claimlist', $claimsHtml );

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Builds and returns the HTML representing a single WikibaseEntity's claim.
	 *
	 * @since 0.4
	 *
	 * @param EntityContent $entity the entity related to the claim
	 * @param Claim $claim the claim to render
	 * @param Language|null $lang the language to use for rendering. if not given, the local
	 *        context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 *
	 * @throws MWException If a claim's value can't be displayed because the related value formatter
	 *         is not yet implemented or provided in the constructor. (Also see related todo)
	 */
	protected function getHtmlForClaim(
		EntityContent $entity,
		Claim $claim,
		Language $lang = null,
		$editable = true
	) {
		global $wgLang;

		wfProfileIn( __METHOD__ );

		$languageCode = isset( $lang ) ? $lang->getCode() : $wgLang->getCode();

		$valueFormatterOptions = new FormatterOptions( array(
			ValueFormatter::OPT_LANG => $languageCode,
			TimeFormatter::OPT_TIME_ISO_FORMATTER => new MwTimeIsoFormatter(
				new FormatterOptions( array( ValueFormatter::OPT_LANG => $languageCode ) )
			),
		) );

		// TODO: display a "placeholder" message for novalue/somevalue snak
		$value = '';
		if ( $claim->getMainSnak()->getType() === 'value' ) {
			$value = $claim->getMainSnak()->getDataValue();

			$valueFormatter = $this->valueFormatters->newFormatter(
				$value->getType(), $valueFormatterOptions
			);

			if ( $valueFormatter !== null ) {
				$value = $valueFormatter->format( $value );
			} else {
				// If value representation is a string, just display that one as a
				// fallback for values not having a formatter implemented yet.
				if ( is_string( $value->getValue() ) ) {
					$value = $value->getValue();
				} elseif ( $value instanceof \DataValues\UnDeserializableValue ) {
					$value = $value->getReason();
				} else {
					// TODO: don't fail here, display a message in the UI instead
					throw new MWException( 'Displaying of values of type "'
						. $value->getType() . '" not supported yet' );
				}
			}
		}

		$mainSnakHtml = wfTemplate( 'wb-snak',
			'wb-mainsnak',
			'', // Link to property. NOTE: we don't display this ever (instead, we generate it on
				// Claim group level) If this was a public function, this should be generated
				// anyhow since important when displaying a Claim on its own.
			'', // type selector, JS only
			( $value === '' ) ? '&nbsp;' : htmlspecialchars( $value )
		);

		// TODO: Use 'wb-claim' or 'wb-statement' template accordingly
		$claimHtml = wfTemplate( 'wb-statement',
			'', // additional classes
			$claim->getGuid(),
			$mainSnakHtml,
			'', // TODO: Qualifiers
			$this->getHtmlForEditSection( $entity, $lang, '', 'span' ), // TODO: add link to SpecialPage
			'', // TODO: References heading
			'' // TODO: References
		);

		wfProfileOut( __METHOD__ );
		return $claimHtml;
	}

	/**
	 * Returns a toolbar with an edit link for a single statement. Equivalent to edit toolbar in JavaScript but with
	 * an edit link pointing to a special page where the statement can be edited. In case JavaScript is available, this
	 * toolbar will be removed an replaced with the interactive JavaScript one.
	 *
	 * @since 0.2
	 *
	 * @param EntityContent $entity
	 * @param \Language|null $lang
	 * @param string $url specifies the URL for the button, default is an empty string
	 * @param string $tag allows to specify the type of the outer node
	 * @param string $action by default 'edit', for aliases this could also be 'add'
	 * @param bool $enabled can be set to false to display the button disabled
	 * @return string
	 */
	public function getHtmlForEditSection(
		EntityContent $entity, Language $lang = null, $url = '', $tag = 'span', $action = 'edit', $enabled = true
	) {
		wfProfileIn( __METHOD__ );

		$buttonLabel = wfMessage( $action === 'add' ? 'wikibase-add' : 'wikibase-edit' )->text();

		$button = ( $enabled ) ?
			wfTemplate( 'wikibase-toolbarbutton',
				$buttonLabel,
				$url // todo: add link to special page for non-JS editing
			) :
			wfTemplate( 'wikibase-toolbarbutton-disabled',
				$buttonLabel
			);

		$html = wfTemplate( 'wb-editsection',
			$tag,
			wfTemplate( 'wikibase-toolbar',
				'',
				wfTemplate( 'wikibase-toolbareditgroup',
					'',
					wfTemplate( 'wikibase-toolbar', '', $button )
				)
			)
		);

		wfProfileOut( __METHOD__ );
		return $html;
	}

	/**
	 * Returns the url of the editlink.
	 *
	 * @since    0.4
	 *
	 * @param string  $specialpagename
	 * @param Entity  $entity
	 * @param \Language|null $lang
	 *
	 * @return string
	 */
	protected function getEditUrl( $specialpagename, Entity $entity, Language $lang = null ) {
		$specialpage = \SpecialPageFactory::getPage( $specialpagename );

		if ( $specialpage === null ) {
			return ''; //XXX: this should throw an exception?!
		}

		if ( $entity->getId() ) {
			$id = $this->getFormattedIdForEntity( $entity );
		} else {
			$id = ''; // can't skip this, that would confuse the order of parameters!
		}

		return $specialpage->getTitle()->getLocalURL()
				. '/' . wfUrlencode( $id )
				. ( $lang === null ? '' : '/' . wfUrlencode( $lang->getCode() ) );
	}

	/**
	 * Outputs the given entity to the OutputPage.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent       $entity the entity to output
	 * @param null|\OutputPage    $out the output page to write to. If not given, the local context will be used.
	 * @param null|\ParserOptions $options parser options to use for rendering. If not given, the local context will be used.
	 * @param null|\ParserOutput  $pout optional parser object - provide this if you already have a parser options for
	 *                            this entity, to avoid redundant rendering.
	 * @return \ParserOutput the parser output, for further processing.
	 *
	 * @todo: fixme: currently, only one entity can be shown per page, because the entity's id is in a global JS config variable.
	 */
	public function render( EntityContent $entity, OutputPage $out = null, ParserOptions $options = null, ParserOutput $pout = null ) {
		wfProfileIn( __METHOD__ );

		$isPoutSet = $pout !== null;

		if ( !$out ) {
			$out = $this->getOutput();
		}

		if ( !$pout ) {
			if ( !$options ) {
				$options = $this->makeParserOptions();
			}

			$pout = $this->getParserOutput( $entity, $options, true );
		}

		$langCode = null;
		if ( $options ) {
			//XXX: This is deprecated, and in addition it will quite often fail so we need a fallback.
			$langCode = $options->getTargetLanguage();
		}
		if ( !$isPoutSet && is_null( $langCode ) ) {
			//XXX: This is quite ugly, we don't know that this language is the language that was used to generate the parser output object.
			$langCode = $this->getLanguage()->getCode();
		}

		// overwrite page title
		$out->setPageTitle( $pout->getTitleText() );

		// register JS stuff
		$editableView = $options->getEditSection(); //XXX: apparently, EditSections isn't included in the parser cache key?!
		$this->registerJsConfigVars( $out, $entity, $langCode, $editableView ); //XXX: $editableView should *not* reflect user permissions

		$out->addParserOutput( $pout );
		wfProfileOut( __METHOD__ );
		return $pout;
	}

	/**
	 * Helper function for registering any JavaScript stuff needed to show the entity.
	 * @todo Would be much nicer if we could do that via the ResourceLoader Module or via some hook.
	 *
	 * @since 0.1
	 *
	 * @param OutputPage    $out the OutputPage to add to
	 * @param EntityContent  $entityContent the entity for which we want to add the JS config
	 * @param string         $langCode the language used for showing the entity.
	 * @param bool           $editableView whether entities on this page should be editable.
	 *                       This is independent of user permissions.
	 *
	 * @todo: fixme: currently, only one entity can be shown per page, because the entity's id is in a global JS config variable.
	 */
	public function registerJsConfigVars( OutputPage $out, EntityContent $entityContent, $langCode, $editableView = false  ) {
		wfProfileIn( __METHOD__ );

		$parser = new \Parser();
		$user = $this->getUser();
		$entity = $entityContent->getEntity();
		$title = $out->getTitle();

		//TODO: replace wbUserIsBlocked this with more useful info (which groups would be required to edit? compare wgRestrictionEdit and wgRestrictionCreate)
		$out->addJsConfigVars( 'wbUserIsBlocked', $user->isBlockedFrom( $entityContent->getTitle() ) ); //NOTE: deprecated

		// tell JS whether the user can edit
		$out->addJsConfigVars( 'wbUserCanEdit', $entityContent->userCanEdit( $user, false ) ); //TODO: make this a per-entity info
		$out->addJsConfigVars( 'wbIsEditView', $editableView );  //NOTE: page-wide property, independent of user permissions

		$out->addJsConfigVars( 'wbEntityType', $entity->getType() );
		$out->addJsConfigVars( 'wbDataLangName', Utils::fetchLanguageName( $langCode ) );

		// entity specific data
		$out->addJsConfigVars( 'wbEntityId', $this->getFormattedIdForEntity( $entity ) );

		// copyright warning message
		$out->addJsConfigVars( 'wbCopyright', array(
			'version' => Utils::getCopyrightMessageVersion(),
			'messageHtml' => Utils::getCopyrightMessage()->parse(),
		) );

		// TODO: use injected id formatter
		$serializationOptions = new EntitySerializationOptions( WikibaseRepo::getDefaultInstance()->getIdFormatter() );

		$serializerFactory = new SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $entity, $serializationOptions );

		$out->addJsConfigVars(
			'wbEntity',
			FormatJson::encode( $serializer->getSerialized( $entity ) )
		);

		// make information about other entities used in this entity available in JavaScript view:
		$refFinder = new ReferencedEntitiesFinder();

		$usedEntityIds = $refFinder->findSnakLinks( $entity->getAllSnaks() );
		$basicEntityInfo = $this->getBasicEntityInfo( $usedEntityIds, $langCode );

		$out->addJsConfigVars(
			'wbUsedEntities',
			FormatJson::encode( $basicEntityInfo )
		);

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Fetches some basic entity information required for the entity view in JavaScript from a
	 * set of entity IDs.
	 * @since 0.4
	 *
	 * @param EntityId[] $entityIds
	 * @param string $langCode For the entity labels which will be included in one language only.
	 * @return array
	 */
	protected function getBasicEntityInfo( array $entityIds, $langCode ) {
		wfProfileIn( __METHOD__ );

		$entities = $this->entityLookup->getEntities( $entityIds );
		$entityInfo = array();

		$serializerFactory = new SerializerFactory();
		$serializationOptions = new EntitySerializationOptions( $this->idFormatter );
		$serializationOptions->setProps( array( 'labels', 'descriptions', 'datatype' ) );

		$serializationOptions->setLanguages( array( $langCode ) );

		/* @var Entity $entity */
		foreach( $entities as $prefixedId => $entity ) {
			if( $entity === null ) {
				continue;
			}
			$serializer = $serializerFactory->newSerializerForObject( $entity, $serializationOptions );
			$entityInfo[ $prefixedId ] = $serializer->getSerialized( $entity );

			$title = $this->entityTitleLookup->getTitleForId( $entity->getId() );

			// TODO: should perhaps implement and use a EntityContentSerializer since this is mixed,
			//  serialized Entity and EntityContent data because of adding the URL:
			$entityInfo[ $prefixedId ]['title'] = $title->getPrefixedText();
			$entityInfo[ $prefixedId ]['lastrevid'] = $title->getLatestRevID();
		}

		wfProfileOut( __METHOD__ );
		return $entityInfo;
	}

	/**
	 * Returns a new view which is suited to render different variations of EntityContent.
	 *
	 * @since 0.2
	 *
	 * @param EntityContent              $entity
	 * @param ValueFormatterFactory      $valueFormatters
	 * @param Lib\PropertyDataTypeLookup $dataTypeLookup
	 * @param EntityLookup               $entityLookup
	 * @param IContextSource|null        $context
	 *
	 * @throws \MWException
	 * @return EntityView
	 */
	public static function newForEntityContent(
		EntityContent $entity,
		ValueFormatterFactory $valueFormatters,
		PropertyDataTypeLookup $dataTypeLookup,
		EntityLookup $entityLookup,
		IContextSource $context = null
	) {
		$type = $entity->getEntity()->getType();

		if ( !in_array( $type, array_keys( self::$typeMap ) ) ) {
			throw new MWException( "No entity view known for handling entities of type '$type'" );
		}

		if ( !$context ) {
			$context = \RequestContext::getMain();
		}

		$idFormatter = WikibaseRepo::getDefaultInstance()->getIdFormatter();
		$entityTitleLookup = EntityContentFactory::singleton();

		$instance = new self::$typeMap[ $type ]( $context, $valueFormatters, $dataTypeLookup, $entityLookup, $entityTitleLookup, $idFormatter );
		return $instance;
	}
}
