<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use ValueValidators\Result;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\Summary;

/**
 * Base class for change operations.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
abstract class ChangeOpBase implements ChangeOp {

	/**
	 * @since 0.4
	 *
	 * @param Summary $summary
	 * @param string $action
	 * @param string $language
	 * @param string|array $args
	 *
	 * @throws InvalidArgumentException
	 */
	protected function updateSummary( $summary, $action, $language = '', $args = '' ) {
		if ( $summary !== null && !$summary instanceof Summary ) {
			throw new InvalidArgumentException( '$summary needs to be an instance of Summary or null' );
		}

		if ( $summary !== null ) {
			$summary->setAction( $action );
			$summary->setLanguage( $language );
			$summary->addAutoSummaryArgs( $args );
		}
	}

	/**
	 * @see ChangeOp::validate()
	 *
	 * This default implementation always returns Result::newSuccess().
	 *
	 * @since 0.5
	 *
	 * @param Entity $entity
	 *
	 * @throws ChangeOpException
	 *
	 * @return Result
	 */
	public function validate( Entity $entity ) {
		return Result::newSuccess();
	}

}
