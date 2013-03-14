<?php

namespace Wikibase\Repo\Query\SQLStore\DVHandler;

use DataValues\DataValue;
use DataValues\GeoCoordinateValue;
use InvalidArgumentException;
use Wikibase\Repo\Database\FieldDefinition;
use Wikibase\Repo\Database\TableDefinition;
use Wikibase\Repo\Query\SQLStore\DataValueHandler;

/**
 * Represents the mapping between DataValues\GeoCoordinateValue and
 * the corresponding table in the store.
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
 * @since wd.qe
 *
 * @file
 * @ingroup WikibaseSQLStore
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GeoCoordinateHandler extends DataValueHandler {

	/**
	 * @see DataValueHandler::getTableDefinition
	 *
	 * @since wd.qe
	 *
	 * @return TableDefinition
	 */
	public function getTableDefinition() {
		$fields = array(
			new FieldDefinition( 'lat', FieldDefinition::TYPE_FLOAT, false ),
			new FieldDefinition( 'lon', FieldDefinition::TYPE_FLOAT, false ),
			new FieldDefinition( 'alt', FieldDefinition::TYPE_FLOAT, true ),
			new FieldDefinition( 'globe', FieldDefinition::TYPE_TEXT, true ),
			new FieldDefinition( 'json', FieldDefinition::TYPE_TEXT, false ),
		);

		return new TableDefinition( 'geo', $fields );
	}

	/**
	 * @see DataValueHandler::getValueFieldName
	 *
	 * @since wd.qe
	 *
	 * @return string
	 */
	public function getValueFieldName() {
		return 'json';
	}

	/**
	 * @see DataValueHandler::getSortFieldName
	 *
	 * @since wd.qe
	 *
	 * @return string
	 */
	public function getSortFieldName() {
		return 'lat';
	}

	/**
	 * @see DataValueHandler::newDataValueFromValueField
	 *
	 * @since wd.qe
	 *
	 * @param $valueFieldValue // TODO: mixed or string?
	 *
	 * @return DataValue
	 */
	public function newDataValueFromValueField( $valueFieldValue ) {
		return GeoCoordinateValue::newFromArray( json_decode( $valueFieldValue, true ) );
	}

	/**
	 * @see DataValueHandler::getWhereConditions
	 *
	 * @since wd.qe
	 *
	 * @param DataValue $value
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getWhereConditions( DataValue $value ) {
		if ( !( $value instanceof GeoCoordinateValue ) ) {
			throw new InvalidArgumentException( 'Value is not a GeoCoordinateValue' );
		}

		return array(
			// Note: the code in this package is not dependent on MW.
			// So do not replace this with FormatJSON::encode.
			'json' => json_encode( $value->getArrayValue() ),
		);
	}

	/**
	 * @see DataValueHandler::getInsertValues
	 *
	 * @since wd.qe
	 *
	 * @param DataValue $value
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getInsertValues( DataValue $value ) {
		if ( !( $value instanceof GeoCoordinateValue ) ) {
			throw new InvalidArgumentException( 'Value is not a GeoCoordinateValue' );
		}

		$values = array(
			'lat' => $value->getLatitude(),
			'lon' => $value->getLongitude(),

			// Note: the code in this package is not dependent on MW.
			// So do not replace this with FormatJSON::encode.
			'json' => json_encode( $value->getArrayValue() ),
		);

		if ( $value->getAltitude() !== null ) {
			$values['alt'] = $value->getAltitude();
		}

		if ( $value->getGlobe() !== null ) {
			$values['globe'] = $value->getGlobe();
		}

		return $values;
	}

}