<?php

namespace Wikibase\Api;

use ApiBase;
use DataValues\IllegalValueException;
use InvalidArgumentException;
use MWException;
use Revision;
use SiteList;
use SiteSQLStore;
use Title;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpAliases;
use Wikibase\ChangeOp\ChangeOpClaim;
use Wikibase\ChangeOp\ChangeOpDescription;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\ChangeOpLabel;
use Wikibase\ChangeOp\ChangeOpMainSnak;
use Wikibase\ChangeOp\ChangeOpSiteLink;
use Wikibase\ChangeOp\ChangeOps;
use Wikibase\Claim;
use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\Item;
use Wikibase\Lib\ClaimGuidGenerator;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Property;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Settings;
use Wikibase\Summary;
use Wikibase\Utils;
use WikiPage;

/**
 * Derived class for API modules modifying a single entity identified by id xor a combination of site and page title.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Adam Shorland
 * @author Michał Łazowik
 */
class EditEntity extends ModifyEntity {

	/**
	 * @since 0.4
	 *
	 * @var string[]
	 */
	protected $validLanguageCodes;

	/**
	 * @see ApiBase::_construct()
	 */
	public function __construct( $mainModule, $moduleName ) {
		parent::__construct( $mainModule, $moduleName );

		$this->validLanguageCodes = array_flip( Utils::getLanguageCodes() );
	}

	/**
	 * @see \Wikibase\Api\ApiWikibase::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( EntityContent $entityContent, array $params ) {
		$permissions = parent::getRequiredPermissions( $entityContent, $params );
		$type = $entityContent->getEntity()->getType();
		$exists = $entityContent->getTitle()->exists();
		if( !$exists ){
			$permissions[] = 'createpage';
			$permissions[] = $type . '-create';
		} else {
			$permissions[] = $type . '-override';
		}
		return $permissions;
	}

	/**
	 * @see ApiModifyEntity::createEntity()
	 */
	protected function createEntity( array $params ) {
		$type = $params['new'];
		$this->flags |= EDIT_NEW;
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();
		try {
			$entityContent = $entityContentFactory->newFromType( $type );
		} catch ( InvalidArgumentException $e ) {
			$this->dieUsage( "No such entity type: '$type'", 'no-such-entity-type' );
		}
		/** @var $entityContent EntityContent */
		$entityContent->grabFreshId();
		return $entityContent;
	}

	/**
	 * @see \Wikibase\Api\ModifyEntity::validateParameters()
	 */
	protected function validateParameters( array $params ) {
		$hasId = isset( $params['id'] );
		$hasNew = isset( $params['new'] );
		$hasSitelink = ( isset( $params['site'] ) && isset( $params['title'] ) );
		$hasSitelinkPart = ( isset( $params['site'] ) || isset( $params['title'] ) );

		if ( !( $hasId XOR $hasSitelink XOR $hasNew ) ) {
			$this->dieUsage( 'Either provide the item "id" or pairs of "site" and "title" or a "new" type for an entity' , 'param-missing' );
		}
		if( $hasId && $hasSitelink ){
			$this->dieUsage( "Parameter 'id' and 'site', 'title' combination are not allowed to be both set in the same request", 'param-illegal' );
		}
		if( ( $hasId || $hasSitelinkPart ) && $hasNew ){
			$this->dieUsage( "Parameters 'id', 'site', 'title' and 'new' are not allowed to be both set in the same request", 'param-illegal' );
		}
	}

	/**
	 * @see \Wikibase\Api\ModifyEntity::modifyEntity()
	 */
	protected function modifyEntity( EntityContent &$entityContent, array $params ) {
		wfProfileIn( __METHOD__ );

		$entity = $entityContent->getEntity();
		$this->validateDataParameter( $params );
		$data = json_decode( $params['data'], true );
		$this->validateDataProperties( $data, $entityContent->getWikiPage() );

		if ( $params['clear'] ) {
			$entity->clear();
		}

		// if we create a new property, make sure we set the datatype
		if( !$entityContent->getTitle()->exists() && $entity instanceof Property ){
			if ( !isset( $data['datatype'] ) ) {
				$this->dieUsage( 'No datatype given', 'param-illegal' );
			} else {
				$entity->setDataTypeId( $data['datatype'] );
			}
		}

		$changeOps = $this->getChangeOps( $data, $entity );

		try {
			$changeOps->apply( $entity );
		} catch ( ChangeOpException $e ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Change could not be applied to entity: ' . $e->getMessage(), 'failed-save' );
		}

		$this->buildResult( $entity );
		$summary = $this->getSummary( $params );

		wfProfileOut( __METHOD__ );
		return $summary;
	}

	/**
	 * @param array $params
	 * @return Summary
	 */
	private function getSummary( $params ) {
		//TODO: Construct a nice and meaningful summary from the changes that get applied!
		//      Perhaps that could be based on the resulting diff?]
		$summary = $this->createSummary( $params );
		if ( isset( $params['id'] ) XOR ( isset( $params['site'] ) && isset( $params['title'] ) ) ) {
			$summary->setAction( $params['clear'] === false ? 'update' : 'override' );
		} else {
			$summary->setAction( 'create' );
		}#
		return $summary;
	}

	protected function getChangeOps( array $data, Entity $entity ) {
		$changeOps = new ChangeOps();

		if ( array_key_exists( 'labels', $data ) ) {
			$changeOps->add( $this->getLabelChangeOps( $data['labels'] ) );
		}

		if ( array_key_exists( 'descriptions', $data ) ) {
			$changeOps->add( $this->getDescriptionChangeOps( $data['descriptions'] ) );
		}

		if ( array_key_exists( 'aliases', $data ) ) {
			$changeOps->add( $this->getAliasesChangeOps( $data['aliases'] ) );
		}

		if ( array_key_exists( 'sitelinks', $data ) ) {
			if ( $entity->getType() !== Item::ENTITY_TYPE ) {
				$this->dieUsage( "Non Items can not have sitelinks", 'not-recognized' );
			}

			$changeOps->add( $this->getSiteLinksChangeOps( $data['sitelinks'], $entity ) );
		}

		if( array_key_exists( 'claims', $data ) ) {
			$changeOps->add(
				$this->getClaimsChangeOps(
					$data['claims'],
					new ClaimGuidGenerator( $entity->getId() )
				)
			);
		}

		return $changeOps;
	}

	/**
	 * @since 0.4
	 * @param array $labels
	 * @return ChangeOpLabel[]
	 */
	protected function getLabelChangeOps( $labels  ) {
		$labelChangeOps = array();

		if ( !is_array( $labels ) ) {
			$this->dieUsage( "List of labels must be an array", 'not-recognized-array' );
		}

		foreach ( $labels as $langCode => $arg ) {
			$this->validateMultilangArgs( $arg, $langCode );

			$language = $arg['language'];
			$newLabel = $this->stringNormalizer->trimToNFC( $arg['value'] );

			if ( array_key_exists( 'remove', $arg ) || $newLabel === "" ) {
				$labelChangeOps[] = new ChangeOpLabel( $language, null );
			}
			else {
				$labelChangeOps[] = new ChangeOpLabel( $language, $newLabel );
			}
		}

		return $labelChangeOps;
	}

	/**
	 * @since 0.4
	 * @param array $descriptions
	 * @return ChangeOpdescription[]
	 */
	protected function getDescriptionChangeOps( $descriptions ) {
		$descriptionChangeOps = array();

		if ( !is_array( $descriptions ) ) {
			$this->dieUsage( "List of descriptions must be an array", 'not-recognized-array' );
		}

		foreach ( $descriptions as $langCode => $arg ) {
			$this->validateMultilangArgs( $arg, $langCode );

			$language = $arg['language'];
			$newDescription = $this->stringNormalizer->trimToNFC( $arg['value'] );

			if ( array_key_exists( 'remove', $arg ) || $newDescription === "" ) {
				$descriptionChangeOps[] = new ChangeOpDescription( $language, null );
			}
			else {
				$descriptionChangeOps[] = new ChangeOpDescription( $language, $newDescription );
			}
		}

		return $descriptionChangeOps;
	}

	/**
	 * @since 0.4
	 * @param array $aliases
	 * @return ChangeOpAliases[]
	 */
	protected function getAliasesChangeOps( $aliases ) {
		if ( !is_array( $aliases ) ) {
			$this->dieUsage( "List of aliases must be an array", 'not-recognized-array' );
		}

		$indexedAliases = $this->getIndexedAliases( $aliases );
		$aliasesChangeOps = $this->getIndexedAliasesChangeOps( $indexedAliases );

		return $aliasesChangeOps;
	}

	/**
	 * @param array $aliases
	 * @return array
	 */
	protected function getIndexedAliases( array $aliases ) {
		$indexedAliases = array();

		foreach ( $aliases as $langCode => $arg ) {
			if ( intval( $langCode ) ) {
				$indexedAliases[] = ( array_values($arg) === $arg ) ? $arg : array( $arg );
			} else {
				$indexedAliases[$langCode] = ( array_values($arg) === $arg ) ? $arg : array( $arg );
			}
		}

		return $indexedAliases;
	}

	/**
	 * @param array $indexedAliases
	 * @return ChangeOpAliases[]
	 */
	protected function getIndexedAliasesChangeOps( array $indexedAliases ) {
		$aliasesChangeOps = array();
		foreach ( $indexedAliases as $langCode => $args ) {
			$aliasesToSet = array();

			foreach ( $args as $arg ) {
				$this->validateMultilangArgs( $arg, $langCode );

				$alias = array( $this->stringNormalizer->trimToNFC( $arg['value'] ) );
				$language = $arg['language'];

				if ( array_key_exists( 'remove', $arg ) ) {
					$aliasesChangeOps[] = new ChangeOpAliases( $language, $alias, 'remove' );
				} elseif ( array_key_exists( 'add', $arg ) ) {
					$aliasesChangeOps[] = new ChangeOpAliases( $language, $alias, 'add' );
				}  else {
					$aliasesToSet[] = $alias[0];
				}
			}

			if ( $aliasesToSet !== array() ) {
				$aliasesChangeOps[] = new ChangeOpAliases( $language, $aliasesToSet, 'set' );
			}
		}

		return $aliasesChangeOps;
	}

	/**
	 * @since 0.4
	 *
	 * @param array $siteLinks
	 * @param Entity $entity
	 *
	 * @return ChangeOpSiteLink[]
	 */
	protected function getSitelinksChangeOps( $siteLinks, Entity $entity ) {
		$siteLinksChangeOps = array();

		if ( !is_array( $siteLinks ) ) {
			$this->dieUsage( "List of sitelinks must be an array", 'not-recognized-array' );
		}

		$sites = $this->siteLinkTargetProvider->getSiteList( Settings::get( 'siteLinkGroups' ) );

		foreach ( $siteLinks as $siteId => $arg ) {
			$this->checkSiteLinks( $arg, $siteId, $sites );
			$globalSiteId = $arg['site'];

			$shouldRemove = array_key_exists( 'remove', $arg )
				|| ( !isset( $arg['title'] ) && !isset( $arg['badges'] ) )
				|| ( isset( $arg['title'] ) && $arg['title'] === '' );

			if ( $sites->hasSite( $globalSiteId ) ) {
				$linkSite = $sites->getSite( $globalSiteId );
			} else {
				$this->dieUsage( "There is no site for global site id '$globalSiteId'", 'no-such-site' );
			}

			if ( $shouldRemove ) {
				$siteLinksChangeOps[] = new ChangeOpSiteLink( $globalSiteId );
			} else {
				$badges = ( isset( $arg['badges'] ) )
					? $this->parseSiteLinkBadges( $arg['badges'] )
					: null;

				if ( isset( $arg['title'] ) ) {
					$linkPage = $linkSite->normalizePageName( $this->stringNormalizer->trimWhitespace( $arg['title'] ) );

					if ( $linkPage === false ) {
						$this->dieUsage(
							"The external client site did not provide page information for site '{$globalSiteId}' and title '{$pageTitle}'",
							'no-external-page' );
					}
				} else {
					$linkPage = null;

					if ( !$entity->hasLinkToSite( $globalSiteId ) ) {
						$this->dieUsage( "Cannot modify badges: sitelink to '{$globalSiteId}' doesn't exist", 'no-such-sitelink' );
					}
				}

				$siteLinksChangeOps[] = new ChangeOpSiteLink( $globalSiteId, $linkPage, $badges );
			}
		}

		return $siteLinksChangeOps;
	}

	/**
	 * @since 0.5
	 *
	 * @param array $claims
	 * @param ClaimGuidGenerator $guidGenerator
	 * @return ChangeOpClaim[]
	 */
	protected function getClaimsChangeOps( $claims, $guidGenerator ) {
		if ( !is_array( $claims ) ) {
			$this->dieUsage( "List of claims must be an array", 'not-recognized-array' );
		}
		$changeOps = array();

		//check if the array is associative or in arrays by property
		if( array_keys( $claims ) !== range( 0, count( $claims ) - 1 ) ){
			foreach( $claims as $subClaims ){
				$changeOps = array_merge( $changeOps,
					$this->getRemoveClaimsChangeOps( $subClaims, $guidGenerator ),
					$this->getModifyClaimsChangeOps( $subClaims, $guidGenerator ) );
			}
		} else {
			$changeOps = array_merge( $changeOps,
				$this->getRemoveClaimsChangeOps( $claims, $guidGenerator ),
				$this->getModifyClaimsChangeOps( $claims, $guidGenerator ) );
		}

		return $changeOps;
	}

	/**
	 * @param array $claims array of serialized claims
	 * @param ClaimGuidGenerator $guidGenerator
	 * @return ChangeOp[]
	 */
	private function getModifyClaimsChangeOps( $claims, $guidGenerator ){
		$opsToReturn = array();

		$serializerFactory = new SerializerFactory();
		$unserializer = $serializerFactory->newUnserializerForClass( 'Wikibase\Claim' );

		foreach ( $claims as $claimArray ) {
			if( !array_key_exists( 'remove', $claimArray ) ){

				try {
					$claim = $unserializer->newFromSerialization( $claimArray );
					assert( $claim instanceof Claim );
				} catch ( IllegalValueException $illegalValueException ) {
					$this->dieUsage( $illegalValueException->getMessage(), 'invalid-claim' );
				} catch ( MWException $mwException ) {
					$this->dieUsage( $mwException->getMessage(), 'invalid-claim' );
				}
				/**	 @var $claim Claim  */

				if( array_key_exists( 'id', $claimArray ) ){
					$opsToReturn[] = new ChangeOpMainSnak(
						$claim->getGuid(),
						null,
						$guidGenerator
					);
				}
				$opsToReturn[] = new ChangeOpClaim( $claim, $guidGenerator );
			}
		}
		return $opsToReturn;
	}

	/**
	 * Get changeops that remove all claims that have the 'remove' key in the array
	 * @param array $claims array of serialized claims
	 * @param ClaimGuidGenerator $guidGenerator
	 * @return ChangeOp[]
	 */
	private function getRemoveClaimsChangeOps( $claims, $guidGenerator ) {
		$opsToReturn = array();
		foreach ( $claims as $claimArray ) {
			if( array_key_exists( 'remove', $claimArray ) ){
				if( array_key_exists( 'id', $claimArray ) ){
					$opsToReturn[] = new ChangeOpMainSnak(
						$claimArray['id'],
						null,
						$guidGenerator
					);
				} else {
					$this->dieUsage( 'Cannot remove a claim with no GUID', 'invalid-claim' );
				}
			}
		}
		return $opsToReturn;
	}

	/**
	 * @param Entity $entity
	 */
	protected function buildResult( Entity $entity ) {
		$this->getResultBuilder()->addLabels( $entity->getLabels(), 'entity' );
		$this->getResultBuilder()->addDescriptions( $entity->getDescriptions(), 'entity' );
		$this->getResultBuilder()->addAliases( $entity->getAllAliases(), 'entity' );

		if ( $entity instanceof Item ) {
			$this->getResultBuilder()->addSiteLinks( $entity->getSimpleSiteLinks(), 'entity' );
		}

		$this->getResultBuilder()->addClaims( $entity->getClaims(), 'entity' );
	}

	/**
	 * @param array $params
	 */
	private function validateDataParameter( $params ) {
		if ( !isset( $params['data'] ) ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'No data to operate upon', 'no-data' );
		}
	}

	/**
	 * @since 0.4
	 * @param array $data
	 * @param WikiPage|bool $page
	 */
	protected function validateDataProperties( $data, $page ) {
		$title = null;
		$revision = null;

		if ( $page ) {
			$title = $page->getTitle();
			$revision = $page->getRevision();
		}

		$allowedProps = array(
			'length',
			'count',
			'touched',
			'pageid',
			'ns',
			'title',
			'lastrevid',
			'labels',
			'descriptions',
			'aliases',
			'sitelinks',
			'claims',
			'datatype'
		);

		$this->checkValidJson( $data, $allowedProps );
		$this->checkPageIdProp( $data, $page );
		$this->checkNamespaceProp( $data, $title );
		$this->checkTitleProp( $data, $title );
		$this->checkRevisionProp( $data, $revision );
	}

	/**
	 * @param $data
	 * @param array $allowedProps
	 */
	protected function checkValidJson( $data, array $allowedProps ) {
		if ( is_null( $data ) ) {
			$this->dieUsage( 'Invalid json: The supplied JSON structure could not be parsed or '
				. 'recreated as a valid structure' , 'invalid-json' );
		}

		// NOTE: json_decode will decode any JS literal or structure, not just objects!
		if ( !is_array( $data ) ) {
			$this->dieUsage( 'Top level structure must be a JSON object', 'not-recognized-array' );
		}

		foreach ( $data as $prop => $args ) {
			if ( !is_string( $prop ) ) { // NOTE: catch json_decode returning an indexed array (list)
				$this->dieUsage( 'Top level structure must be a JSON object, (no keys found)', 'not-recognized-string' );
			}

			if ( !in_array( $prop, $allowedProps ) ) {
				$this->dieUsage( "Unknown key in json: $prop", 'not-recognized' );
			}
		}
	}

	/**
	 * @param $data
	 * @param WikiPage|mixed(?) $page
	 */
	protected function checkPageIdProp( $data, $page ) {
		if ( isset( $data['pageid'] )
			&& ( is_object( $page ) ? $page->getId() !== $data['pageid'] : true ) ) {
			$this->dieUsage( 'Illegal field used in call, "pageid", must either be correct or not given', 'param-illegal' );
		}
	}

	/**
	 * @param $data
	 * @param Title|null $title
	 */
	protected function checkNamespaceProp( $data, $title ) {
		// not completely convinced that we can use title to get the namespace in this case
		if ( isset( $data['ns'] )
			&& ( is_object( $title ) ? $title->getNamespace() !== $data['ns'] : true ) ) {
			$this->dieUsage( 'Illegal field used in call: "namespace", must either be correct or not given', 'param-illegal' );
		}
	}

	/**
	 * @param $data
	 * @param Title|null $title
	 */
	protected function checkTitleProp( $data, $title ) {
		if ( isset( $data['title'] )
			&& ( is_object( $title ) ? $title->getPrefixedText() !== $data['title'] : true ) ) {
			$this->dieUsage( 'Illegal field used in call: "title", must either be correct or not given', 'param-illegal' );
		}
	}

	/**
	 * @param $data
	 * @param Revision|null $revision
	 */
	protected function checkRevisionProp( $data, $revision ) {
		if ( isset( $data['lastrevid'] )
			&& ( is_object( $revision ) ? $revision->getId() !== $data['lastrevid'] : true ) ) {
			$this->dieUsage( 'Illegal field used in call: "lastrevid", must either be correct or not given', 'param-illegal' );
		}
	}

	/**
	 * @see \ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'no-such-entity', 'info' => $this->msg( 'wikibase-api-no-such-entity' )->text() ),
			array( 'code' => 'no-such-entity-type', 'info' => $this->msg( 'wikibase-api-no-such-entity-type' )->text() ),
			array( 'code' => 'no-data', 'info' => $this->msg( 'wikibase-api-no-data' )->text() ),
			array( 'code' => 'not-recognized', 'info' => $this->msg( 'wikibase-api-not-recognized' )->text() ),
			array( 'code' => 'not-recognized-array', 'info' => $this->msg( 'wikibase-api-not-recognized-array' )->text() ),
			array( 'code' => 'no-such-site', 'info' => $this->msg( 'wikibase-api-no-such-site' )->text() ),
			array( 'code' => 'no-external-page', 'info' => $this->msg( 'wikibase-api-no-external-page' )->text() ),
			array( 'code' => 'not-item', 'info' => $this->msg( 'wikibase-api-not-item' )->text() ),
			array( 'code' => 'no-such-sitelink', 'info' => $this->msg( 'wikibase-api-no-sitelink' )->text() ),
			array( 'code' => 'invalid-json', 'info' => $this->msg( 'wikibase-api-invalid-json' )->text() ),
			array( 'code' => 'not-recognized-string', 'info' => $this->msg( 'wikibase-api-not-recognized-string' )->text() ),
			array( 'code' => 'param-illegal', 'info' => $this->msg( 'wikibase-api-param-illegal' )->text() ),
			array( 'code' => 'param-missing', 'info' => $this->msg( 'wikibase-api-param-missing' )->text() ),
			array( 'code' => 'inconsistent-language', 'info' => $this->msg( 'wikibase-api-inconsistent-language' )->text() ),
			array( 'code' => 'not-recognised-language', 'info' => $this->msg( 'wikibase-not-recognised-language' )->text() ),
			array( 'code' => 'inconsistent-site', 'info' => $this->msg( 'wikibase-api-inconsistent-site' )->text() ),
			array( 'code' => 'not-recognized-site', 'info' => $this->msg( 'wikibase-api-not-recognized-site' )->text() ),
			array( 'code' => 'failed-save', 'info' => $this->msg( 'wikibase-api-failed-save' )->text() ),
		) );
	}

	/**
	 * @see \ApiBase::getAllowedParams()
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			parent::getAllowedParamsForId(),
			parent::getAllowedParamsForSiteLink(),
			parent::getAllowedParamsForEntity(),
			array(
				'data' => array(
					ApiBase::PARAM_TYPE => 'string',
				),
				'clear' => array(
					ApiBase::PARAM_TYPE => 'boolean',
					ApiBase::PARAM_DFLT => false
				),
				'new' => array(
					ApiBase::PARAM_TYPE => 'string',
				),
			)
		);
	}

	/**
	 * @see \ApiBase::getParamDescription()
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			parent::getParamDescriptionForId(),
			parent::getParamDescriptionForSiteLink(),
			parent::getParamDescriptionForEntity(),
			array(
				'data' => array( 'The serialized object that is used as the data source.',
					"A newly created entity will be assigned an 'id'."
				),
				'clear' => array( 'If set, the complete entity is emptied before proceeding.',
					'The entity will not be saved before it is filled with the "data", possibly with parts excluded.'
				),
				'new' => array( "If set, a new entity will be created.",
					"Set this to the type of the entity you want to create - currently 'item'|'property'.",
					"It is not allowed to have this set when 'id' is also set."
				),
			)
		);
	}

	/**
	 * @see \ApiBase::getDescription()
	 */
	public function getDescription() {
		return array(
			'API module to create a single new Wikibase entity and modify it with serialised information.'
		);
	}

	/**
	 * @see \ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbeditentity&new=item&data={}'
			=> 'Create a new empty item, returns extended with the item structure',
			'api.php?action=wbeditentity&clear=true&id=Q42&data={}'
			=> 'Clear item with id Q42',
			'api.php?action=wbeditentity&new=item&data={"labels":{"de":{"language":"de","value":"de-value"},"en":{"language":"en","value":"en-value"}}}'
			=> 'Create a new item and set labels for de and en',
			'api.php?action=wbeditentity&new=property&data={"labels":{"en-gb":{"language":"en-gb","value":"Propertylabel"}},"descriptions":{"en-gb":{"language":"en-gb","value":"Propertydescription"}},"datatype":"string"}'
			=> 'Create a new property containing the json data, returns extended with the item structure',
			'api.php?action=wbeditentity&id=Q42&data={"sitelinks":{"nowiki":{"site":"nowiki","title":"København"}}}'
			=> 'Sets sitelink for nowiki, overwriting it if it already exists',
			'api.php?action=wbeditentity&id=Q42&data={"claims":[{"mainsnak":{"snaktype":"value","property":"P56","datavalue":{"value":"ExampleString","type":"string"}},"type":"statement","rank":"normal"}]}'
			=> 'Creates a new claim on the item for the property P56 and a value of "ExampleString"',
			'api.php?action=wbeditentity&id=Q42&data={"claims":[{"id":"Q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F","remove":""},{"id":"Q42$GH678DSA-01PQ-28XC-HJ90-DDFD9990126X","remove":""}]}'
			=> 'Removes the claims from the item with the guids q42$D8404CDA-25E4-4334-AF13-A3290BCD9C0F and q42$GH678DSA-01PQ-28XC-HJ90-DDFD9990126X',
			'api.php?action=wbeditentity&id=Q42&data={"claims":[{"id":"Q42$GH678DSA-01PQ-28XC-HJ90-DDFD9990126X","mainsnak":{"snaktype":"value","property":"P56","datavalue":{"value":"ChangedString","type":"string"}},"type":"statement","rank":"normal"}]}'
			=> 'Sets the claim with the GUID to the value of the claim',
		);
	}

	/**
	 * Check some of the supplied data for multilang arg
	 * @param array $arg The argument array to verify
	 * @param string $langCode The language code used in the value part
	 */
	public function validateMultilangArgs( $arg, $langCode ) {
		if ( !is_array( $arg ) ) {
			$this->dieUsage(
				"An array was expected, but not found in the json for the langCode {$langCode}" ,
				'not-recognized-array' );
		}
		if ( !is_string( $arg['language'] ) ) {
			$this->dieUsage(
				"A string was expected, but not found in the json for the langCode {$langCode} and argument 'language'" ,
				'not-recognized-string' );
		}
		if ( !is_numeric( $langCode ) ) {
			if ( $langCode !== $arg['language'] ) {
				$this->dieUsage(
					"inconsistent language: {$langCode} is not equal to {$arg['language']}",
					'inconsistent-language' );
			}
		}
		if ( isset( $this->validLanguageCodes ) && !array_key_exists( $arg['language'], $this->validLanguageCodes ) ) {
			$this->dieUsage(
				"unknown language: {$arg['language']}",
				'not-recognized-language' );
		}
		if ( !is_string( $arg['value'] ) ) {
			$this->dieUsage(
				"A string was expected, but not found in the json for the langCode {$langCode} and argument 'value'" ,
				'not-recognized-string' );
		}
	}

	/**
	 * Check some of the supplied data for sitelink arg
	 *
	 * @param $arg Array: The argument array to verify
	 * @param $siteCode string: The site code used in the argument
	 * @param &$sites \SiteList: The valid site codes as an assoc array
	 */
	public function checkSiteLinks( $arg, $siteCode, SiteList &$sites = null ) {
		if ( !is_array( $arg ) ) {
			$this->dieUsage( 'An array was expected, but not found' , 'not-recognized-array' );
		}
		if ( !is_string( $arg['site'] ) ) {
			$this->dieUsage( 'A string was expected, but not found' , 'not-recognized-string' );
		}
		if ( !is_numeric( $siteCode ) ) {
			if ( $siteCode !== $arg['site'] ) {
				$this->dieUsage( "inconsistent site: {$siteCode} is not equal to {$arg['site']}", 'inconsistent-site' );
			}
		}
		if ( isset( $sites ) && !$sites->hasSite( $arg['site'] ) ) {
			$this->dieUsage( "unknown site: {$arg['site']}", 'not-recognized-site' );
		}
		if ( isset( $arg['title'] ) && !is_string( $arg['title'] ) ) {
			$this->dieUsage( 'A string was expected, but not found' , 'not-recognized-string' );
		}
		if ( isset( $arg['badges'] ) ) {
			if ( !is_array( $arg['badges'] ) ) {
				$this->dieUsage( 'Badges: an array was expected, but not found' , 'not-recognized-array' );
			} else {
				foreach ( $arg['badges'] as $badge ) {
					if ( !is_string( $badge ) ) {
						$this->dieUsage( 'Badges: a string was expected, but not found' , 'not-recognized-string' );
					}
				}
			}
		}
	}

}
