<?php

namespace Wikibase\Validators;

use DataTypes\DataTypeFactory;
use DataValues\UnDeserializableValue;
use DataValues\DataValue;
use ValueValidators\Error;
use ValueValidators\Result;
use ValueValidators\ValueValidator;
use Wikibase\Claim;
use Wikibase\Lib\PropertyDataTypeLookup;
use Wikibase\Lib\PropertyNotFoundException;
use Wikibase\PropertyValueSnak;
use Wikibase\Reference;
use Wikibase\References;
use Wikibase\Snak;
use Wikibase\Statement;

/**
 * Class SnakValidator for validating Snaks.
 *
 * @since 0.4
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class SnakValidator implements ValueValidator {

	/**
	 * @var DataTypeFactory
	 */
	protected $dataTypeFactory;

	/**
	 * @var PropertyDataTypeLookup
	 */
	protected $propertyDataTypeLookup;

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup,
		DataTypeFactory $dataTypeFactory ) {

		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->dataTypeFactory = $dataTypeFactory;
	}

	/**
	 * Applies validation to the given Claim.
	 * This is done by validating all snaks contained in the claim, notably:
	 * the main snak, the qualifiers, and all snaks of all references,
	 * in case the claim is a Statement.
	 *
	 * @param \Wikibase\Claim $claim The value to validate
	 *
	 * @return \ValueValidators\Result
	 */
	public function validateClaimSnaks( Claim $claim ) {
		$snak = $claim->getMainSnak();
		$result = $this->validate( $snak );

		if ( !$result->isValid() ) {
			return $result;
		}

		foreach ( $claim->getQualifiers() as $snak ) {
			$result = $this->validate( $snak );

			if ( !$result->isValid() ) {
				return $result;
			}
		}

		if ( $claim instanceof Statement ) {
			$result = $this->validateReferences( $claim->getReferences() );

			if ( !$result->isValid() ) {
				return $result;
			}
		}

		return Result::newSuccess();
	}

	/**
	 * Validate a list of references.
	 * This is done by validating all snaks in all of the references.
	 *
	 * @param References $references
	 * @return \ValueValidators\Result
	 */
	public function validateReferences( References $references ) {
		/* @var Reference $ref */
		foreach ( $references as $ref ) {
			$result = $this->validateReference( $ref );

			if ( !$result->isValid() ) {
				return $result;
			}
		}

		return Result::newSuccess();
	}

	/**
	 * Validate a list of references.
	 * This is done by validating all snaks in all of the references.
	 *
	 * @param Reference $reference
	 * @return \ValueValidators\Result
	 */
	public function validateReference( Reference $reference ) {
		foreach ( $reference->getSnaks() as $snak ) {
			$result = $this->validate( $snak );

			if ( !$result->isValid() ) {
				return $result;
			}
		}

		return Result::newSuccess();
	}

	/**
	 * Validates a Snak.
	 * For a PropertyValueSnak, this is done using the validators from the DataType
	 * that is associated with the Snak's property.
	 * Other Snak types are currently not validated.
	 *
	 * @see ValueValidator::validate()
	 *
	 * @param Snak $snak The value to validate
	 *
	 * @return \ValueValidators\Result
	 * @throws \InvalidArgumentException
	 */
	public function validate( $snak ) {
		// XXX: instead of an instanceof check, we could have multiple validators
		//      with a canValidate() method, to determine which validator to use
		//      for a given snak.

		$propertyId = $snak->getPropertyId();

		try {
			$typeId = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $propertyId );

			if ( $snak instanceof PropertyValueSnak ) {
				$dataValue = $snak->getDataValue();
				$result = $this->validateDataValue( $dataValue, $typeId );
			} else {
				$result = Result::newSuccess();
			}
		} catch ( PropertyNotFoundException $ex ) {
			$result = Result::newError( array(
				Error::newError( "Property $propertyId not found!", null, 'no-such-property', array( $propertyId ) )
			) );
		}

		return $result;
	}

	/**
	 * Validates the given data value using the given data type.
	 *
	 * @param DataValue $dataValue
	 * @param string    $dataTypeId
	 *
	 * @return Result
	 */
	public function validateDataValue( DataValue $dataValue, $dataTypeId ) {
		$dataType = $this->dataTypeFactory->getType( $dataTypeId );

		if ( $dataValue instanceof UnDeserializableValue ) {
			$result = Result::newError( array(
				Error::newError( "Bad snak value: " . $dataValue->getReason(), null, 'bad-value', array( $dataValue->getReason() ) )
			) );
		} elseif ( $dataType->getDataValueType() != $dataValue->getType() ) {
			$result = Result::newError( array(
					Error::newError( "Bad value type: " . $dataValue->getType() . ", expected " . $dataType->getDataValueType(),
						null, 'bad-value-type', array( $dataValue->getType(), $dataType->getDataValueType() ) )
			) );
		} else {
			$result = Result::newSuccess();
		}

		//XXX: Perhaps DataType should have a validate() method (even implement ValueValidator)
		//     At least, DataType should expose only one validator, which would be a CompositeValidator
		foreach ( $dataType->getValidators() as $validator ) {
			$subResult = $validator->validate( $dataValue );

			//XXX: Some validators should be fatal and cause us to abort the loop.
			//     Others shouldn't.

			if ( !$subResult->isValid() ) {
				//TODO: Don't bail out immediately. Accumulate errors from all validators.
				//      We need Result::merge() for this.
				return $subResult;
			}
		}

		return $result;
	}

	/**
	 * @see ValueValidator::setOptions()
	 *
	 * @param array $options
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}

}
