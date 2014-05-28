<?php

namespace Wikibase\Repo;

use DataTypes\DataTypeFactory;
use DataValues\DataValueFactory;
use SiteSQLStore;
use SiteStore;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\ChangeOp\ChangeOpFactoryProvider;
use Wikibase\DataModel\Claim\ClaimGuidParser;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\EntityContentFactory;
use Wikibase\EntityLookup;
use Wikibase\LabelDescriptionDuplicateDetector;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Lib\ClaimGuidValidator;
use Wikibase\Lib\DispatchingValueFormatter;
use Wikibase\Lib\EntityIdLinkFormatter;
use Wikibase\Lib\EntityRetrievingDataTypeLookup;
use Wikibase\Lib\Localizer\ExceptionLocalizer;
use Wikibase\Lib\Localizer\MessageParameterFormatter;
use Wikibase\Lib\Localizer\WikibaseExceptionLocalizer;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\PropertyDataTypeLookup;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\Lib\SnakConstructionService;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\WikibaseDataTypeBuilders;
use Wikibase\Lib\WikibaseSnakFormatterBuilders;
use Wikibase\Lib\WikibaseValueFormatterBuilders;
use Wikibase\ParserOutputJsConfigBuilder;
use Wikibase\ReferencedEntitiesFinder;
use Wikibase\Settings;
use Wikibase\SettingsArray;
use Wikibase\SnakFactory;
use Wikibase\StoreFactory;
use Wikibase\StringNormalizer;
use Wikibase\SummaryFormatter;
use Wikibase\Utils;
use Wikibase\Validators\EntityConstraintProvider;
use Wikibase\Validators\SnakValidator;
use Wikibase\Validators\TermValidatorFactory;
use Wikibase\Validators\ValidatorErrorLocalizer;

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
	 * @var EntityIdParser
	 */
	private $entityIdParser = null;

	/**
	 * @var StringNormalizer
	 */
	private $stringNormalizer;

	/**
	 * @var OutputFormatSnakFormatterFactory
	 */
	private $snakFormatterFactory;

	/**
	 * @var OutputFormatValueFormatterFactory
	 */
	private $valueFormatterFactory;

	/**
	 * @var SummaryFormatter
	 */
	private $summaryFormatter;

	/**
	 * @var ExceptionLocalizer
	 */
	private $exceptionLocalizer;

	/**
	 * @var SiteStore
	 */
	private $siteStore;

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
			$builders = new WikibaseDataTypeBuilders(
				$this->getEntityLookup(),
				$this->getEntityIdParser(),
				$urlSchemes
			);

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
		return new EntityContentFactory( $this->getContentModelMappings() );
	}

	/**
	 * @since 0.5
	 *
	 * @return \Wikibase\store\EntityStoreWatcher
	 */
	public function getEntityStoreWatcher() {
		return $this->getStore()->getEntityStoreWatcher();
	}

	/**
	 * @since 0.5
	 *
	 * @return \Wikibase\EntityTitleLookup
	 */
	public function getEntityTitleLookup() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @since 0.5
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return \Wikibase\EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $uncached = '' ) {
		return $this->getStore()->getEntityRevisionLookup( $uncached );
	}

	/**
	 * @since 0.5
	 *
	 * @return \Wikibase\store\EntityStore
	 */
	public function getEntityStore() {
		return $this->getStore()->getEntityStore();
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
			$this->propertyDataTypeLookup = new PropertyInfoDataTypeLookup(
				$infoStore,
				$retrievingLookup
			);
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
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $uncached = '' ) {
		return $this->getStore()->getEntityLookup( $uncached );
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
		if ( $this->entityIdParser === null ) {
			//TODO: make the ID builders configurable
			$this->entityIdParser = new DispatchingEntityIdParser( BasicEntityIdParser::getBuilders() );
		}

		return $this->entityIdParser;
	}

	/**
	 * @since 0.5
	 *
	 * @return ClaimGuidParser
	 */
	public function getClaimGuidParser() {
		return new ClaimGuidParser( $this->getEntityIdParser() );
	}

	/**
	 * @since 0.5
	 *
	 * @return ChangeOpFactoryProvider
	 */
	public function getChangeOpFactoryProvider() {
		return new ChangeOpFactoryProvider(
			$this->getEntityConstraintProvider(),
			new ClaimGuidGenerator(),
			$this->getClaimGuidValidator(),
			$this->getClaimGuidParser(),
			$this->getSnakValidator(),
			$this->getTermValidatorFactory()
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return SnakValidator
	 */
	public function getSnakValidator() {
		return new SnakValidator(
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);
	}

	/**
	 * @since 0.4
	 *
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory() {
		if ( $this->languageFallbackChainFactory === null ) {
			global $wgUseSquid;

			// The argument is about whether full page output (OutputPage, specifically JS vars in
			// it currently) is cached for anons, where the only caching mechanism in use now is
			// Squid.
			$anonymousPageViewCached = $wgUseSquid;

			$this->languageFallbackChainFactory = new LanguageFallbackChainFactory(
				defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES,
				$anonymousPageViewCached
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
			$this->claimGuidValidator = new ClaimGuidValidator( $this->getEntityIdParser() );
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
	 * @return \Wikibase\Store
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
	 * @return WikibaseValueFormatterBuilders
	 */
	public function getValueFormatterBuilders() {
		global $wgContLang;

		return new WikibaseValueFormatterBuilders(
			$this->getEntityLookup(),
			$wgContLang,
			$this->getEntityTitleLookup()
		);
	}

	/**
	 * @return OutputFormatSnakFormatterFactory
	 */
	protected function newSnakFormatterFactory() {
		$builders = new WikibaseSnakFormatterBuilders(
			$this->getValueFormatterBuilders(),
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
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
		$builders = $this->getValueFormatterBuilders();

		$factory = new OutputFormatValueFormatterFactory( $builders->getValueFormatterBuildersForFormats() );
		return $factory;
	}

	/**
	 * @return ExceptionLocalizer
	 */
	public function getExceptionLocalizer() {
		if ( !$this->exceptionLocalizer ) {
			$this->exceptionLocalizer = new WikibaseExceptionLocalizer(
				$this->getMessageParameterFormatter()
			);
		}

		return $this->exceptionLocalizer;
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

		$valueFormatterBuilders = $this->getValueFormatterBuilders();

		$snakFormatterBuilders = new WikibaseSnakFormatterBuilders(
			$valueFormatterBuilders,
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);

		$valueFormatterBuilders->setValueFormatter(
			SnakFormatter::FORMAT_PLAIN,
			'VT:wikibase-entityid',
			$idFormatter
		);

		$snakFormatterFactory = new OutputFormatSnakFormatterFactory(
			$snakFormatterBuilders->getSnakFormatterBuildersForFormats()
		);
		$valueFormatterFactory = new OutputFormatValueFormatterFactory(
			$valueFormatterBuilders->getValueFormatterBuildersForFormats()
		);

		$snakFormatter = $snakFormatterFactory->getSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$options
		);
		$valueFormatter = $valueFormatterFactory->getValueFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$options
		);

		$formatter = new SummaryFormatter(
			$idFormatter,
			$valueFormatter,
			$snakFormatter,
			$wgContLang
		);

		return $formatter;
	}

	public function getParserOutputJsConfigBuilder( $langCode ) {
		return new ParserOutputJsConfigBuilder(
			$this->getStore()->getEntityInfoBuilder(),
			$this->getEntityIdParser(),
			$this->getEntityContentFactory(),
			new ReferencedEntitiesFinder(),
			$langCode
		);
	}

	/**
	 * @return \Wikibase\EntityPermissionChecker
	 */
	public function getEntityPermissionChecker() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @return TermValidatorFactory
	 */
	protected function getTermValidatorFactory() {
		$constraints = $this->getSettings()->getSetting( 'multilang-limits' );
		$maxLength = $constraints['length'];

		$languages = Utils::getLanguageCodes();

		return new TermValidatorFactory(
			$maxLength,
			$languages,
			$this->getEntityIdParser(),
			$this->getLabelDescriptionDuplicateDetector(),
			$this->getStore()->newSiteLinkCache()
		);
	}

	/**
	 * @return EntityConstraintProvider
	 */
	public function getEntityConstraintProvider() {
		return new EntityConstraintProvider(
			$this->getLabelDescriptionDuplicateDetector(),
			$this->getStore()->newSiteLinkCache()
		);
	}

	/**
	 * @return ValidatorErrorLocalizer
	 */
	public function getValidatorErrorLocalizer() {
		return new ValidatorErrorLocalizer( $this->getMessageParameterFormatter() );
	}

	/**
	 * @return LabelDescriptionDuplicateDetector
	 */
	public function getLabelDescriptionDuplicateDetector() {
		return new LabelDescriptionDuplicateDetector( $this->getStore()->getTermIndex() );
	}

	/**
	 * @return SiteStore
	 */
	public function getSiteStore() {
		if ( !$this->siteStore ) {
			$this->siteStore = SiteSQLStore::newInstance();
		}

		return $this->siteStore;
	}

	/**
	 * Returns a ValueFormatter suitable for converting message parameters to wikitext.
	 * The formatter is most likely implemented to dispatch to different formatters internally,
	 * based on the type of the parameter.
	 *
	 * @return ValueFormatter
	 */
	protected function getMessageParameterFormatter() {
		global $wgLang;

		$formatterOptions = new FormatterOptions();
		$valueFormatterBuilders = $this->getValueFormatterBuilders();
		$valueFormatters = $valueFormatterBuilders->getWikiTextFormatters( $formatterOptions );

		return new MessageParameterFormatter(
			new DispatchingValueFormatter( $valueFormatters ),
			$this->getEntityTitleLookup(),
			$this->getSiteStore(),
			$wgLang
		);
	}

	/**
	 * Get the mapping of entity types => content models
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	public function getContentModelMappings() {
		// @TODO: We should have smth. like this for namespaces too
		$map = array(
			Item::ENTITY_TYPE => CONTENT_MODEL_WIKIBASE_ITEM,
			Property::ENTITY_TYPE => CONTENT_MODEL_WIKIBASE_PROPERTY
		);

		wfRunHooks( 'WikibaseContentModelMapping', array( &$map ) );

		return $map;
	}
}
