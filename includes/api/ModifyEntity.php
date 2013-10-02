<?php

namespace Wikibase\Api;

use Status, User, Title;
use ApiBase;
use Wikibase\EntityContent;
use Wikibase\EntityContentFactory;
use Wikibase\ItemHandler;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\StringNormalizer;
use Wikibase\Summary;
use Wikibase\Utils;

/**
 * Base class for API modules modifying a single entity identified based on id xor a combination of site and page title.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
abstract class ModifyEntity extends ApiWikibase {

	/**
	 * @var StringNormalizer
	 */
	protected $stringNormalizer;

	public function __construct( \ApiMain $main, $name, $prefix = '' ) {
		parent::__construct( $main, $name, $prefix );

		$this->stringNormalizer = WikibaseRepo::getDefaultInstance()->getStringNormalizer();
	}

	/**
	 * Flags to pass to EditEntity::attemptSave; use with the EDIT_XXX constants.
	 *
	 * @see \EditEntity::attemptSave
	 * @see \WikiPage::doEditContent
	 *
	 * @var integer $flags
	 */
	protected $flags;

	/**
	 * @see  Wikibase\Api\ApiWikibase::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( EntityContent $entityContent, array $params ) {
		$permissions = parent::getRequiredPermissions( $entityContent, $params );

		$permissions[] = 'edit';
		return $permissions;
	}

	/**
	 * Get the entity.
	 *
	 * @since 0.1
	 *
	 * @param array $params
	 *
	 * @return \Wikibase\EntityContent Found existing entity
	 */
	protected function getEntityContent( array $params ) {
		$entityContent = null;

		// If we have an id try that first. If the id isn't prefixed, assume it refers to an item.
		if ( isset( $params['id'] ) ) {
			$id = $params['id'];

			$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

			try{
				$entityId = WikibaseRepo::getDefaultInstance()->getEntityIdParser()->parse( $id );
			} catch( \ValueParsers\ParseException $e ){
				$this->dieUsage( "Could not parse {$id}, No entity found", 'no-such-entity-id' );
			}

			$entityTitle = $entityId ? $entityContentFactory->getTitleForId( $entityId, \Revision::FOR_THIS_USER ) : null;
			if ( is_null( $entityTitle ) ) {
				$this->dieUsage( "No entity found matching ID $id", 'no-such-entity-id' );
			}

		}
		// Otherwise check if we have a link and try that.
		// This will always result in an item, because only items have sitelinks.
		elseif ( isset( $params['site'] ) && isset( $params['title'] ) ) {
			$itemHandler = new ItemHandler();

			$entityTitle = $itemHandler->getTitleFromSiteLink(
				$params['site'],
				$this->stringNormalizer->trimToNFC( $params['title'] )
			);

			if ( is_null( $entityTitle ) ) {
				$this->dieUsage( 'No entity found matching site link ' . $params['site'] . ':' . $params['title'] , 'no-such-entity-link' );
			}
		} else {
			return null;
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$entityContent = $this->loadEntityContent( $entityTitle, $baseRevisionId );

		if ( is_null( $entityContent ) ) {
			$this->dieUsage( "Can't access item content of " . $entityTitle->getPrefixedDBkey() . ", revision may have been deleted.", 'no-such-entity' );
		}

		return $entityContent;
	}

	/**
	 * Create the entity.
	 *
	 * @since    0.1
	 *
	 * @param array       $params
	 *
	 * @internal param \Wikibase\EntityContent $entityContent
	 * @return \Wikibase\EntityContent Newly created entity
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
	 * @param EntityContent $entity
	 * @param array       $params
	 *
	 * @internal param \Wikibase\EntityContent $entityContent
	 * @return Summary|null a summary of the modification, or null to indicate failure.
	 */
	protected abstract function modifyEntity( EntityContent &$entity, array $params );

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
	 * @see \ApiBase::execute()
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
		$entityContent = $this->getEntityContent( $params );
		if ( is_null( $entityContent ) ) {
			$entityContent = $this->createEntity( $params );
		}

		// At this point only change/edit rights should be checked
		$status = $this->checkPermissions( $entityContent, $user, $params );

		if ( !$status->isOK() ) {
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'You do not have sufficient permissions' , 'permissiondenied' );
		}

		$summary = $this->modifyEntity( $entityContent, $params );

		if ( !$summary ) {
			//XXX: This could rather be used for "silent" failure, i.e. in cases where
			//     there was simply nothing to do.
			wfProfileOut( __METHOD__ );
			$this->dieUsage( 'Attempted modification of the item failed' , 'failed-modify' );
		}

		if ( $summary === true ) { // B/C, for implementations of modifyEntity that return true on success.
			$summary = new Summary( $this->getModuleName() );
		}

		$this->addFlags( $entityContent->isNew() );

		//NOTE: EDIT_NEW will not be set automatically. If the entity doesn't exist, and EDIT_NEW was
		//      not added to $this->flags explicitly, the save will fail.

		// collect information and create an EditEntity
		$status = $this->attemptSaveEntity(
			$entityContent,
			$summary->toString(),
			$this->flags
		);

		$this->addToOutput( $entityContent, $status );

		wfProfileOut( __METHOD__ );
	}

	protected function addFlags( $entityContentIsNew ) {
		// if the entity is not up for creation, set the EDIT_UPDATE flags
		if ( !$entityContentIsNew && ( $this->flags & EDIT_NEW ) === 0 ) {
			$this->flags |= EDIT_UPDATE;
		}

		$params = $this->extractRequestParams();
		$this->flags |= ( $this->getUser()->isAllowed( 'bot' ) && $params['bot'] ) ? EDIT_FORCE_BOT : 0;
	}

	protected function addToOutput( EntityContent $entityContent, Status $status ) {
		$formatter = WikibaseRepo::getDefaultInstance()->getEntityIdFormatter();

		$this->getResult()->addValue(
			'entity',
			'id',
			$formatter->format( $entityContent->getEntity()->getId() )
		);

		$this->getResult()->addValue(
			'entity',
			'type', $entityContent->getEntity()->getType()
		);

		$this->addRevisionIdFromStatusToResult( 'entity', 'lastrevid', $status );

		$params = $this->extractRequestParams();

		if ( isset( $params['site'] ) && isset( $params['title'] ) ) {
			$this->addNormalizationInfoToOutput( $params['title'] );
		}

		$this->getResult()->addValue(
			null,
			'success',
			1
		);
	}

	protected function addNormalizationInfoToOutput( $title ) {
		$normalized = array();

		$normTitle = $this->stringNormalizer->trimToNFC( $title );
		if ( $normTitle !== $title ) {
			$normalized['from'] = $title;
			$normalized['to'] = $normTitle;
		}

		if ( $normalized !== array() ) {
			$this->getResult()->addValue(
				'entity',
				'normalized', $normalized
			);
		}
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
	 * @see \ApiBase::isWriteMode()
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
		return array(
			'site' => array(
				ApiBase::PARAM_TYPE => $this->getSiteLinkTargetSites()->getGlobalIdentifiers(),
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
				//'Later it can be implemented a mechanism where a token can be returned spontaneously',
				//'and the requester should then start using the new token from the next request, possibly when',
				//'repeating a failed request.'
			),
			'bot' => array( 'Mark this edit as bot',
				'This URL flag will only be respected if the user belongs to the group "bot".'
			),
		);
	}

}
