<?php

namespace Wikibase\Api;

use MWException;
use ApiBase;
use Diff\ListDiffer;
use Wikibase\EntityContent;
use Wikibase\Claim;
use Wikibase\EntityContentFactory;
use Wikibase\ClaimDiffer;
use Wikibase\ClaimSaver;
use Wikibase\ClaimSummaryBuilder;
use Wikibase\Summary;

/**
 * API module for creating or updating an entire Claim.
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
 * @since 0.4
 *
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetClaim extends ApiWikibase {

	// TODO: rights

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.4
	 */
	public function execute() {
		$claim = $this->getClaimFromRequest();

		$claimDiffer = new ClaimDiffer( new ListDiffer() );
		$claimSummaryBuilder = new ClaimSummaryBuilder( $this->getModuleName(), $claimDiffer );
		$claimSaver = new ClaimSaver();

		$params = $this->extractRequestParams();
		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$token = isset( $params['token'] ) ? $params['token'] : '';

		$newRevisionId = null;

		$status = $claimSaver->saveClaim(
			$claim,
			$baseRevisionId,
			$token,
			$this->getUser(),
			$claimSummaryBuilder
		);
		$this->handleSaveStatus( $status ); // die on error, report warnings, etc

		$statusValue = $status->getValue();
		$newRevisionId = isset( $statusValue['revision'] ) ? $statusValue['revision']->getId() : null;

		if ( $newRevisionId !== null ) {
			$this->getResult()->addValue(
				'pageinfo',
				'lastrevid',
				$newRevisionId
			);
		}

		$this->outputClaim( $claim );
	}

	/**
	 * @since 0.4
	 *
	 * @return Claim
	 */
	protected function getClaimFromRequest() {
		$serializerFactory = new \Wikibase\Lib\Serializers\SerializerFactory();
		$unserializer = $serializerFactory->newUnserializerForClass( 'Wikibase\Claim' );

		$params = $this->extractRequestParams();
		$claim = $unserializer->newFromSerialization( \FormatJson::decode( $params['claim'], true ) );

		assert( $claim instanceof Claim );

		return $claim;
	}

	/**
	 * @since 0.4
	 *
	 * @param Claim $claim
	 */
	protected function outputClaim( Claim $claim ) {
		$serializerFactory = new \Wikibase\Lib\Serializers\SerializerFactory();
		$serializer = $serializerFactory->newSerializerForObject( $claim );
		$serializer->getOptions()->setIndexTags( $this->getResult()->getIsRawMode() );

		$this->getResult()->addValue(
			null,
			'claim',
			$serializer->getSerialized( $claim )
		);
	}

	/**
	 * @see ApiBase::getAllowedParams
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'token' => null,
			'baserevid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
		);
	}

	/**
	 * @see ApiBase::getParamDescription
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'claim' => 'Claim serialization',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
		);
	}

	/**
	 * @see ApiBase::getDescription
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getDescription() {
		return array(
			'API module for creating or updating an entire Claim.'
		);
	}

	/**
	 * @see ApiBase::getExamples
	 *
	 * @since 0.4
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=setclaim&claim={json-stuff}&baserevid=9042&token=foobar'
			// 'ex' => 'desc'
		);
	}

}
