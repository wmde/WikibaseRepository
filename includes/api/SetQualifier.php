<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use Wikibase\ChangeOp\ChangeOpQualifier;
use Wikibase\ChangeOp\ClaimChangeOpFactory;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for creating a qualifier or setting the value of an existing one.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetQualifier extends ModifyClaim {

	/**
	 * @var ClaimChangeOpFactory
	 */
	protected $claimChangeOpFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$changeOpFactoryProvider = WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider();
		$this->claimChangeOpFactory = $changeOpFactoryProvider->getClaimChangeOpFactory();
	}

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->claimGuidParser->parse( $params['claim'] )->getEntityId();
		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$entityRevision = $this->loadEntityRevision( $entityId, $baseRevisionId );
		$entity = $entityRevision->getEntity();

		$summary = $this->claimModificationHelper->createSummary( $params, $this );

		$claim = $this->claimModificationHelper->getClaimFromEntity( $params['claim'], $entity );

		if ( isset( $params['snakhash'] ) ) {
			$this->validateQualifierHash( $claim, $params['snakhash'] );
		}

		$changeOp = $this->getChangeOp();
		$this->claimModificationHelper->applyChangeOp( $changeOp, $entity, $summary );

		$this->saveChanges( $entity, $summary );
		$this->getResultBuilder()->markSuccess();
		$this->getResultBuilder()->addClaim( $claim );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Checks if the required parameters are set and the ones that make no sense given the
	 * snaktype value are not set.
	 *
	 * @since 0.2
	 */
	protected function validateParameters( array $params ) {
		if ( !( $this->claimModificationHelper->validateClaimGuid( $params['claim'] ) ) ) {
			$this->dieError( 'Invalid claim guid' , 'invalid-guid' );
		}

		if ( !isset( $params['snakhash'] ) ) {
			if ( !isset( $params['snaktype'] ) ) {
				$this->dieError( 'When creating a new qualifier (ie when not providing a snakhash) a snaktype should be specified', 'param-missing' );
			}

			if ( !isset( $params['property'] ) ) {
				$this->dieError( 'When creating a new qualifier (ie when not providing a snakhash) a property should be specified', 'param-missing' );
			}
		}

		if ( isset( $params['snaktype'] ) && $params['snaktype'] === 'value' && !isset( $params['value'] ) ) {
			$this->dieError( 'When setting a qualifier that is a PropertyValueSnak, the value needs to be provided', 'param-missing' );
		}
	}

	/**
	 * @since 0.4
	 *
	 * @param Claim $claim
	 * @param string $qualifierHash
	 */
	protected function validateQualifierHash( Claim $claim, $qualifierHash ) {
		if ( !$claim->getQualifiers()->hasSnakHash( $qualifierHash ) ) {
			$this->dieError( "Claim does not have a qualifier with the given hash" , 'no-such-qualifier' );
		}
	}

	/**
	 * @since 0.4
	 *
	 * @return ChangeOpQualifier
	 */
	protected function getChangeOp() {
		$params = $this->extractRequestParams();

		$claimGuid = $params['claim'];

		$propertyId = $this->claimModificationHelper->getEntityIdFromString( $params['property'] );
		if( !$propertyId instanceof PropertyId ){
			$this->dieError(
				$propertyId->getSerialization() . ' does not appear to be a property ID',
				'param-illegal'
			);
		}
		$newQualifier = $this->claimModificationHelper->getSnakInstance( $params, $propertyId );

		if ( isset( $params['snakhash'] ) ) {
			$changeOp = $this->claimChangeOpFactory->newSetQualifierOp( $claimGuid, $newQualifier, $params['snakhash'] );
		} else {
			$changeOp = $this->claimChangeOpFactory->newSetQualifierOp( $claimGuid, $newQualifier, '' );
		}

		return $changeOp;
	}

	/**
	 * @see ApiBase::getAllowedParams
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			array(
				'claim' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'property' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => false,
				),
				'value' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => false,
				),
				'snaktype' => array(
					ApiBase::PARAM_TYPE => array( 'value', 'novalue', 'somevalue' ),
					ApiBase::PARAM_REQUIRED => false,
				),
				'snakhash' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => false,
				),
			),
			parent::getAllowedParams()
		);
	}

	/**
	 * @see ApiBase::getParamDescription
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			array(
				'claim' => 'A GUID identifying the claim for which a qualifier is being set',
				'property' => array(
					'Id of the snaks property.',
					'Should only be provided when creating a new qualifier or changing the property of an existing one'
				),
				'snaktype' => array(
					'The type of the snak.',
					'Should only be provided when creating a new qualifier or changing the type of an existing one'
				),
				'value' => array(
					'The new value of the qualifier. ',
					'Should only be provdied for PropertyValueSnak qualifiers'
				),
				'snakhash' => array(
					'The hash of the snak to modify.',
					'Should only be provided for existing qualifiers'
				),
			)
		);
	}

	/**
	 * @see ApiBase::getDescription
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for creating a qualifier or setting the value of an existing one.'
		);
	}

	/**
	 * @see ApiBase::getExamples
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetqualifier&claim=Q2$4554c0f4-47b2-1cd9-2db9-aa270064c9f3&property=P1&value=GdyjxP8I6XB3&snaktype=value&token=foobar' => 'Set the qualifier for the given claim with property P1 to string value GdyjxP8I6XB3',
		);
	}
}
