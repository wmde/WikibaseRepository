<?php

namespace Wikibase\Repo;

use DataTypes\DataTypeFactory;
use DataValues\DataValueFactory;
use ValueFormatters\FormatterOptions;
use ValueParsers\ParserOptions;
use Wikibase\DataModel\Claim\ClaimGuidParser;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\EntityContentFactory;
use Wikibase\EntityLookup;
use Wikibase\EntityRevisionLookup;
use Wikibase\EntityTitleLookup;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Lib\EntityIdLinkFormatter;
use Wikibase\Lib\EntityIdParser;
use Wikibase\Lib\EntityRetrievingDataTypeLookup;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\PropertyDataTypeLookup;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\Lib\SnakConstructionService;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\WikibaseDataTypeBuilders;
use Wikibase\Lib\ClaimGuidValidator;
use Wikibase\Lib\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\WikibaseValueFormatterBuilders;
use Wikibase\Settings;
use Wikibase\SettingsArray;
use Wikibase\Store;
use Wikibase\StoreFactory;
use Wikibase\SnakFactory;
use Wikibase\StringNormalizer;
use Wikibase\SummaryFormatter;

/**
 * Top level factory for the WikibaseRepo extension.
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class WikibaseRepo {

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @var DataTypeFactory|null
	 */
	private $dataTypeFactory = null;

	/**
	 * @var EntityIdFormatter|null
	 */
	private $idFormatter = null;

	/**
	 * @var SnakConstructionService|null
	 */
	private $snakConstructionService = null;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var ClaimGuidValidator
	 */
	private $claimGuidValidator = null;

	/**
	 * @var StringNormalizer
	 */
	private $stringNormalizer;

	/**
	 * @var OutputFormatSnakFormatterFactory
	 */
	private $snakFormatterFactory;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var OutputFormatValueFormatterFactory
	 */
	private $valueFormatterFactory;

	/**
	 * @var SummaryFormatter
	 */
	private $summaryFormatter;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * Returns the default instance constructed using newInstance().
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @since 0.4
	 *
	 * @return WikibaseRepo
	 */
	public static function getDefaultInstance() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = new self( Settings::singleton() );
		}

		return $instance;
	}

	/**
	 * @since 0.4
	 *
	 * @param SettingsArray $settings
	 */
	public function __construct( SettingsArray $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @since 0.4
	 *
	 * @return DataTypeFactory
	 */
	public function getDataTypeFactory() {
		if ( $this->dataTypeFactory === null ) {

			$urlSchemes = $this->getSettings()->getSetting( 'urlSchemes' );
			$builders = new WikibaseDataTypeBuilders( $this->getEntityLookup(), $this->getEntityIdParser(), $urlSchemes );

			$typeBuilderSpecs = array_intersect_key(
				$builders->getDataTypeBuilders(),
				array_flip( $this->settings->getSetting( 'dataTypes' ) )
			);

			$this->dataTypeFactory = new DataTypeFactory( $typeBuilderSpecs );
		}

		return $this->dataTypeFactory;
	}

	/**
	 * @since 0.4
	 *
	 * @return DataValueFactory
	 */
	public function getDataValueFactory() {
		return DataValueFactory::singleton();
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityContentFactory
	 */
	public function getEntityContentFactory() {
		$entityNamespaces = $this->settings->getSetting( 'entityNamespaces' );

		return new EntityContentFactory(
			$this->getIdFormatter(),
			is_array( $entityNamespaces ) ? array_keys( $entityNamespaces ) : array()
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityTitleLookup
	 */
	public function getEntityTitleLookup() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() {
		if ( $this->entityRevisionLookup === null ) {
			$this->entityRevisionLookup = StoreFactory::getStore()->getEntityRevisionLookup();
		}

		return $this->entityRevisionLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdFormatter
	 */
	public function getIdFormatter() {
		if ( $this->idFormatter === null ) {
			$this->idFormatter = new EntityIdFormatter( new FormatterOptions() );
		}

		return $this->idFormatter;
	}

	/**
	 * @since 0.4
	 *
	 * @return PropertyDataTypeLookup
	 */
	public function getPropertyDataTypeLookup() {
		if ( $this->propertyDataTypeLookup === null ) {
			$infoStore = $this->getStore()->getPropertyInfoStore();
			$retrievingLookup = new EntityRetrievingDataTypeLookup( $this->getEntityLookup() );
			$this->propertyDataTypeLookup = new PropertyInfoDataTypeLookup( $infoStore, $retrievingLookup );
		}

		return $this->propertyDataTypeLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return StringNormalizer
	 */
	public function getStringNormalizer() {
		if ( $this->stringNormalizer === null ) {
			$this->stringNormalizer = new StringNormalizer();
		}

		return $this->stringNormalizer;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup() {
		if ( $this->entityLookup === null ) {
			$this->entityLookup = StoreFactory::getStore()->getEntityLookup();
		}

		return $this->entityLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return SnakConstructionService
	 */
	public function getSnakConstructionService() {
		if ( $this->snakConstructionService === null ) {
			$snakFactory = new SnakFactory();
			$dataTypeLookup = $this->getPropertyDataTypeLookup();
			$dataTypeFactory = $this->getDataTypeFactory();
			$dataValueFactory = $this->getDataValueFactory();

			$this->snakConstructionService = new SnakConstructionService(
				$snakFactory,
				$dataTypeLookup,
				$dataTypeFactory,
				$dataValueFactory );
		}

		return $this->snakConstructionService;
	}

	/**
	 * Returns the base to use when generating URIs for use in RDF output.
	 *
	 * @return string
	 */
	public function getRdfBaseURI() {
		global $wgServer; //TODO: make this configurable

		$uri = $wgServer;
		$uri = preg_replace( '!^//!', 'http://', $uri );
		$uri = $uri . '/entity/';
		return $uri;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdParser
	 */
	public function getEntityIdParser() {
		$options = new ParserOptions();
		return new EntityIdParser( $options );
	}

	/**
	 * @since 0.5
	 *
	 * @return ClaimGuidParser
	 */
	public function getClaimGuidParser() {
		$idBuilders = BasicEntityIdParser::getBuilders();

		// TODO: extensions need to be able to add builders.

		$parser = new DispatchingEntityIdParser( $idBuilders );

		return new ClaimGuidParser( $parser );
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdFormatter
	 */
	public function getEntityIdFormatter() {
		return $this->getIdFormatter();
	}

	/**
	 * @since 0.4
	 *
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory() {
		if ( $this->languageFallbackChainFactory === null ) {
			global $wgUseSquid;
			// The argument is about whether full page output (OutputPage, specifically JS vars in it currently)
			// is cached for anons, where the only caching mechanism in use now is Squid.
			$this->languageFallbackChainFactory = new LanguageFallbackChainFactory(
				/* $anonymousPageViewCached = */ $wgUseSquid
			);
		}

		return $this->languageFallbackChainFactory;
	}

	/**
	 * @since 0.4
	 *
	 * @return ClaimGuidValidator
	 */
	public function getClaimGuidValidator() {
		if ( $this->claimGuidValidator === null ) {
			$this->claimGuidValidator = new ClaimGuidValidator();
		}

		return $this->claimGuidValidator;
	}

	/**
	 * @since 0.4
	 *
	 * @return SettingsArray
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * @since 0.4
	 *
	 * @return Store
	 */
	public function getStore() {
		//TODO: inject this, get rid of global store instance(s)
		return StoreFactory::getStore();
	}

	/**
	 * Returns a OutputFormatSnakFormatterFactory the provides SnakFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatSnakFormatterFactory
	 */
	public function getSnakFormatterFactory() {
		if ( !$this->snakFormatterFactory ) {
			$this->snakFormatterFactory = $this->newSnakFormatterFactory();
		}

		return $this->snakFormatterFactory;
	}

	/**
	 * @return OutputFormatSnakFormatterFactory
	 */
	protected function newSnakFormatterFactory() {
		global $wgContLang;

		$valueFormatterBuilders = new WikibaseValueFormatterBuilders(
			$this->getEntityLookup(),
			$wgContLang
		);

		$builders = new WikibaseSnakFormatterBuilders(
			$valueFormatterBuilders,
			$this->getPropertyDataTypeLookup()
		);

		$factory = new OutputFormatSnakFormatterFactory( $builders->getSnakFormatterBuildersForFormats() );
		return $factory;
	}

	/**
	 * Returns a OutputFormatValueFormatterFactory the provides ValueFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatValueFormatterFactory
	 */
	public function getValueFormatterFactory() {
		if ( !$this->valueFormatterFactory ) {
			$this->valueFormatterFactory = $this->newValueFormatterFactory();
		}

		return $this->valueFormatterFactory;
	}

	/**
	 * @return OutputFormatValueFormatterFactory
	 */
	protected function newValueFormatterFactory() {
		global $wgContLang;

		$builders = new WikibaseValueFormatterBuilders(
			$this->getEntityLookup(),
			$wgContLang
		);

		$factory = new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
		return $factory;
	}

	/**
	 * Returns a SummaryFormatter.
	 *
	 * @return SummaryFormatter
	 */
	public function getSummaryFormatter() {
		if ( !$this->summaryFormatter ) {
			$this->summaryFormatter = $this->newSummaryFormatter();
		}

		return $this->summaryFormatter;
	}

	/**
	 * @return SummaryFormatter
	 */
	protected function newSummaryFormatter() {
		global $wgContLang;

		$options = new FormatterOptions();
		$idFormatter = new EntityIdLinkFormatter( $options, $this->getEntityContentFactory() );

		$valueFormatterBuilders = new WikibaseValueFormatterBuilders(
			$this->getEntityLookup(),
			$wgContLang
		);

		$snakFormatterBuilders = new WikibaseSnakFormatterBuilders(
			$valueFormatterBuilders,
			$this->getPropertyDataTypeLookup()
		);

		$valueFormatterBuilders->setValueFormatter( SnakFormatter::FORMAT_PLAIN, 'VT:wikibase-entityid', $idFormatter );

		$snakFormatterFactory = new OutputFormatSnakFormatterFactory( $snakFormatterBuilders->getSnakFormatterBuildersForFormats() );
		$valueFormatterFactory = new OutputFormatValueFormatterFactory( $valueFormatterBuilders->getValueFormatterBuildersForFormats() );

		$snakFormatter = $snakFormatterFactory->getSnakFormatter( SnakFormatter::FORMAT_PLAIN, $options );
		$valueFormatter = $valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_PLAIN, $options );

		$formatter = new SummaryFormatter(
			$idFormatter,
			$valueFormatter,
			$snakFormatter,
			$wgContLang
		);

		return $formatter;
	}

}
