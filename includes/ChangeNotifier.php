<?php

namespace Wikibase;

/**
 *
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ChangeNotifier {

	/**
	 * Returns the global instance of the ChangeNotifier interface.
	 *
	 * @since 0.1
	 *
	 * @return ChangeNotifier
	 */
	public static function singleton() {
		static $instance = false;

		if ( $instance === false ) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * @since 0.1
	 * @var bool
	 */
	protected $inTranscation = false;

	/**
	 * The changes stashed in the current transaction.
	 *
	 * @since 0.1
	 * @var array of Change
	 */
	protected $changes = array();

	/**
	 * Begin a transaction.
	 * During the transaction any changes provided will be stashed
	 * and only be committed at the point commit is called.
	 *
	 * @since 0.1
	 */
	public function begin() {
		$this->inTranscation = true;
	}

	/**
	 * Commit all of the stashed changes.
	 *
	 * @since 0.1
	 *
	 * @return \Status
	 */
	public function commit() {
		if ( $this->inTranscation ) {
			$this->inTranscation = false;
			$this->handleChanges( $this->changes );
		}

		return \Status::newGood();
	}

	/**
	 * Handles the provided change.
	 *
	 * @since 0.1
	 *
	 * @param Change $change
	 *
	 * @return \Status
	 */
	public function handleChange( Change $change ) {
		return $this->handleChanges( array( $change ) );
	}

	/**
	 * Handles the provided changes.
	 *
	 * @since 0.1
	 *
	 * @param $changes array of Change
	 *
	 * @return \Status
	 */
	public function handleChanges( array $changes ) {
		if ( $changes !== array() ) {
			if ( $this->inTranscation ) {
				$this->changes = array_merge( $this->changes, $changes );
			}
			else {
				$dbw = wfGetDB( DB_MASTER );

				$dbw->begin();

				foreach ( $changes as $change ) {
					$change->save();
				}

				$dbw->commit();
			}
		}

		return \Status::newGood();
	}

}