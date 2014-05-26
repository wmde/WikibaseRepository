<?php

namespace Wikibase\ChangeOp;

use Exception;
use InvalidArgumentException;
use ValueValidators\Error;
use ValueValidators\Result;

/**
 * Exception thrown when the validation of a change operation failed.
 * This is essentially a wrapper for ValueValidators\Result.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ChangeOpValidationException extends ChangeOpException {

	/**
	 * @var Result
	 */
	protected $result;

	/**
	 * @param Result $result
	 * @param Exception $previous
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( Result $result, Exception $previous = null ) {
		$messages = $this->composeErrorMessage( $result->getErrors() );
		parent::__construct( 'Validation failed: ' . $messages, 0, $previous );

		$this->result = $result;
	}

	/**
	 * @return Result
	 */
	public function getValidationResult() {
		return $this->result;
	}

	/**
	 * @param Error[] $errors
	 *
	 * @return string
	 */
	private function composeErrorMessage( $errors ) {
		$text = implode( '; ', array_map( function( Error $error ) {
			return $error->getText();
		}, $errors ) );

		return $text;
	}
}
