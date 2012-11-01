<?php

namespace Wikibase;
use Traversable, ApiResult, MWException;

/**
 * API serializer for Traversable objects that need to be grouped
 * per property id. Each element needs to have a getPropertyId method.
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
 * @since 0.2
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ByPropertyListSerializer extends ApiSerializerObject {

	/**
	 * @since 0.2
	 *
	 * @var string
	 */
	protected $elementName;

	/**
	 * @since 0.2
	 *
	 * @var ApiSerializer
	 */
	protected $elementSerializer;

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 *
	 * @param string $elementName
	 * @param ApiSerializer $elementSerializer
	 * @param ApiResult $apiResult
	 * @param ApiSerializationOptions|null $options
	 */
	public function __construct( $elementName, ApiSerializer $elementSerializer, ApiResult $apiResult, ApiSerializationOptions $options = null ) {
		parent::__construct( $apiResult, $options );

		$this->elementName = $elementName;
		$this->elementSerializer = $elementSerializer;
	}

	/**
	 * @see ApiSerializer::getSerialized
	 *
	 * @since 0.2
	 *
	 * @param mixed $objects
	 *
	 * @return array
	 * @throws MWException
	 */
	public function getSerialized( $objects ) {
		if ( !( $objects instanceof Traversable ) ) {
			throw new MWException( 'ByPropertyListSerializer can only serialize Traversable objects' );
		}

		$serialization = array();

		// FIXME: "iterator => array => iterator" is stupid
		$objects = new ByPropertyIdArray( iterator_to_array( $objects ) );
		$objects->buildIndex();

		$entityFactory = EntityFactory::singleton();

		foreach ( $objects->getPropertyIds() as $propertyId ) {
			$serializedObjects = array();

			foreach ( $objects->getByPropertyId( $propertyId ) as $object ) {
				$serializedObjects[] = $this->elementSerializer->getSerialized( $object );
			}

			$this->getResult()->setIndexedTagName( $serializedObjects, $this->elementName );

			$propertyId = $entityFactory->getPrefixedId( Property::ENTITY_TYPE, $propertyId );
			$serialization[$propertyId] = $serializedObjects;
		}

		$this->getResult()->setIndexedTagName( $serialization, 'property' );

		return $serialization;
	}

}