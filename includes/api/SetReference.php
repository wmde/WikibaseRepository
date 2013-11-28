<?php

namespace Wikibase\Api;

use ApiBase;
use FormatJson;
use InvalidArgumentException;
use OutOfBoundsException;
use Wikibase\ChangeOp\ChangeOpReference;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\SnakList;
use Wikibase\Statement;
use Wikibase\Reference;
use Wikibase\Lib\Serializers\SerializerFactory;

/**
 * API module for creating a reference or setting the value of an existing one.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetReference extends ModifyClaim {

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->claimGuidParser->parse( $params['statement'] )->getEntityId();
		$entityTitle = $this->claimModificationHelper->getEntityTitle( $entityId );
		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$entityContent = $this->loadEntityContent( $entityTitle, $baseRevisionId );
		$entity = $entityContent->getEntity();
		$summary = $this->claimModificationHelper->createSummary( $params, $this );

		$claim = $this->claimModificationHelper->getClaimFromEntity( $params['statement'], $entity );

		if ( ! ( $claim instanceof Statement ) ) {
			$this->dieUsage( 'The referenced claim is not a statement and thus cannot have references', 'not-statement' );
		}

		if ( isset( $params['reference'] ) ) {
			$this->validateReferenceHash( $claim, $params['reference'] );
		}

		if( isset( $params['snaks-order' ] ) ) {
			$snaksOrder = $this->getArrayFromParam( $params['snaks-order'] );
		} else {
			$snaksOrder = array();
		}

		$newReference = new Reference(
			$this->getSnaks(
				$this->getArrayFromParam( $params['snaks'] ),
				$snaksOrder
			)
		);

		$changeOp = $this->getChangeOp( $newReference );

		try {
			$changeOp->apply( $entity, $summary );
		} catch ( ChangeOpException $e ) {
			$this->dieUsage( $e->getMessage(), 'failed-save' );
		}

		$this->saveChanges( $entityContent, $summary );
		$this->getResultBuilder()->markSuccess();
		$this->getResultBuilder()->addReference( $newReference );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Check the provided parameters
	 *
	 * @since 0.4
	 */
	protected function validateParameters( array $params ) {
		if ( !( $this->claimModificationHelper->validateClaimGuid( $params['statement'] ) ) ) {
			$this->dieUsage( 'Invalid claim guid' , 'invalid-guid' );
		}
	}

	/**
	 * @since 0.4
	 *
	 * @param Statement $claim
	 * @param string $referenceHash
	 */
	protected function validateReferenceHash( Statement $claim, $referenceHash ) {
		if ( !$claim->getReferences()->hasReferenceHash( $referenceHash ) ) {
			$this->dieUsage( "Claim does not have a reference with the given hash" , 'no-such-reference' );
		}
	}

	/**
	 * @since 0.5
	 *
	 * @param string $arrayParam
	 *
	 * @return array
	 */
	protected function getArrayFromParam( $arrayParam ) {
		$rawArray = FormatJson::decode( $arrayParam, true );

		if ( !is_array( $rawArray ) || !count( $rawArray ) ) {
			$this->dieUsage( 'No array or invalid JSON given', 'invalid-json' );
		}

		return $rawArray;
	}

	/**
	 * @since 0.3
	 *
	 * @param array $rawSnaks array of snaks
	 * @param array $snakOrder array of property ids the snaks are supposed to be ordered by.
	 *
	 * @return SnakList
	 */
	protected function getSnaks( array $rawSnaks, array $snakOrder = array() ) {
		$snaks = new SnakList();

		$serializerFactory = new SerializerFactory();
		$snakUnserializer = $serializerFactory->newUnserializerForClass( 'Wikibase\Snak' );

		$snakOrder = ( count( $snakOrder ) > 0 ) ? $snakOrder : array_keys( $rawSnaks );

		try {
			foreach( $snakOrder as $propertyId ) {
				if ( !is_array( $rawSnaks[$propertyId] ) ) {
					$this->dieUsage( 'Invalid snak JSON given', 'invalid-json' );
				}
				foreach ( $rawSnaks[$propertyId] as $rawSnak ) {
					if ( !is_array( $rawSnak ) ) {
						$this->dieUsage( 'Invalid snak JSON given', 'invalid-json' );
					}

					$snak = $snakUnserializer->newFromSerialization( $rawSnak );
					$this->snakValidation->validateSnak( $snak );
					$snaks[] = $snak;
				}
			}
		} catch( InvalidArgumentException $invalidArgumentException ) {
			// Handle Snak instantiation failures
			$this->dieUsage(
				'Failed to get reference from reference Serialization '
					. $invalidArgumentException->getMessage(),
				'snak-instantiation-failure'
			);
		} catch( OutOfBoundsException $outOfBoundsException ) {
			$this->dieUsage(
				'Failed to get reference from reference Serialization '
					. $outOfBoundsException->getMessage(),
				'snak-instantiation-failure'
			);
		}

		return $snaks;
	}

	/**
	 * @since 0.4
	 *
	 * @param Reference $reference
	 *
	 * @return ChangeOpReference
	 */
	protected function getChangeOp( Reference $reference ) {
		$params = $this->extractRequestParams();

		$claimGuid = $params['statement'];
		$hash = isset( $params['reference'] ) ? $params['reference'] : '';
		$index = isset( $params['index'] ) ? $params['index'] : null;

		return new ChangeOpReference( $claimGuid, $reference, $hash, $index );
	}

	/**
	 * @see \ApiBase::getAllowedParams
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			array(
				'statement' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'snaks' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'snaks-order' => array(
					ApiBase::PARAM_TYPE => 'string',
				),
				'reference' => array(
					ApiBase::PARAM_TYPE => 'string',
				),
				'index' => array(
					ApiBase::PARAM_TYPE => 'integer',
				),
			),
			parent::getAllowedParams()
		);
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge(
			parent::getPossibleErrors(),
			array(
				array( 'code' => 'no-such-claim', 'info' => $this->msg( 'wikibase-api-no-such-claim' )->text() ),
				array( 'code' => 'not-statement', 'info' => $this->msg( 'wikibase-api-not-statement' )->text() ),
				array( 'code' => 'no-such-reference', 'info' => $this->msg( 'wikibase-api-no-such-reference' )->text() ),
			)
		);
	}

	/**
	 * @see \ApiBase::getParamDescription
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			array(
				'statement' => 'A GUID identifying the statement for which a reference is being set',
				'snaks' => 'The snaks to set the reference to. JSON object with property ids pointing to arrays containing the snaks for that property',
				'reference' => 'A hash of the reference that should be updated. Optional. When not provided, a new reference is created',
				'index' => 'The index within the statement\'s list of references where to move the reference to. Optional. When not provided, an existing reference will stay in place while a new reference will be appended.',
			)
		);
	}

	/**
	 * @see \ApiBase::getDescription
	 *
	 * @since 0.3
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for creating a reference or setting the value of an existing one.'
		);
	}

	/**
	 * @see \ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks={"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string","value":"foo"}}]}&baserevid=7201010&token=foobar'
				=> 'Create a new reference for claim with GUID Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF',
			'api.php?action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&reference=1eb8793c002b1d9820c833d234a1b54c8e94187e&snaks={"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string","value":"bar"}}]}&baserevid=7201010&token=foobar'
				=> 'Set reference for claim with GUID Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF which has hash of 1eb8793c002b1d9820c833d234a1b54c8e94187e',
			'api.php?action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks={"P212":[{"snaktype":"novalue","property":"P212"}]}&index=0&baserevid=7201010&token=foobar'
				=> 'Creates a new reference for the claim with GUID Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF and inserts the new reference at the top of the list of references instead of appending it to the bottom.',
		);
	}
}
