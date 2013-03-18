<?php

namespace Wikibase\Repo\Test\Query\SQLStore;

use DataValues\DataValue;
use Wikibase\Repo\Query\SQLStore\DataValueHandler;

/**
 * Unit tests for the Wikibase\Repo\Query\SQLStore\DataValueHandler implementing classes.
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
 * @file
 * @since wd.qe
 *
 * @ingroup WikibaseRepoTest
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class DataValueHandlerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @since wd.qe
	 *
	 * @return DataValueHandler[]
	 */
	protected abstract function getInstances();

	/**
	 * @since wd.qe
	 *
	 * @return DataValue[]
	 */
	protected abstract function getValues();

	/**
	 * @since wd.qe
	 *
	 * @return DataValueHandler[][]
	 */
	public function instanceProvider() {
		return $this->arrayWrap( $this->getInstances() );
	}

	/**
	 * @since wd.qe
	 *
	 * @return DataValue[][]
	 */
	public function valueProvider() {
		return $this->arrayWrap( $this->getValues() );
	}

	/**
	 * @since wd.qe
	 *
	 * @return DataValueHandler
	 */
	protected function newInstance() {
		$instances = $this->getInstances();
		return reset( $instances );
	}

	protected function arrayWrap( array $elements ) {
		return array_map(
			function ( $element ) {
				return array( $element );
			},
			$elements
		);
	}

	/**
	 * @dataProvider instanceProvider
	 *
	 * @param DataValueHandler $dvHandler
	 */
	public function testGetDataValueTableReturnType( DataValueHandler $dvHandler ) {
		$this->assertInstanceOf( 'Wikibase\Repo\Query\SQLStore\DataValueTable', $dvHandler->getDataValueTable() );
	}

	/**
	 * @dataProvider valueProvider
	 *
	 * @param DataValue $value
	 */
	public function testGetWhereConditionsReturnType( DataValue $value ) {
		$instance = $this->newInstance();

		$whereConditions = $instance->getWhereConditions( $value );

		$this->assertInternalType( 'array', $whereConditions );
		$this->assertNotEmpty( $whereConditions );
	}

	/**
	 * @dataProvider valueProvider
	 *
	 * @param DataValue $value
	 */
	public function testGetInsertValuesReturnType( DataValue $value ) {
		$instance = $this->newInstance();

		$insertValues = $instance->getInsertValues( $value );

		$this->assertInternalType( 'array', $insertValues );
		$this->assertNotEmpty( $insertValues );

		$this->assertArrayHasKey( $instance->getDataValueTable()->getValueFieldName(), $insertValues );
		$this->assertArrayHasKey( $instance->getDataValueTable()->getSortFieldName(), $insertValues );

		if ( $instance->getDataValueTable()->getLabelFieldName() !== null ) {
			$this->assertArrayHasKey( $instance->getDataValueTable()->getLabelFieldName(), $insertValues );
		}
	}

	/**
	 * @dataProvider valueProvider
	 *
	 * @param DataValue $value
	 */
	public function testNewDataValueFromValueFieldValue( DataValue $value ) {
		$instance = $this->newInstance();

		$fieldValues = $instance->getInsertValues( $value );
		$valueFieldValue = $fieldValues[$instance->getDataValueTable()->getValueFieldName()];

		$newValue = $instance->newDataValueFromValueField( $valueFieldValue );

		$this->assertTrue(
			$value->equals( $newValue ),
			'Newly constructed DataValue equals the old one'
		);
	}

}
