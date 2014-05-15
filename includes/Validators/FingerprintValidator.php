<?php

namespace Wikibase\Validators;

use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\Fingerprint;

/**
 * Validator interface for validating Entity Fingerprints.
 *
 * This is intended particularly for uniqueness checks.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
interface FingerprintValidator {

	/**
	 * Validate the given fingerprint.
	 *
	 * @since 0.5
	 *
	 * @param Fingerprint $fingerprint
	 * @param EntityId|null $entityId Context for uniqueness checks: conflicts with this entity
	 *        are ignored.
	 * @param string[]|null $languageCodes If given, the validation may be limited to the given languages;
	 *        This is intended for optimization for the common case of only a single language changing.
	 *
	 * @return Result
	 */
	public function validateFingerprint( Fingerprint $fingerprint, EntityId $entityId = null, $languageCodes = null );

}