<?php

namespace Wikibase\Validators;

use ValueValidators\Result;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\Fingerprint;

/**
 * Composite validator for applying multiple validators as one.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class CompositeFingerprintValidator implements FingerprintValidator {

	/**
	 * @var FingerprintValidator[]
	 */
	protected $validators;

	/**
	 * @param FingerprintValidator[] $validators
	 */
	public function __construct( array $validators ) {
		$this->validators = $validators;
	}

	/**
	 * Validate a fingerprint by applying each of the validators supplied to the constructor.
	 *
	 * @see FingerprintValidator::validateFingerprint
	 *
	 * @since 0.5
	 *
	 * @param Fingerprint $fingerprint
	 * @param EntityId|null $entityId
	 * @param string[]|null $languageCodes
	 *
	 * @return Result
	 */
	public function validateFingerprint( Fingerprint $fingerprint, EntityId $entityId = null, $languageCodes = null ) {
		foreach ( $this->validators as $validator ) {
			$result = $validator->validateFingerprint( $fingerprint, $entityId, $languageCodes );

			if ( !$result->isValid() ) {
				return $result;
			}
		}

		return Result::newSuccess();
	}

}
