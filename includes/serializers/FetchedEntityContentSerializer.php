<?php

namespace Wikibase\Serializers;

use ApiResult;
use InvalidArgumentException;
use Content;
use MWException;
use Wikibase\Entity;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\EntityContent;
use Wikibase\LanguageFallbackChain;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Serializers\SerializerObject;
use Wikibase\Lib\Serializers\EntitySerializationOptions;
use Wikibase\Lib\Serializers\SerializerFactory;

/**
 * Serializer for some information related to Content. This is not a full Content serialization,
 * instead the serialized object will contain information required by the UI to create a
 * FetchedEntityContent instance in JavaScript.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 */
class FetchedEntityContentSerializer extends SerializerObject {
	/**
	 * @see SerializerObject::$options
	 * @var FetchedEntityContentSerializationOptions
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @since 0.5
	 *
	 * @param FetchedEntityContentSerializationOptions $options
	 */
	public function __construct( FetchedEntityContentSerializationOptions $options = null ) {
		if( $options === null ) {
			$options = new FetchedEntityContentSerializationOptions();
		}
		parent::__construct( $options );
	}

	/**
	 * @see Serializer::getSerialized
	 *
	 * @since 0.5
	 *
	 * @param EntityContent $entityContent
	 * @return array
	 *
	 * @throws InvalidArgumentException If $entityContent is no instance of Content.
	 */
	public function getSerialized( $entityContent ) {
		if( !( $entityContent instanceof Content ) ) {
			throw new InvalidArgumentException(
				'FetchedEntityContentSerializer can only serialize Content objects' );
		}

		/** @var $entity Entity */
		$entity = $entityContent->getEntity();
		$entityTitle = $entityContent->getTitle();
		$entitySerializationOptions = $this->options->getEntitySerializationOptions();

		$serializerFactory = new SerializerFactory();
		$entitySerializer = $serializerFactory->newSerializerForObject(
			$entity,
			$entitySerializationOptions
		);
		$serialization['content'] = $entitySerializer->getSerialized( $entity );
		$serialization['title'] = $entityTitle->getPrefixedText();
		$serialization['revision'] = $entityTitle->getLatestRevID() ?: '';

		return $serialization;
	}

	/**
	 * Creates a new instance suitable for EntityContent serializations in a form as required in the
	 * frontend's "wikibase.fetchedEntities" global.
	 *
	 * @since 0.5
	 *
	 * @param string $primaryLanguage
	 * @param LanguageFallbackChain|null $languageFallbackChain
	 * @return FetchedEntityContentSerializer
	 */
	public static function newForFrontendStore( $primaryLanguage, LanguageFallbackChain $languageFallbackChain ) {
		$entitySerializationOptions =
			new EntitySerializationOptions( WikibaseRepo::getDefaultInstance()->getIdFormatter() );
		$entitySerializationOptions->setProps( array( 'labels', 'descriptions', 'datatype' ) );
		if ( !$languageFallbackChain ) {
			$languageFallbackChainFactory = WikibaseRepo::getDefaultInstance()->getLanguageFallbackChain();
			$languageFallbackChain = $languageFallbackChainFactory->newFromLanguageCode(
				$primaryLanguage, LanguageFallbackChainFactory::FALLBACK_SELF
			);
		}
		$entitySerializationOptions->setLanguages( array( $primaryLanguage => $languageFallbackChain ) );

		$fetchedEntityContentSerializationOptions =
			new FetchedEntityContentSerializationOptions( $entitySerializationOptions );

		$serializer = new FetchedEntityContentSerializer( $fetchedEntityContentSerializationOptions );
		return $serializer;
	}
}
