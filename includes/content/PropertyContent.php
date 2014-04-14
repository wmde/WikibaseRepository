<?php

namespace Wikibase;

use Content;
use DataUpdate;
use IContextSource;
use ParserOutput;
use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\PropertyDataTypeLookup;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Utils;
use WikiPage;

/**
 * Content object for articles representing Wikibase properties.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class PropertyContent extends EntityContent {

	/**
	 * @since 0.1
	 * @var Property
	 */
	protected $property;

	/**
	 * Do not use to construct new stuff from outside of this class,
	 * use the static newFoobar methods.
	 *
	 * In other words: treat as protected (which it was, but now
	 * cannot be since we derive from Content).
	 *
	 * @protected
	 *
	 * @since 0.1
	 *
	 * @param Property $property
	 */
	public function __construct( Property $property ) {
		parent::__construct( CONTENT_MODEL_WIKIBASE_PROPERTY );
		$this->property = $property;
	}

	/**
	 * Create a new propertyContent object for the provided property.
	 *
	 * @since 0.1
	 *
	 * @param Property $property
	 *
	 * @return PropertyContent
	 */
	public static function newFromProperty( Property $property ) {
		return new static( $property );
	}

	/**
	 * Create a new PropertyContent object from the provided Property data.
	 *
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return PropertyContent
	 */
	public static function newFromArray( array $data ) {
		return new static( new Property( $data ) );
	}

	/**
	 * Gets the property that makes up this property content.
	 *
	 * @since 0.1
	 *
	 * @return Property
	 */
	public function getProperty() {
		return $this->property;
	}

	/**
	 * Sets the property that makes up this property content.
	 *
	 * @since 0.1
	 *
	 * @param Property $property
	 */
	public function setProperty( Property $property ) {
		$this->property = $property;
	}

	/**
	 * Returns a new empty PropertyContent.
	 *
	 * @since 0.1
	 *
	 * @return PropertyContent
	 */
	public static function newEmpty() {
		return new static( Property::newEmpty() );
	}

	/**
	 * @see EntityContent::getEntity
	 *
	 * @since 0.1
	 *
	 * @return Property
	 */
	public function getEntity() {
		return $this->property;
	}

	/**
	 * @see Content::getDeletionUpdates
	 *
	 * @param \WikiPage $page
	 * @param null|\ParserOutput $parserOutput
	 *
	 * @since 0.1
	 *
	 * @return DataUpdate[]
	 */
	public function getDeletionUpdates( \WikiPage $page, \ParserOutput $parserOutput = null ) {
		//XXX: access to services should be done via the ContentHandler.
		$infoStore = WikibaseRepo::getDefaultInstance()->getStore()->getPropertyInfoStore();

		return array_merge(
			parent::getDeletionUpdates( $page, $parserOutput ),
			array(
				new EntityDeletionUpdate( $this ),
				new PropertyInfoDeletion( $this->getProperty()->getId(), $infoStore ),
			)
		);
	}

	/**
	 * @see ContentHandler::getSecondaryDataUpdates
	 *
	 * @since 0.1
	 *
	 * @param Title $title
	 * @param Content|null $old
	 * @param boolean $recursive
	 *
	 * @param null|ParserOutput $parserOutput
	 *
	 * @return DataUpdate[]
	 */
	public function getSecondaryDataUpdates( Title $title, Content $old = null,
		$recursive = false, ParserOutput $parserOutput = null ) {

		//XXX: access to services should be done via the ContentHandler.
		$infoStore = WikibaseRepo::getDefaultInstance()->getStore()->getPropertyInfoStore();

		return array_merge(
			parent::getSecondaryDataUpdates( $title, $old, $recursive, $parserOutput ),
			array(
				new EntityModificationUpdate( $this, $old ),
				new PropertyInfoUpdate( $this->getProperty(), $infoStore ),
			)
		);
	}


	/**
	 * Instantiates an EntityView.
	 *
	 * @see getEntityView()
	 *
	 * @param IContextSource $context
	 * @param SnakFormatter $snakFormatter
	 * @param PropertyDataTypeLookup $dataTypeLookup
	 * @param EntityInfoBuilder $entityInfoBuilder
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param EntityIdParser $idParser
	 * @param SerializationOptions $options
	 *
	 * @return EntityView
	 */
	protected function newEntityView(
		IContextSource $context,
		SnakFormatter $snakFormatter,
		PropertyDataTypeLookup $dataTypeLookup,
		EntityInfoBuilder $entityInfoBuilder,
		EntityTitleLookup $entityTitleLookup,
		EntityIdParser $idParser,
		SerializationOptions $options
	) {
		$configBuilder = new ParserOutputJsConfigBuilder(
			$entityInfoBuilder,
			$idParser,
			$entityTitleLookup,
			new ReferencedEntitiesFinder(),
			$context->getLanguage()->getCode()
		);

		return new PropertyView(
			$context,
			$snakFormatter,
			$dataTypeLookup,
			$entityInfoBuilder,
			$entityTitleLookup,
			$options,
			$configBuilder
		);
	}
}
