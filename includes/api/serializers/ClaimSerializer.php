<?php

namespace Wikibase;
use MWException;

/**
 * API serializer for Claim objects.
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
class ClaimSerializer extends ApiSerializerObject {

	/**
	 * @since 0.3
	 *
	 * @var string[]
	 */
	protected static $rankMap = array(
		Statement::RANK_DEPRECATED => 'deprecated',
		Statement::RANK_NORMAL => 'normal',
		Statement::RANK_PREFERRED => 'preferred',
	);

	/**
	 * Returns the available ranks in serialized form.
	 *
	 * @since 0.3
	 *
	 * @return string[]
	 */
	public static function getRanks() {
		return array_values( self::$rankMap );
	}

	/**
	 * Unserializes the rank and returns an element from the Statement::RANK_ enum.
	 *
	 * @since 0.3
	 *
	 * @param string $serializedRank
	 *
	 * @return integer
	 */
	public static function unserializeRank( $serializedRank ) {
		$ranks = array_flip( self::$rankMap );
		return $ranks[$serializedRank];
	}

	/**
	 * @see ApiSerializer::getSerialized
	 *
	 * @since 0.2
	 *
	 * @param mixed $claim
	 *
	 * @return array
	 * @throws MWException
	 */
	public function getSerialized( $claim ) {
		if ( !( $claim instanceof Claim ) ) {
			throw new MWException( 'ClaimSerializer can only serialize Claim objects' );
		}

		$serialization['id'] = $claim->getGuid();

		$snakSerializer = new SnakSerializer( $this->getResult(), $this->options );
		$serialization['mainsnak'] = $snakSerializer->getSerialized( $claim->getMainSnak() );

		$snaksSerializer = new ByPropertyListSerializer( 'qualifier', $snakSerializer, $this->getResult(), $this->options );
		$serialization['qualifiers'] = $snaksSerializer->getSerialized( $claim->getQualifiers() );

		$serialization['type'] = $claim instanceof Statement ? 'statement' : 'claim';

		if ( $claim instanceof Statement ) {
			$serialization['rank'] = self::$rankMap[ $claim->getRank() ];

			$snaksSerializer = new ByPropertyListSerializer( 'reference', $snakSerializer, $this->getResult(), $this->options );
			$serialization['references'] = $snaksSerializer->getSerialized( $claim->getReferences() );
		}

		return $serialization;
	}

}