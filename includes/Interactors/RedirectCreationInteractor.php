<?php

namespace Wikibase\Repo\Interactors;

use User;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\EntityPermissionChecker;
use Wikibase\Lib\Store\EntityRedirect;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\UnresolvedRedirectException;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * An interactor implementing the use case of creating a redirect.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class RedirectCreationInteractor {

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var EntityStore
	 */
	private $entityStore;

	/**
	 * @var EntityPermissionChecker
	 */
	private $permissionChecker;

	/**
	 * @var SummaryFormatter
	 */
	private $summaryFormatter;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param EntityStore $entityStore
	 * @param EntityPermissionChecker $permissionChecker
	 * @param SummaryFormatter $summaryFormatter
	 * @param User $user
	 */
	public function __construct(
		EntityRevisionLookup $entityRevisionLookup,
		EntityStore $entityStore,
		EntityPermissionChecker $permissionChecker,
		SummaryFormatter $summaryFormatter,
		User $user
	) {
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->entityStore = $entityStore;
		$this->permissionChecker = $permissionChecker;
		$this->summaryFormatter = $summaryFormatter;
		$this->user = $user;
	}

	/**
	 * Create a redirect at $fromId pointing to $toId.
	 *
	 * @param EntityId $fromId The ID of the entity to be replaced by the redirect. The entity
	 * must exist and be empty (or be a redirect already).
	 * @param EntityId $toId The ID of the entity the redirect should point to. The Entity must
	 * exist and must not be a redirect.
	 *
	 * @return EntityRedirect
	 *
	 * @throws RedirectCreationException If creating the redirect fails. Calling code may use
	 * RedirectCreationException::getErrorCode() to get further information about the cause of
	 * the failure. An explanation of the error codes can be obtained from getErrorCodeInfo().
	 */
	public function createRedirect( EntityId $fromId, EntityId $toId ) {
		wfProfileIn( __METHOD__ );

		$this->checkCompatible( $fromId, $toId );
		$this->checkPermissions( $fromId );

		$this->checkExists( $toId );
		$this->checkEmpty( $fromId );

		$summary = new Summary( 'wbcreateredirect' );
		$summary->addAutoSummaryArgs( $fromId, $toId );

		$redirect = new EntityRedirect( $fromId, $toId );
		$this->saveRedirect( $redirect, $summary );

		wfProfileOut( __METHOD__ );

		return $redirect;
	}

	private function checkPermissions( EntityId $entityId ) {
		$status = $this->permissionChecker->getPermissionStatusForEntityId( $this->user, 'edit', $entityId );

		if ( !$status->isOK() ) {
			// XXX: This is silly, we really want to pass the Status object to the API error handler.
			// Perhaps we should get rid of RedirectCreationException and use Status throughout.
			throw new RedirectCreationException( $status->getWikiText(), 'permissiondenied' );
		}
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkEmpty( EntityId $entityId ) {
		try {
			$revision = $this->entityRevisionLookup->getEntityRevision( $entityId );

			if ( !$revision ) {
				throw new RedirectCreationException(
					"Entity $entityId not found",
					'no-such-entity'
				);
			}

			$entity = $revision->getEntity();

			if ( !$entity->isEmpty() ) {
				throw new RedirectCreationException(
					"Entity $entityId is not empty",
					'target-not-empty'
				);
			}
		} catch ( UnresolvedRedirectException $ex ) {
			// Nothing to do. It's ok to override a redirect with a redirect.
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException( $ex->getMessage(), 'cant-load-entity-content', $ex );
		}
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkExists( EntityId $entityId ) {
		try {
			$revision = $this->entityRevisionLookup->getLatestRevisionId( $entityId );

			if ( !$revision ) {
				throw new RedirectCreationException(
					"Entity $entityId not found",
					'no-such-entity'
				);
			}
		} catch ( UnresolvedRedirectException $ex ) {
			throw new RedirectCreationException( $ex->getMessage(), 'target-is-redirect', $ex );
		}
	}

	/**
	 * @param EntityId $fromId
	 * @param EntityId $toId
	 *
	 * @throws RedirectCreationException
	 */
	private function checkCompatible( EntityId $fromId, EntityId $toId ) {
		if ( $fromId->getEntityType() !== $toId->getEntityType() ) {
			throw new RedirectCreationException(
				'Incompatible entity types',
				'target-is-incompatible'
			);
		}
	}

	/**
	 * @param EntityRedirect $redirect
	 * @param Summary $summary
	 *
	 * @throws RedirectCreationException
	 */
	private function saveRedirect( EntityRedirect $redirect, Summary $summary ) {
		try {
			$this->entityStore->saveRedirect(
				$redirect,
				$this->summaryFormatter->formatSummary( $summary ),
				$this->user,
				EDIT_UPDATE
			);
		} catch ( StorageException $ex ) {
			throw new RedirectCreationException( $ex->getMessage(), 'cant-redirect', $ex );
		}
	}

	/**
	 * Returns information about the error codes used with RedirectCreationException by this class.
	 *
	 * @return string[] a map of error codes as returned by RedirectCreationException::getErrorCode()
	 * to a human readable explanation (in English).
	 *
	 * @see CreateRedirectException::getErrorCode()
	 * @see ApiMain::getPossibleErrors
	 */
	public function getErrorCodeInfo() {
		return array(
			'invalid-entity-id'=> 'Invalid entity ID',
			'target-not-empty'=> 'The entity that is to be turned into a redirect is not empty',
			'no-such-entity'=> 'Entity not found',
			'target-is-redirect'=> 'The redirect target is itself a redirect',
			'target-is-incompatible'=> 'The redirect target is incompatible (e.g. a different type of entity)',
			'cant-redirect'=> 'Can\'t create the redirect (e.g. the given type of entity does not support redirects)',
		);
	}

}
