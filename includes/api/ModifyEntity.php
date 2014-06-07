<?php

namespace Wikibase\Api;

use ApiBase;
use ApiMain;
use LogicException;
use Status;
use UsageException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\ChangeOpValidationException;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EntityRevision;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\StringNormalizer;
use Wikibase\Summary;

/**
 * Base class for API modules modifying a single entity identified based on id xor a combination of site and page title.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 * @author Michał Łazowik
 */
abstract class ModifyEntity extends ApiWikibase {

	/**
	 * @var StringNormalizer
	 */
	protected $stringNormalizer;

	/**
	 * @var SiteLinkTargetProvider
	 */
	protected $siteLinkTargetProvider;

	/**
	 * @var SiteLinkLookup
	 */
	protected $siteLinkLookup;

	/**
	 * @since 0.5
	 *
	 * @var array
	 */
	protected $siteLinkGroups;

	/**
	 * @var string[]
	 */
	protected $badgeItems;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 *
	 * @see ApiBase::__construct
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$repo = WikibaseRepo::getDefaultInstance();

		//TODO: provide a mechanism to override the services
		$this->stringNormalizer = $repo->getStringNormalizer();
		$this->siteLinkTargetProvider = new SiteLinkTargetProvider( $repo->getSiteStore() );

		$this->siteLinkGroups = $repo->getSettings()->getSetting( 'siteLinkGroups' );
		$this->siteLinkLookup = $repo->getStore()->newSiteLinkCache();
		$this->badgeItems = $repo->getSettings()->getSetting( 'badgeItems' );
	}

	/**
	 * Flags to pass to EditEntity::attemptSave; use with the EDIT_XXX constants.
	 *
	 * @see EditEntity::attemptSave
	 * @see WikiPage::doEditContent
	 *
	 * @var integer $flags
	 */
	protected $flags;

	/**
	 * Get the entity using the id, site and title params passed to the api
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 *
	 * @return EntityRevision Found existing entity
	 */
	protected function getEntityRevisionFromApiParams( array $params ) {
		if ( isset( $params['id'] ) ) {
			$id = $params['id'];
			$entityId = $this->getEntityIdFromString( $id );
		} elseif ( isset( $params['site'] ) && isset( $params['title'] ) ) {
			$entityId = $this->getEntityIdFromSiteTitleCombination( $params['site'],  $params['title'] );
		} else {
			//Things that use this method assume null means we want a new entity
			return null;
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : 0;
		$entityRevision = $this->entityLookup->getEntityRevision( $entityId, $baseRevisionId );

		if ( is_null( $entityRevision ) ) {
			$this->dieUsage( "Can't access entity " . $entityId->getSerialization() . ", revision may have been deleted.", 'no-such-entity' );
		}

		return $entityRevision;
	}

	/**
	 * Returns an EntityId object based on the given $id,
	 * or throws a usage exception if the ID is invalid.
	 *
	 * @param string $id
	 *
	 * @throws UsageException
	 * @throws LogicException
	 * @return EntityId
	 */
	protected function getEntityIdFromString( $id ) {
		try {
			return $this->idParser->parse( $id );
		} catch ( EntityIdParsingException $ex ) {
			$this->dieUsage( $ex->getMessage(), 'no-such-entity-id' );
		}

		throw new LogicException( 'ApiBase::dieUsage did not throw a UsageException' );
	}

	/**
	 * Returns the ID of the entity connected to $title on $site, or
	 * throws a usage exception if no such entity is found.
	 *
	 * @param string $site
	 * @param string $title
	 * @return EntityId
	 */
	protected function getEntityIdFromSiteTitleCombination( $site, $title ) {
		$itemId = $this->siteLinkLookup->getItemIdForLink( $site, $title );

		if ( $itemId === null ) {
			$this->dieUsage( 'No entity found matching site link ' . $site . ':' . $title , 'no-such-entity-link' );
		}

		return $itemId;
	}

	/**
	 * Validates badges from params and turns them into an array of ItemIds.
	 *
	 * @param array $badgesParams
	 *
	 * @return ItemId[]
	 */
	protected function parseSiteLinkBadges( array $badgesParams ) {
		$badges = array();

		foreach ( $badgesParams as $badgeSerialization ) {
			try {
				$badgeId = $this->idParser->parse( $badgeSerialization );
			} catch( EntityIdParsingException $e ) {
				$this->dieUsage( "Badges: could not parse '{$badgeSerialization}', the id is invalid", 'no-such-entity-id' );
				$badgeId = null;
			}

			if ( !( $badgeId instanceof ItemId ) ) {
				$this->dieUsage( "Badges: entity with id '{$badgeSerialization}' is not an item", 'not-item' );
			}

			if ( !array_key_exists( $badgeId->getPrefixedId(), $this->badgeItems ) ) {
				$this->dieUsage( "Badges: item '{$badgeSerialization}' is not a badge", 'not-badge' );
			}

			$itemTitle = $this->titleLookup->getTitleForId( $badgeId );

			if ( is_null( $itemTitle ) || !$itemTitle->exists() ) {
				$this->dieUsage( "Badges: no item found matching id '{$badgeSerialization}'", 'no-such-entity' );
			}

			$badges[] = $badgeId;
		}

		return $badges;
	}

	/**
	 * Create the entity.
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 * @return Entity Newly created entity
	 */
	protected function createEntity( array $params ) {
		$this->dieUsage( 'Could not find an existing entity' , 'no-such-entity' );
	}

	/**
	 * Create a new Summary instance suitable for representing the action performed by this module.
	 *
	 * @param array $params
	 *
	 * @return Summary
	 */
	protected function createSummary( array $params ) {
		$summary = new Summary( $this->getModuleName() );
		if ( !is_null( $params['summary'] ) ) {
			$summary->setUserSummary( $params['summary'] );
		}
		return $summary;
	}

	/**
	 * Actually modify the entity.
	 *
	 * @since 0.1
	 *
	 * @param Entity $entity
	 * @param array $params
	 * @param int $baseRevId
	 *
	 * @return Summary|null a summary of the modification, or null to indicate failure.
	 */
	protected abstract function modifyEntity( Entity &$entity, array $params, $baseRevId );

	/**
	 * Applies the given ChangeOp to the given Entity.
	 * Any ChangeOpException is converted into a UsageException with the code 'modification-failed'.
	 *
	 * @since 0.5
	 *
	 * @param ChangeOp $changeOp
	 * @param Entity $entity
	 * @param Summary $summary The summary object to update with information about the change.
	 *
	 * @throws UsageException
	 */
	protected function applyChangeOp( ChangeOp $changeOp, Entity $entity, Summary $summary = null ) {
		try {
			$result = $changeOp->validate( $entity );

			if ( !$result->isValid() ) {
				throw new ChangeOpValidationException( $result );
			}

			$changeOp->apply( $entity, $summary );
		} catch ( ChangeOpException $ex ) {
			$this->errorReporter->dieException( $ex, 'modification-failed' );
		}
	}

	/**
	 * Make sure the required parameters are provided and that they are valid.
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 */
	protected function validateParameters( array $params ) {
		// note that this is changed back and could fail
		if ( !( isset( $params['id'] ) XOR ( isset( $params['site'] ) && isset( $params['title'] ) ) ) ) {
			$this->dieUsage( 'Either provide the item "id" or pairs of "site" and "title" for a corresponding page' , 'param-illegal' );
		}
	}

	/**
	 * @see ApiBase::execute()
	 *
	 * @since 0.1
	 */
	public function execute() {
		wfProfileIn( __METHOD__ );

		$params = $this->extractRequestParams();
		$user = $this->getUser();
		$this->flags = 0;

		$this->validateParameters( $params );

		// Try to find the entity or fail and create it, or die in the process
		$entityRev = $this->getEntityRevisionFromApiParams( $params );
		if ( is_null( $entityRev ) ) {
			$entity = $this->createEntity( $params );
			$entityRevId = 0;

			// HACK: We need to assign an ID early, for things like the ClaimIdGenerator.
			if ( $entity->getId() === null ) {
				$this->entityStore->assignFreshId( $entity );
			}
		} else {
			$entity = $entityRev->getEntity();
			$entityRevId = $entityRev->getRevision();
		}

		if ( $entity->getId() === null ) {
			throw new \LogicException( 'The Entity should have an ID at this point!' );
		}

		// At this point only change/edit rights should be checked
		$status = $this->checkPermissions( $entity, $user, $params );

		if ( !$status->isOK() ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'You do not have sufficient permissions' , 'permissiondenied' );
		}

		$summary = $this->modifyEntity( $entity, $params, $entityRevId );

		if ( !$summary ) {
			//XXX: This could rather be used for "silent" failure, i.e. in cases where
			//     there was simply nothing to do.
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Attempted modification of the item failed' , 'failed-modify' );
		}

		if ( $summary === true ) { // B/C, for implementations of modifyEntity that return true on success.
			$summary = new Summary( $this->getModuleName() );
		}

		$this->addFlags( $entity->getId() === null );

		//NOTE: EDIT_NEW will not be set automatically. If the entity doesn't exist, and EDIT_NEW was
		//      not added to $this->flags explicitly, the save will fail.
		$status = $this->attemptSaveEntity(
			$entity,
			$summary,
			$this->flags
		);

		$this->addToOutput( $entity, $status );

		wfProfileOut( __METHOD__ );
	}

	protected function addFlags( $entityIsNew ) {
		// if the entity is not up for creation, set the EDIT_UPDATE flags
		if ( !$entityIsNew && ( $this->flags & EDIT_NEW ) === 0 ) {
			$this->flags |= EDIT_UPDATE;
		}

		$params = $this->extractRequestParams();
		$this->flags |= ( $this->getUser()->isAllowed( 'bot' ) && $params['bot'] ) ? EDIT_FORCE_BOT : 0;
	}

	protected function addToOutput( Entity $entity, Status $status ) {
		$this->getResultBuilder()->addBasicEntityInformation( $entity->getId(), 'entity' );
		$this->getResultBuilder()->addRevisionIdFromStatusToResult( $status, 'entity' );

		$params = $this->extractRequestParams();

		if ( isset( $params['site'] ) && isset( $params['title'] ) ) {
			$normTitle = $this->stringNormalizer->trimToNFC( $params['title'] );
			if ( $normTitle !== $params['title'] ) {
				$this->getResultBuilder()->addNormalizedTitle( $params['title'], $normTitle, 'normalized' );
			}
		}

		$this->getResultBuilder()->markSuccess( 1 );
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'no-such-entity-id', 'info' => $this->msg( 'wikibase-api-no-such-entity-id' )->text() ),
			array( 'code' => 'no-such-entity-link', 'info' => $this->msg( 'wikibase-api-no-such-entity-link' )->text() ),
			array( 'code' => 'no-such-entity', 'info' => $this->msg( 'wikibase-api-no-such-entity' )->text() ),
			array( 'code' => 'param-illegal', 'info' => $this->msg( 'wikibase-api-param-illegal' )->text() ),
			array( 'code' => 'permissiondenied', 'info' => $this->msg( 'wikibase-api-permissiondenied' )->text() ),
			array( 'code' => 'failed-modify', 'info' => $this->msg( 'wikibase-api-failed-modify' )->text() ),
		) );
	}

	/**
	 * @see ApiBase::isWriteMode()
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Get allowed params for the identification of the entity
	 * Lookup through an id is common for all entities
	 *
	 * @since 0.1
	 *
	 * @return array the allowed params
	 */
	public function getAllowedParamsForId() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}

	/**
	 * Get allowed params for the identification by a sitelink pair
	 * Lookup through the sitelink object is not used in every subclasses
	 *
	 * @since 0.1
	 *
	 * @return array the allowed params
	 */
	public function getAllowedParamsForSiteLink() {
		$sites = $this->siteLinkTargetProvider->getSiteList( $this->siteLinkGroups );
		return array(
			'site' => array(
				ApiBase::PARAM_TYPE => $sites->getGlobalIdentifiers(),
			),
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}

	/**
	 * Get allowed params for the entity in general
	 *
	 * @since 0.1
	 *
	 * @return array the allowed params
	 */
	public function getAllowedParamsForEntity() {
		return array(
			'baserevid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'summary' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'token' => null,
			'bot' => false,
		);
	}

	/**
	 * Get param descriptions for identification of the entity
	 * Lookup through an id is common for all entities
	 *
	 * @since 0.1
	 *
	 * @return array the param descriptions
	 */
	protected function getParamDescriptionForId() {
		return array(
			'id' => array( 'The identifier for the entity, including the prefix.',
				"Use either 'id' or 'site' and 'title' together."
			),
		);
	}

	/**
	 * Get param descriptions for identification by a sitelink pair
	 * Lookup through the sitelink object is not used in every subclasses
	 *
	 * @since 0.1
	 *
	 * @return array the param descriptions
	 */
	protected function getParamDescriptionForSiteLink() {
		return array(
			'site' => array( 'An identifier for the site on which the page resides.',
				"Use together with 'title' to make a complete sitelink."
			),
			'title' => array( 'Title of the page to associate.',
				"Use together with 'site' to make a complete sitelink."
			),
		);
	}

	/**
	 * Get param descriptions for the entity in general
	 *
	 * @since 0.1
	 *
	 * @return array the param descriptions
	 */
	protected function getParamDescriptionForEntity() {
		return array(
			'baserevid' => array( 'The numeric identifier for the revision to base the modification on.',
				"This is used for detecting conflicts during save."
			),
			'summary' => array( 'Summary for the edit.',
				"Will be prepended by an automatically generated comment. The length limit of the
				autocomment together with the summary is 260 characters. Be aware that everything above that
				limit will be cut off."
			),
			'type' => array( 'A specific type of entity.',
				"Will default to 'item' as this will be the most common type."
			),
			'token' => array( 'A "edittoken" token previously obtained through the token module (prop=info).',
			),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),
		);
	}

}
