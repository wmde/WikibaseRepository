<?php

namespace Wikibase\Repo\Query\SQLStore\DVHandler;

use Wikibase\Repo\Query\SQLStore\DataValueHandler;
use Wikibase\Repo\Database\TableDefinition;
use Wikibase\Repo\Database\FieldDefinition;
use DataValues\DataValue;
use DataValues\NumberValue;
use InvalidArgumentException;

/**
 * Represents the mapping between Wikibase\NumberValue and
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
class NumberHandler extends DataValueHandler {

	/**
	 * @see DataValueHandler::getTableDefinition
	 *
	 * @since wd.qe
	 *
	 * @return TableDefinition
	 */
	public function getTableDefinition() {
		$fields = array(
			new FieldDefinition( 'value', FieldDefinition::TYPE_FLOAT, false ),
			new FieldDefinition( 'json', FieldDefinition::TYPE_TEXT, false ),
		);

		return new TableDefinition( 'number', $fields );
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
		return 'value';
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
		return new NumberValue( $valueFieldValue );
	}

	/**
	 * @see DataValueHandler::getLabelFieldName
	 *
	 * @since wd.qe
	 *
	 * @return string|null
	 */
	public function getLabelFieldName() {
		return null;
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
		if ( !( $value instanceof NumberValue ) ) {
			throw new InvalidArgumentException( 'Value is not a NumberValue' );
		}

		return array(
			'value' => $value->getValue(),
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
		if ( !( $value instanceof NumberValue ) ) {
			throw new InvalidArgumentException( 'Value is not a NumberValue' );
		}

		$values = array(
			'json' => $value->getArrayValue(),
			'value' => $value->getValue(),
		);

		return $values;
	}

}