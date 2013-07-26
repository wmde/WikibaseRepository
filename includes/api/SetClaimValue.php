<?php

namespace Wikibase\Api;

use ApiBase, MWException;

use DataValues\IllegalValueException;
use ApiMain;
use ValueParsers\ParseException;
use Wikibase\Autocomment;
use Wikibase\EntityId;
use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\EntityContentFactory;
use Wikibase\Lib\PropertyNotFoundException;
use Wikibase\Lib\SnakConstructionService;
use Wikibase\SnakObject;
use Wikibase\Claim;
use Wikibase\Claims;
use Wikibase\Lib\ClaimGuidValidator;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Validators\ValidatorErrorLocalizer;
use Wikibase\validators\SnakValidator;

/**
 * API module for setting the DataValue contained by the main snak of a claim.
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
 * @since 0.3
 *
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SetClaimValue extends ApiWikibase implements IAutocomment{

	/**
	 * @var SnakValidationHelper
	 */
	protected $snakValidation;

	/**
	 * @var SnakConstructionService
	 */
	protected $snakConstruction;

	/**
	 * see ApiBase::__construct()
	 *
	 * @param ApiMain $mainModule
	 * @param string  $moduleName
	 * @param string  $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$this->snakValidation = new SnakValidationHelper(
			$this,
			WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup(),
			WikibaseRepo::getDefaultInstance()->getDataTypeFactory(),
			new ValidatorErrorLocalizer()
		);

		$this->snakConstruction = WikibaseRepo::getDefaultInstance()->getSnakConstructionService();
	}

	/**
	 * @see \ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$content = $this->getEntityContent();

		$params = $this->extractRequestParams();

		try {
			$claim = $this->updateClaim(
				$content->getEntity(),
				$params['claim'],
				$params['snaktype'],
				isset( $params['value'] ) ? \FormatJson::decode( $params['value'], true ) : null
			);

			$this->saveChanges( $content );
			$this->outputClaim( $claim );
		}
		catch ( IllegalValueException $ex ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Invalid snak: IllegalValueException', 'invalid-snak' );
		}
		catch ( PropertyNotFoundException $ex ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Invalid snak: PropertyNotFoundException', 'invalid-snak' );
		}
		catch ( ParseException $parseException ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Invalid guid: ParseException', 'invalid-guid' );
		}

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @since 0.3
	 *
	 * @return \Wikibase\EntityContent
	 */
	protected function getEntityContent() {
		$params = $this->extractRequestParams();

		// @todo generalize handling of settings in api modules
		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$entityPrefixes = $settings->getSetting( 'entityPrefixes' );
		$claimGuidValidator = new ClaimGuidValidator( $entityPrefixes );

		if ( !( $claimGuidValidator->validate( $params['claim'] ) ) ) {
			$this->dieUsage( 'Invalid claim guid' , 'invalid-guid' );
		}

		$entityId = EntityId::newFromPrefixedId( Entity::getIdFromClaimGuid( $params['claim'] ) );
		$entityTitle = EntityContentFactory::singleton()->getTitleForId( $entityId );

		if ( $entityTitle === null ) {
			$this->dieUsage( 'No such entity' , 'no-such-entity' );
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;

		return $this->loadEntityContent( $entityTitle, $baseRevisionId );
	}

	/**
	 * Updates the claim with specified GUID to have a main snak with provided value.
	 * The claim is modified in the passed along entity and is returned as well.
	 *
	 * @since 0.3
	 *
	 * @param \Wikibase\Entity $entity
	 * @param string $guid
	 * @param string $snakType
	 * @param mixed $value
	 *
	 * @return \Wikibase\Claim
	 */
	protected function updateClaim( Entity $entity, $guid, $snakType, $value = null ) {
		$claims = new Claims( $entity->getClaims() );

		if ( !$claims->hasClaimWithGuid( $guid ) ) {
			$this->dieUsage( 'No such claim' , 'no-such-claim' );
		}

		$claim = $claims->getClaimWithGuid( $guid );
		$oldSnak = $claim->getMainSnak();

		try {
			$snak = $this->snakConstruction->newSnak( $oldSnak->getPropertyId(), $snakType, $value );
			$this->snakValidation->validateSnak( $snak );

			$claim->setMainSnak( $snak );
			$entity->setClaims( $claims );

			return $claim;
		} catch ( IllegalValueException $ex ) {
			$this->dieUsage( $ex->getMessage(), 'invalid-snak' );
		}
	}

	/**
	 * @since 0.3
	 *
	 * @param \Wikibase\EntityContent $content
	 */
	protected function saveChanges( EntityContent $content ) {
		$status = $this->attemptSaveEntity(
			$content,
			'', // TODO: automcomment
			EDIT_UPDATE
		);

		$this->addRevisionIdFromStatusToResult( 'pageinfo', 'lastrevid', $status );
	}

	/**
	 * @since 0.3
	 *
	 * @param \Wikibase\Claim $claim
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
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'claim' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'value' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			),
			'snaktype' => array(
				ApiBase::PARAM_TYPE => array( 'value', 'novalue', 'somevalue' ),
				ApiBase::PARAM_REQUIRED => true,
			),
			'token' => null,
			'baserevid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'bot' => false,
		);
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'invalid-guid', 'info' => $this->msg( 'wikibase-api-invalid-guid' )->text() ),
			array( 'code' => 'no-such-entity', 'info' => $this->msg( 'wikibase-api-no-such-entity' )->text() ),
			array( 'code' => 'no-such-claim', 'info' => $this->msg( 'wikibase-api-no-such-claim' )->text() ),
			array( 'code' => 'invalid-snak', 'info' => $this->msg( 'wikibase-api-invalid-snak' )->text() ),
		) );
	}

	/**
	 * @see \ApiBase::getParamDescription
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array(
			'claim' => 'A GUID identifying the claim',
			'snaktype' => 'The type of the snak',
			'value' => 'The value to set the datavalue of the the main snak of the claim to',
			'token' => 'An "edittoken" token previously obtained through the token module (prop=info).',
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),

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
			'API module for setting the value of a Wikibase claim.'
		);
	}

	/**
	 * @see \ApiBase::getExamples
	 *
	 * @since 0.3
	 *
	 * @return array
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetclaimvalue&claim=q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F&snaktype=value&value={"entity-type":"item","numeric-id":1}&token=foobar&baserevid=7201010' => 'Sets the claim with the GUID of q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F to a value of q1',
		);
	}

	/**
	 * @see \Wikibase\Api\IAutocomment::getTextForComment()
	 */
	public function getTextForComment( array $params, $plural = 1 ) {
		return Autocomment::formatAutoComment(
			$this->getModuleName(),
			array(
				/*plural */ (int)isset( $params['claim'] )
			)
		);
	}

	/**
	 * @see \Wikibase\Api\IAutocomment::getTextForSummary()
	 */
	public function getTextForSummary( array $params ) {
		return Autocomment::formatAutoSummary(
			Autocomment::pickValuesFromParams( $params, 'claim' )
		);
	}

	/**
	 * @see ApiBase::isWriteMode
	 * @return bool true
	 */
	public function isWriteMode() {
		return true;
	}

}
