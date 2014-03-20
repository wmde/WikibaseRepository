<?php

namespace Wikibase\Api;

use Message;
use MessageCache;
use Revision;
use Title;
use User;
use Status;
use ApiBase;
use Wikibase\EntityContent;
use Wikibase\EntityFactory;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\EditEntity;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;
use WikiPage;

/**
 * Base class for API modules
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Adam Shorland
 */
abstract class ApiWikibase extends \ApiBase {

	private $resultBuilder;

	/**
	 * Wrapper message for single errors
	 *
	 * @var bool|string
	 */
	protected static $shortErrorContextMessage = false;

	/**
	 * Wrapper message for multiple errors
	 *
	 * @var bool|string
	 */
	protected static $longErrorContextMessage = false;

	/**
	 * @return ResultBuilder
	 */
	public function getResultBuilder() {
		if( !isset( $this->resultBuilder ) ) {

			$entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();

			$serializerFactory = new SerializerFactory(
				null,
				WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup(),
				EntityFactory::singleton()
			);

			$this->resultBuilder = new ResultBuilder(
				$this->getResult(),
				$entityTitleLookup,
				$serializerFactory
			);
		}
		return $this->resultBuilder;
	}

	/**
	 * @see ApiBase::getPossibleErrors()
	 */
	public function getPossibleErrors() {
		return array(
			array( 'code' => 'failed-save', 'info' => $this->msg( 'wikibase-api-failed-save' )->text()  ),
			array( 'code' => 'editconflict', 'info' => $this->msg( 'wikibase-api-editconflict' )->text()  ),
			array( 'code' => 'badtoken', 'info' => $this->msg( 'wikibase-api-badtoken' )->text()  ),
			array( 'code' => 'nosuchrevid', 'info' => $this->msg( 'wikibase-api-nosuchrevid' )->text()  ),
			array( 'code' => 'cant-load-entity-content', 'info' => $this->msg( 'wikibase-api-cant-load-entity-content' )->text()  ),
		);
	}

	/**
	 * @see ApiBase::getParamDescription()
	 */
	public function getParamDescription() {
		return array(
		);
	}

	/**
	 * @see ApiBase::getAllowedParams()
	 */
	public function getAllowedParams() {
		return array(
		);
	}

	/**
	 * @see ApiBase::needsToken()
	 */
	public function needsToken() {
		return $this->isWriteMode();
	}

	/**
	 * @see ApiBase::mustBePosted()
	 */
	public function mustBePosted() {
		return $this->isWriteMode();
	}

	/**
	 * @see ApiBase::isReadMode
	 */
	public function isReadMode() {
		return true;
	}

	/**
	 * @see ApiBase::getVersion
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getVersion() {
		return get_class( $this ) . '-' . WB_VERSION;
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#' . $this->getModuleName();
	}

	/**
	 * Returns the permissions that are required to perform the operation specified by
	 * the parameters.
	 *
	 * @param EntityContent $entityContent the entityContent to check permissions for
	 * @param $params array of arguments for the module, describing the operation to be performed
	 *
	 * @return Status the check's result
	 */
	protected function getRequiredPermissions( EntityContent $entityContent, array $params ) {
		return array();
	}

	/**
	 * Check the rights for the user accessing the module.
	 *
	 * @param $entityContent EntityContent the entity to check
	 * @param $user User doing the action
	 * @param $params array of arguments for the module, passed for ModifyItem
	 *
	 * @return Status the check's result
	 * @todo: use this also to check for read access in ApiGetEntities, etc
	 */
	public function checkPermissions( EntityContent $entityContent, User $user, array $params ) {
		$permissions = $this->getRequiredPermissions( $entityContent, $params );
		$status = Status::newGood();

		foreach ( $permissions as $perm ) {
			$permStatus = $entityContent->checkPermission( $perm, $user, true );
			$status->merge( $permStatus );
		}

		return $status;
	}

	/**
	 * Load the entity content of the given revision.
	 *
	 * Will fail by calling dieUsage() if the revision can not be found or can not be loaded.
	 *
	 * @since 0.3
	 *
	 * @param Title   $title   : the title of the page to load the revision for
	 * @param bool|int $revId   : the revision to load. If not given, the current revision will be loaded.
	 * @param int      $audience
	 * @param User $user
	 * @param int      $audience: the audience to load this for, see Revision::FOR_XXX constants and
	 *                          Revision::getContent().
	 * @param User $user    : the user to consider if $audience == Revision::FOR_THIS_USER
	 *
	 * @return EntityContent the revision's content.
	 */
	protected function loadEntityContent(
		Title $title,
		$revId = false,
		$audience = Revision::FOR_PUBLIC,
		User $user = null
	) {
		if ( $revId === null || $revId === false || $revId === 0 ) {
			$page = WikiPage::factory( $title );
			$content = $page->getContent( $audience, $user );
		} else {
			$revision = Revision::newFromId( $revId );

			if ( !$revision ) {
				$this->dieUsage( "Revision not found: $revId", 'nosuchrevid' );
			}

			if ( $revision->getPage() != $title->getArticleID() ) {
				$this->dieUsage( "Revision $revId does not belong to " .
					$title->getPrefixedDBkey(), 'nosuchrevid' );
			}

			$content = $revision->getContent( $audience, $user );
		}

		if ( is_null( $content ) ) {
			$this->dieUsage( "Can't access item content of " .
				$title->getPrefixedDBkey() .
				", revision may have been deleted.", 'cant-load-entity-content' );
		}

		return $content;
	}

	/**
	 * Signal errors and warnings from a save operation to the API call's output.
	 * This is much like handleStatus(), but specialized for Status objects returned by
	 * EditEntity::attemptSave(). In particular, the 'errorFlags' and 'errorCode' fields
	 * from the status value are used to determine the error code to return to the caller.
	 *
	 * @note: this function may or may not return normally, depending on whether
	 *        the status is fatal or not.
	 *
	 * @see handleStatus().
	 *
	 * @param Status $status The status to report
	 */
	protected function handleSaveStatus( Status $status ) {
		$value = $status->getValue();
		$errorCode = null;

		if ( is_array( $value ) && isset( $value['errorCode'] ) ) {
			$errorCode = $value['errorCode'];
		} else {
			$editError = 0;

			if ( is_array( $value ) && isset( $value['errorFlags'] ) ) {
				$editError = $value['errorFlags'];
			}

			if ( ( $editError & EditEntity::TOKEN_ERROR ) > 0 ) {
				$errorCode = 'badtoken';
			} elseif ( ( $editError & EditEntity::EDIT_CONFLICT_ERROR ) > 0 ) {
				$errorCode = 'editconflict';
			} elseif ( ( $editError & EditEntity::ANY_ERROR ) > 0 ) {
				$errorCode = 'failed-save';
			}
		}

		//NOTE: will just add warnings or do nothing if there's no error
		$this->handleStatus( $status, $errorCode );
	}

	/**
	 * Include messages from a Status object in the API call's output.
	 *
	 * If $status->isOK() returns false, this method will terminate via the a call
	 * to $this->dieUsage().
	 *
	 * If $status->isOK() returns false, any errors from the $status object will be included
	 * in the error-section of the API response. Otherwise, any warnings from $status will be
	 * included in the warnings-section of the API response. In both cases, a messages-section
	 * is used that includes an HTML representation of the messages as well as a list of message
	 * keys and parameters, for client side rendering and localization.
	 *
	 * @param Status $status The status to report
	 * @param string  $errorCode The API error code to use in case $status->isOK() returns false
	 * @param array   $extradata Additional data to include the the error report,
	 *                if $status->isOK() returns false
	 * @param int     $httpRespCode the HTTP response code to use in case
	 *                $status->isOK() returns false.
	 *
	 * @warning This is a temporary solution, pending a similar feature in MediaWiki core,
	 *          see bug 45843.
	 *
	 * @see ApiBase::dieUsage()
	 */
	public function handleStatus( Status $status, $errorCode, array $extradata = array(), $httpRespCode = 0 ) {
		wfProfileIn( __METHOD__ );

		$res = $this->getResult();
		$isError = !$status->isOK();

		// report all warnings and errors
		if ( $status->isGood() ) {
			$description = null;
		} else {
			$description = $status->getWikiText( self::$shortErrorContextMessage, self::$longErrorContextMessage );
		}

		$errors = $status->getErrorsByType( $isError ? 'error' : 'warning' );
		$messages = $this->compileStatusReport( $errors );

		if ( $messages ) {
			//NOTE: Status::getHTML() doesn't work, see bug 45844
			//$html = $status->getHTML( self::$shortErrorContextMessage, self::$longErrorContextMessage );

			// nasty workaround:
			$html = $this->messageToHtml( $description );
			$res->setContent( $messages, $html, 'html' );
		}

		if ( $isError ) {
			$res->setElement( $extradata, 'messages', $messages );

			wfProfileOut( __METHOD__ );
			$this->dieUsage( $description, $errorCode, $httpRespCode, $extradata );
		} elseif ( $messages ) {
			$res->disableSizeCheck();
			$res->addValue( array( 'warnings' ), 'messages', $messages, true );
			$res->enableSizeCheck();

			wfProfileOut( __METHOD__ );
		}
	}

	/**
	 * Converts the given wiki text to HTML, using the parser mode for interface messages.
	 *
	 * @note: this is only needed as a temporary workaround for bug 45844
	 *
	 * @param string|null|bool $text The wikitext to parse.
	 *
	 * @return string|null|bool HTML of the wikitext if $text is a string; $text if it's false or null
	 *
	 * @see MessageCache::parse()
	 */
	protected function messageToHtml( $text ) {
		if ( $text === null || $text === false || $text === '' ) {
			return $text;
		}

		$out = MessageCache::singleton()->parse(
			$text,
			$this->getContext()->getTitle(),
			/*linestart*/true,
			/*interface*/true,
			$this->getContext()->getLanguage()
		);

		return $out->getText();
	}

	/**
	 * Utility method for compiling a list of messages into a form suitable for use
	 * in an API result structure.
	 *
	 * The $errors parameters is a list of (error) messages. Each entry in that array
	 * represents on message; the message can be represented as:
	 *
	 * * a message key, as a string
	 * * an indexed array with the message key as the first element, and the remaining elements
	 *   acting as message parameters
	 * * an associative array with the following fields:
	 *   - message: the message key (as a string); may also be a Message object, see below for that.
	 *   - params: a list of parameters (optional)
	 *   - type: the type of message (warning or error) (optional)
	 * * an associative array like above, but containing a Message object in the 'message' field.
	 *   In that case, the 'params' field is ignored and the parameter list is taken from the
	 *   Message object.
	 *
	 * This provides support for message lists coming from Status::getErrorsByType() as well as
	 * Title::getUserPermissionsErrors() etc.
	 *
	 * @param $errors array a list of errors, as returned by Status::getErrorsByType()
	 * @param $messages array an result structure to add to (optional)
	 *
	 * @return array a result structure containing the messages from $errors as well as what
	 *         was already present in the $messages parameter.
	 */
	protected function compileStatusReport( $errors, $messages = array() ) {
		if ( !is_array($errors) || $errors === array() ) {
			return $messages;
		}

		$res = $this->getResult();

		foreach ( $errors as $m ) {
			$type = null;
			$name = null;
			$params = null;

			if ( is_string( $m ) ) {
				// it's a plain string containing a message key
				$name = $m;
			} elseif ( is_array( $m ) ) {
				if ( isset( $m[0]) ) {
					// it's an indexed array, the first entriy is the message key, the rest are paramters
					$name = $m[0];
					$params = array_slice( $m, 1 );
				} else{
					// it's an assoc array, find message key and params in fields.
					$type = isset( $m['type'] ) ? $m['type'] : null;
					$params = isset( $m['params'] ) ? $m['params'] : null;

					if( isset( $m['message'] ) ) {
						if ( $m['message'] instanceof Message ) {
							// message object, handle below
							$m = $m['message']; // NOTE: this triggers the "$m is an object" case below!
						} else {
							// plain key and param list
							$name = strval( $m['message'] );
						}
					}
				}
			}

			if ( $m instanceof Message ) { //NOTE: no elsif, since $m can be manipulated
				// a message object

				$name = $m->getKey();
				$params = $m->getParams();
			}

			if ( $name !== null ) {
				// got at least a name

				$row = array();

				$res->setElement( $row, 'name', $name );

				if ( $type !== null ) {
					$res->setElement( $row, 'type', $type );
				}

				if ( $params !== null && !empty( $params ) ) {
					$res->setElement( $row, 'parameters', $params );
					$res->setIndexedTagName( $row['parameters'], 'parameter' );
				}

				$messages[] = $row;
			}
		}

		$res->setIndexedTagName( $messages, 'message' );
		return $messages;
	}

	/**
	 * Attempts to save the new entity content, chile first checking for permissions,
	 * edit conflicts, etc. Saving is done via EditEntity::attemptSave().
	 *
	 * This method automatically takes into account several parameters:
	 * * 'bot' for setting the bot flag
	 * * 'baserevid' for determining the edit's base revision for conflict resolution
	 * * 'token' for the edit token
	 *
	 * If an error occurs, it is automatically reported and execution of the API module
	 * is terminated using a call to dieUsage() (via handleStatus()). If there were any
	 * warnings, they will automatically be included in the API call's output (again, via
	 * handleStatus()).
	 *
	 * @param EntityContent $content  The content to save
	 * @param string|Summary $summary The edit summary
	 * @param int    $flags           The edit flags (see WikiPage::doEditContent)
	 *
	 * @return Status the status of the save operation, as returned by EditEntity::attemptSave()
	 * @see  EditEntity::attemptSave()
	 *
	 * @todo: this could be factored out into a controller-like class, but that controller
	 *        would still need knowledge of the API module to be useful. We'll put it here
	 *        for now pending further discussion.
	 */
	protected function attemptSaveEntity( EntityContent $content, $summary, $flags = 0 ) {
		if ( $summary instanceof Summary ) {
			$summary = $this->formatSummary( $summary );
		}

		$params = $this->extractRequestParams();
		$user = $this->getUser();

		if ( isset( $params['bot'] ) && $params['bot'] && $user->isAllowed( 'bot' ) ) {
			$flags |= EDIT_FORCE_BOT;
		}

		$baseRevisionId = isset( $params['baserevid'] ) ? intval( $params['baserevid'] ) : null;
		$baseRevisionId = $baseRevisionId > 0 ? $baseRevisionId : false;

		//TODO: allow injection/override!
		$entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();
		$entityRevisionLookup = WikibaseRepo::getDefaultInstance()->getEntityRevisionLookup( 'uncached' );
		$entityStore = WikibaseRepo::getDefaultInstance()->getEntityStore();

		$editEntity = new EditEntity(
			$entityTitleLookup,
			$entityRevisionLookup,
			$entityStore,
			$content->getEntity(), //TODO: refactor API modules to not use EntityContent at all!
			$user,
			$baseRevisionId,
			$this->getContext() );

		if ( !$this->needsToken() ) {
			// false disabled the token check
			$token = false;
		} else {
			// null fails the token check
			$token = isset( $params['token'] ) ? $params['token'] : null;
		}

		$status = $editEntity->attemptSave(
			$summary,
			$flags,
			$token
		);

		$this->handleSaveStatus( $status );
		return $status;
	}

	protected function formatSummary( Summary $summary ) {
		$formatter = WikibaseRepo::getDefaultInstance()->getSummaryFormatter();
		return $formatter->formatSummary( $summary );
	}

}
