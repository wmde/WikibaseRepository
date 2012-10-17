<?php

namespace Wikibase\Test;
use Wikibase\Entity;

/**
 * Tests for the Wikibase\EntitySerializer deriving classes.
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
 * @since 0.2
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseApiSerialization
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class EntitySerializerBaseTest extends ApiSerializerBaseTest {

	/**
	 * @since 0.2
	 *
	 * @return Entity
	 */
	protected abstract function getEntityInstance();

	/**
	 * Returns arguments for entity agnostic arguments that can be returned
	 * by validProvider after making sure the provided serialization contains
	 * anything the entity implementing class requires.
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	protected function semiValidProvider() {
		$entity = $this->getEntityInstance();

		$validArgs = array();

		$options = new \Wikibase\EntitySerializationOptions();
		$options->setProps( array( 'aliases' ) );

		$entity0 = $entity->copy();
		$entity0->setAliases( 'en', array( 'foo', 'bar' ) );
		$entity0->setAliases( 'de', array( 'baz', 'bah' ) );

		$validArgs[] = array(
			$entity0,
			array(
				'id' => $entity0->getPrefixedId(),
				'type' => $entity0->getType(),
				'aliases' => array(
					'en' => array(
						array(
							'value' => 'foo',
							'language' => 'en',
						),
						array(
							'value' => 'bar',
							'language' => 'en',
						),
					),
					'de' => array(
						array(
							'value' => 'baz',
							'language' => 'de',
						),
						array(
							'value' => 'bah',
							'language' => 'de',
						),
					),
				),
			),
			$options,
		);

		$options = new \Wikibase\EntitySerializationOptions();
		$options->setProps( array( 'descriptions', 'labels' ) );

		$entity1 = $entity->copy();
		$entity1->setLabel( 'en', 'foo' );
		$entity1->setLabel( 'de', 'bar' );
		$entity1->setDescription( 'en', 'baz' );
		$entity1->setDescription( 'de', 'bah' );

		$validArgs[] = array(
			$entity1,
			array(
				'id' => $entity1->getPrefixedId(),
				'type' => $entity1->getType(),
				'labels' => array(
					'en' => array(
						'value' => 'foo',
						'language' => 'en',
					),
					'de' => array(
						'value' => 'bar',
						'language' => 'de',
					),
				),
				'descriptions' => array(
					'en' => array(
						'value' => 'baz',
						'language' => 'en',
					),
					'de' => array(
						'value' => 'bah',
						'language' => 'de',
					),
				),
			),
			$options,
		);

		return $validArgs;
	}

}
