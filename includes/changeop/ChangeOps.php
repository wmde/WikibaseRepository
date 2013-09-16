<?php

namespace Wikibase;

use InvalidArgumentException;

/**
 * Class for holding a batch of change operations
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
 * @since 0.4
 *
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class ChangeOps {

	/**
	 * @since 0.4
	 *
	 * @var ChangeOp[]
	 */
	protected $ops;

	/**
	 * @since 0.4
	 *
	 */
	public function __construct() {
		$this->ops = array();
	}

	/**
	 * Adds a changeOp
	 *
	 * @since 0.4
	 *
	 * @param ChangeOp|ChangeOp[] $changeOp
	 *
	 * @throws InvalidArgumentException
	 */
	public function add( $changeOp ) {
		if ( !is_array( $changeOp ) && !( $changeOp instanceof ChangeOp ) ) {
			throw new InvalidArgumentException( '$changeOp needs to be an instance of ChangeOp or an array of ChangeOps' );
		}

		if ( $changeOp instanceof ChangeOp ) {
			$this->ops[] = $changeOp;
		} else {
			foreach ( $changeOp as $op ) {
				if ( $op instanceof ChangeOp ) {
					$this->ops[] = $op;
				} else {
					throw new InvalidArgumentException( 'array $changeOp must contain ChangeOps only' );
				}
			}
		}
	}

	/**
	 * Get the array of changeOps
	 *
	 * @since 0.4
	 *
	 * @return ChangeOp[]
	 */
	public function getChangeOps() {
		return $this->ops;
	}

	/**
	 * Applies all changes to the given entity
	 *
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param Summary|null $summary
	 *
	 * @throws ChangeOpException
	 * @return bool
	 *
	 */
	public function apply( Entity $entity, Summary $summary = null ) {
		try {
			foreach ( $this->ops as $op ) {
				$op->apply( $entity, $summary );
			}
		} catch ( ChangeOpException $e ) {
			throw new ChangeOpException( 'Exception while applying changes: ' . $e->getMessage() );
		}

		return true;
	}

}
