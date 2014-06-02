<?php

namespace Wikibase\Repo;

/**
 * Dispatches a notification to a set of watchers.
 *
 * @todo: should go into MediaWiki core.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class GenericEventDispatcher {

	/**
	 * @var array
	 */
	private $watchers = array();

	/**
	 * @var int
	 */
	private $key = 0;

	/**
	 * @var string
	 */
	private $interface;

	/**
	 * @param string $interface the interface watchers must implement
	 */
	public function __construct( $interface ) {
		$this->interface = $interface;
	}

	/**
	 * Registers a watcher. The watcher will be called whenever
	 * the dispatch() method is called, until the watcher is unregistered.
	 *
	 * @param object $listener
	 *
	 * @throws \InvalidArgumentException
	 * @return mixed The listener key, for removing the listener later.
	 */
	public function registerWatcher( $listener ) {
		if ( !is_subclass_of( $listener, $this->interface ) ) {
			throw new \InvalidArgumentException( '$listener must implement ' . $this->interface );
		}

		$key = ++$this->key;
		$this->watchers[ $key ] = $listener;
		return $key;
	}

	/**
	 * Unregisters a watcher using its registration key. The watcher will no longer
	 * be called by dispatch().
	 *
	 * @param mixed $key A watcher key as returned by registerWatcher().
	 *
	 * @throws \InvalidArgumentException
	 */
	public function unregisterWatcher( $key ) {
		if ( is_object( $key ) || is_array( $key ) ) {
			throw new \InvalidArgumentException( '$key must be a primitive value' );
		}

		if ( isset( $this->watchers[$key] ) ) {
			unset( $this->watchers[$key] );
		}
	}

	/**
	 * Dispatches a notification to all registered watchers.
	 *
	 * @param string $event the name of the event, that is,
	 *        the name of the method to call on the watchers.
	 *
	 * @param * any extra parameters are passed to the watcher method.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function dispatch( $event ) {
		if ( !is_string( $event ) ) {
			throw new \InvalidArgumentException( '$event must be a string' );
		}

		$args = func_get_args();
		$event = array_shift( $args );

		foreach ( $this->watchers as $watcher ) {
			call_user_func_array( array( $watcher, $event ), $args );
		}
	}

}
