<?php

namespace Wikibase\Api;

use ApiBase;
use Wikibase\EntityId;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Claims;
use Wikibase\ChangeOpMainSnak;
use Wikibase\ChangeOpException;

/**
 * API module for creating claims.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.2
 *
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class CreateClaim extends ModifyClaim {

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.2
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->claimModificationHelper->getEntityIdFromString( $params['entity'] );
		$entityTitle = $this->claimModificationHelper->getEntityTitle( $entityId );
		$entityContent = $this->getEntityContent( $entityTitle );
		$entity = $entityContent->getEntity();

		$propertyId = $this->claimModificationHelper->getEntityIdFromString( $params['property'] );

		$snak = $this->claimModificationHelper->getSnakInstance( $params, $propertyId );

		$summary = $this->claimModificationHelper->createSummary( $params, $this );
		$changeOp = new ChangeOpMainSnak( '', $snak, WikibaseRepo::getDefaultInstance()->getIdFormatter() );

		try {
			$changeOp->apply( $entity, $summary );
		} catch ( ChangeOpException $e ) {
			$this->dieUsage( $e->getMessage(), 'failed-save' );
		}

		$claims = new Claims( $entity->getClaims() );
		$claim = $claims->getClaimWithGuid( $changeOp->getClaimGuid() );

		$this->saveChanges( $entityContent, $summary );

		$this->claimModificationHelper->addClaimToApiResult( $claim );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Checks if the required parameters are set and the ones that make no sense given the
	 * snaktype value are not set.
	 *
	 * @since 0.2
	 *
	 * @params array $params
	 */
	protected function validateParameters( array $params ) {
		if ( $params['snaktype'] == 'value' XOR isset( $params['value'] ) ) {
			if ( $params['snaktype'] == 'value' ) {
				$this->dieUsage( 'A value needs to be provided when creating a claim with PropertyValueSnak snak', 'param-missing' );
			}
			else {
				$this->dieUsage( 'You cannot provide a value when creating a claim with no PropertyValueSnak as main snak', 'param-illegal' );
			}
		}

		if ( !isset( $params['property'] ) ) {
			$this->dieUsage( 'A property ID needs to be provided when creating a claim with a Snak', 'param-missing' );
		}

		if ( isset( $params['value'] ) && \FormatJson::decode( $params['value'], true ) == null ) {
			$this->dieUsage( 'Could not decode snak value', 'invalid-snak' );
		}
	}

	/**
	 * @see \ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge(
			parent::getPossibleErrors(),
			$this->claimModificationHelper->getPossibleErrors(),
			array(
				array( 'code' => 'param-missing', 'info' => $this->msg( 'wikibase-api-param-missing' )->text() ),
				array( 'code' => 'param-illegal', 'info' => $this->msg( 'wikibase-api-param-illegal' )->text() ),
			)
		);
	}

	/**
	 * @see \ApiBase::getAllowedParams
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'entity' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'snaktype' => array(
					ApiBase::PARAM_TYPE => array( 'value', 'novalue', 'somevalue' ),
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
			)
		);
	}

	/**
	 * @see \ApiBase::getParamDescription
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			array(
				'entity' => 'Id of the entity you are adding the claim to',
				'property' => 'Id of the snaks property',
				'value' => 'Value of the snak when creating a claim with a snak that has a value',
				'snaktype' => 'The type of the snak',
			)
		);
	}

	/**
	 * @see \ApiBase::getDescription
	 */
	public function getDescription() {
		return array(
			'API module for creating Wikibase claims.'
		);
	}

	/**
	 * @see \ApiBase::getExamples
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbcreateclaim&entity=q42&property=p9001&snaktype=novalue' => 'Creates a claim for item q42 of property p9001 with a novalue snak.',
			'api.php?action=wbcreateclaim&entity=q42&property=p9002&snaktype=value&value="itsastring"' => ' Creates a claim for item q42 of property p9002 with string value "itsastring"',
			'api.php?action=wbcreateclaim&entity=q42&property=p9003&snaktype=value&value={"entity-type":"item","numeric-id":1}' => 'Creates a claim for item q42 of property p9003 with a value of item q1',
		);
	}
}
