<?php

namespace Wikibase\Repo\Interactors;

use Exception;
use MessageException;

/**
 * Exception representing a token check failure.
 *
 * @since 0.5
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class TokenCheckException extends MessageException {

	/**
	 * @var string
	 */
	private $errorCode;

	/**
	 * @param string $message A free form message, for logging and debugging
	 * @param string $errorCode An error code, for use in the API
	 * @param Exception $previous The previous exception that caused this exception.
	 */
	public function __construct( $message, $errorCode = '', Exception $previous = null ) {
		parent::__construct( 'wikibase-tokencheck-' . $errorCode, array(), $message, $previous );
		$this->errorCode = $errorCode;
	}

	/**
	 * @return string
	 */
	public function getErrorCode() {
		return $this->errorCode;
	}

}
