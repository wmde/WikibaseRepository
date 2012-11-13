<?php

namespace Wikibase;
use MWException;

/**
 * API serializer for lists of claims.
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
 * @since 0.3
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ClaimsSerializer extends ApiSerializerObject {

	/**
	 * @see ApiSerializer::getSerialized
	 *
	 * @since 0.2
	 *
	 * @param mixed $claims
	 *
	 * @return array
	 * @throws MWException
	 */
	public function getSerialized( $claims ) {
		if ( !( $claims instanceof Claims ) ) {
			throw new MWException( 'ClaimsSerializer can only serialize Claims objects' );
		}

		$claimSerializer = new ClaimSerializer( $this->getResult(), $this->options );
		$serializer = new ByPropertyListSerializer( 'claim', $claimSerializer, $this->getResult(), $this->options );

		return $serializer->getSerialized( $claims );
	}

}