<?php

namespace Wikibase;

use ApiBase;
use ApiEditPage;
use Content;
use ContentHandler;
use DatabaseUpdater;
use EditPage;
use HistoryPager;
use Html;
use Language;
use Linker;
use LogEntryBase;
use MWContentSerializationException;
use MWException;
use OutputPage;
use ParserOutput;
use RecentChange;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RequestContext;
use Revision;
use SearchResult;
use Skin;
use SkinTemplate;
use SpecialSearch;
use SplFileInfo;
use Title;
use User;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;

/**
 * File defining the hook handlers for the Wikibase extension.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Nikola Smolenski
 * @author Daniel Werner
 * @author Michał Łazowik
 * @author Jens Ohlig
 */
final class RepoHooks {

	/**
	 * Handler for the BeforePageDisplay hook, simply injects wikibase.ui.entitysearch module
	 * replacing the native search box with the entity selector widget.
	 *
	 * @since 0.4
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return boolean
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( 'wikibase.ui.entitysearch' );
		return true;
	}

	/**
	 * Handler for the SetupAfterCache hook, completing setup of
	 * content and namespace setup.
	 *
	 * @since 0.1
	 *
	 * @note: $wgExtraNamespaces and $wgNamespaceAliases have already been processed at this point
	 *        and should no longer be touched.
	 *
	 * @return boolean
	 * @throws MWException
	 */
	public static function onSetupAfterCache() {
		wfProfileIn( __METHOD__ );
		global $wgNamespaceContentModels;

		$namespaces = Settings::get( 'entityNamespaces' );

		if ( empty( $namespaces ) ) {
			wfProfileOut( __METHOD__ );
			throw new MWException( 'Wikibase: Incomplete configuration: '
				. '$wgWBRepoSettings["entityNamespaces"] has to be set to an '
				. 'array mapping content model IDs to namespace IDs. '
				. 'See ExampleSettings.php for details and examples.');
		}

		foreach ( $namespaces as $model => $ns ) {
			if ( !isset( $wgNamespaceContentModels[$ns] ) ) {
				$wgNamespaceContentModels[$ns] = $model;
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @since 0.1
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return boolean
	 */
	public static function onSchemaUpdate( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();

		if ( $type === 'mysql' || $type === 'sqlite' /* || $type === 'postgres' */ ) {
			$extension = $type === 'postgres' ? '.pg.sql' : '.sql';

			$updater->addExtensionTable(
				'wb_changes',
				__DIR__ . '/sql/changes' . $extension
			);

			if ( $type === 'mysql' && !$updater->updateRowExists( 'ChangeChangeObjectId.sql' ) ) {
				$updater->addExtensionUpdate( array(
					'applyPatch',
					__DIR__ . '/sql/ChangeChangeObjectId.sql',
					true
				) );

				$updater->insertUpdateRow( 'ChangeChangeObjectId.sql' );
			}

			$updater->addExtensionTable(
				'wb_changes_dispatch',
				__DIR__ . '/sql/changes_dispatch' . $extension
			);
		}
		else {
			wfWarn( "Database type '$type' is not supported by the Wikibase repository." );
		}

		if ( Settings::get( 'defaultStore' ) === 'sqlstore' ) {
			/**
			 * @var SQLStore $store
			 */
			$store = StoreFactory::getStore( 'sqlstore' );
			$store->doSchemaUpdate( $updater );
		}

		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param array &$files
	 *
	 * @return boolean
	 */
	public static function registerUnitTests( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/phpunit/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = array();
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Handler for the NamespaceIsMovable hook.
	 *
	 * Implemented to prevent moving pages that are in an entity namespace.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NamespaceIsMovable
	 *
	 * @since 0.1
	 *
	 * @param integer $ns Namespace ID
	 * @param boolean $movable
	 *
	 * @return boolean
	 */
	public static function onNamespaceIsMovable( $ns, &$movable ) {
		wfProfileIn( __METHOD__ );

		if ( NamespaceUtils::isEntityNamespace( $ns ) ) {
			$movable = false;
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Called when a revision was inserted due to an edit.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 *
	 * @since 0.1
	 *
	 * @param weirdStuffButProbably|WikiPage $article
	 * @param Revision $revision
	 * @param integer $baseID
	 * @param User $user
	 *
	 * @return boolean
	 */
	public static function onNewRevisionFromEditComplete( $article, Revision $revision, $baseID, User $user ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		if ( $entityContentFactory->isEntityContentModel( $article->getContent()->getModel() ) ) {
			/**
			 * @var $newEntity Entity
			 */
			$newEntity = $article->getContent()->getEntity();

			$parent = is_null( $revision->getParentId() )
				? null : Revision::newFromId( $revision->getParentId() );

			$change = EntityChange::newFromUpdate(
				$parent ? EntityChange::UPDATE : EntityChange::ADD,
				$parent ? $parent->getContent()->getEntity() : null,
				$newEntity
			);

			$change->setFields( array(
				'revision_id' => $revision->getId(),
				'user_id' => $user->getId(),
				'object_id' => $newEntity->getId()->getPrefixedId(),
				'time' => $revision->getTimestamp(),
			) );

			$changeNotifier = new ChangeNotifier();
			$changeNotifier->handleChange( $change );
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Occurs after the delete article request has been processed.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleDeleteComplete
	 *
	 * @since 0.1
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 * @param integer $id
	 * @param Content $content
	 * @param LogEntryBase $logEntry
	 *
	 * @throws MWException
	 *
	 * @return boolean
	 */
	public static function onArticleDeleteComplete( WikiPage $wikiPage, User $user, $reason, $id,
		Content $content = null, LogEntryBase $logEntry = null
	) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		// Bail out if we are not looking at an entity
		if ( !$content || !$entityContentFactory->isEntityContentModel( $content->getModel() ) ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		/**
		 * @var EntityContent $content
		 * @var Entity $entity
		 */
		$entity = $content->getEntity();

		$change = EntityChange::newFromUpdate( EntityChange::REMOVE, $entity, null, array(
			'revision_id' => 0, // there's no current revision
			'user_id' => $user->getId(),
			'object_id' => $entity->getId()->getPrefixedId(),
			'time' => $logEntry->getTimestamp(),
		) );

		$change->setMetadataFromUser( $user );

		$changeNotifier = new ChangeNotifier();
		$changeNotifier->handleChange( $change );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Handle changes for undeletions
	 *
	 * @since 0.2
	 *
	 * @param Title $title
	 * @param bool $created
	 * @param string $comment
	 *
	 * @return bool
	 */
	public static function onArticleUndelete( Title $title, $created, $comment ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		// Bail out if we are not looking at an entity
		if ( !$entityContentFactory->isEntityContentModel( $title->getContentModel() ) ) {
			return true;
		}

		$revId = $title->getLatestRevID();
		$content = $entityContentFactory->getFromRevision( $revId );

		if ( $content ) {
			StoreFactory::getStore()->newEntityPerPage()->addEntityContent( $content );

			$entity = $content->getEntity();

			$rev = Revision::newFromId( $revId );

			$userId = $rev->getUser();
			$change = EntityChange::newFromUpdate( EntityChange::RESTORE, null, $entity, array(
				// TODO: Use timestamp of log entry, but needs core change.
				// This hook is called before the log entry is created.
				'revision_id' => $revId,
				'user_id' => $userId,
				'object_id' => $entity->getId()->getPrefixedId(),
				'time' => wfTimestamp( TS_MW, wfTimestampNow() )
			) );

			$user = User::newFromId( $userId );
			$change->setMetadataFromUser( $user );

			$changeNotifier = new ChangeNotifier();
			$changeNotifier->handleChange( $change );
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * TODO: Add some stuff? Seems to be changes propagation...
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RecentChange_save
	 *
	 * @since ?
	 *
	 * @param $rc RecentChange
	 * @return bool
	 */
	public static function onRecentChangeSave( RecentChange $rc ) {
		if ( $rc->getAttribute( 'rc_log_type' ) === null ) {
			$changesTable = ChangesTable::singleton();

			$slave = $changesTable->getReadDb();
			$changesTable->setReadDb( DB_MASTER );

			/* @var EntityChange $change */
			$change = $changesTable->selectRow(
				null,
				array( 'revision_id' => $rc->getAttribute( 'rc_this_oldid' ) )
			);

			$changesTable->setReadDb( $slave );

			if ( $change ) {
				$change->setMetadataFromRC( $rc );
				$change->save();
			}
		}
		return true;
	}

	/**
	 * Allows to add user preferences.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * NOTE: Might make sense to put the inner functionality into a well structured Preferences file once this
	 *       becomes more.
	 *
	 * @since 0.1
	 *
	 * @param User $user
	 * @param array &$preferences
	 *
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		wfProfileIn( __METHOD__ );

		$preferences['wb-acknowledgedcopyrightversion'] = array(
			'type' => 'api'
		);

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Called after fetching the core default user options.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetDefaultOptions
	 *
	 * @param array &$defaultOptions
	 *
	 * @return bool
	 */
	public static function onUserGetDefaultOptions( array &$defaultOptions ) {
		wfProfileIn( __METHOD__ );

		// pre-select default language in the list of fallback languages
		$defaultLang = $defaultOptions['language'];
		$defaultOptions[ 'wb-languages-' . $defaultLang ] = 1;

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Modify line endings on history page.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryLineEnding
	 *
	 * @since 0.1
	 *
	 * @param HistoryPager $history
	 * @param object &$row
	 * @param string &$s
	 * @param array &$classes
	 *
	 * @return boolean
	 */
	public static function onPageHistoryLineEnding( HistoryPager $history, &$row, &$s, array &$classes  ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$article = $history->getArticle();
		$rev = new Revision( $row );

		if ( $entityContentFactory->isEntityContentModel( $history->getTitle()->getContentModel() )
			&& $article->getPage()->getLatest() !== $rev->getID()
			&& $rev->getTitle()->quickUserCan( 'edit', $history->getUser() )
		) {
			$link = Linker::linkKnown(
				$rev->getTitle(),
				$history->msg( 'wikibase-restoreold' )->escaped(),
				array(),
				array(
					'action'	=> 'edit',
					'restore'	=> $rev->getId()
				)
			);

			$s .= " " . $history->msg( 'parentheses' )->rawParams( $link )->escaped();
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Alter the structured navigation links in SkinTemplates.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 *
	 * @since 0.1
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array $links
	 *
	 * @return boolean
	 */
	public static function onPageTabs( SkinTemplate &$sktemplate, array &$links ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();

		if ( $entityContentFactory->isEntityContentModel( $title->getContentModel() ) ) {
			unset( $links['views']['edit'] );
			unset( $links['views']['viewsource'] );

			if ( $title->quickUserCan( 'edit', $sktemplate->getUser() ) ) {
				$old = !$sktemplate->isRevisionCurrent()
					&& !$request->getCheck( 'diff' );

				$restore = $request->getCheck( 'restore' );

				if ( $old || $restore ) {
					// insert restore tab into views array, at the second position

					$revid = $restore ? $request->getText( 'restore' ) : $sktemplate->getRevisionId();

					$head = array_slice( $links['views'], 0, 1);
					$tail = array_slice( $links['views'], 1 );
					$neck['restore'] = array(
						'class' => $restore ? 'selected' : false,
						'text' => $sktemplate->getLanguage()->ucfirst(
								wfMessage( 'wikibase-restoreold' )->text()
							),
						'href' => $title->getLocalURL( array(
								'action' => 'edit',
								'restore' => $revid )
							),
					);

					$links['views'] = array_merge( $head, $neck, $tail );
				}
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Handles a rebuild request by rebuilding all secondary storage of the repository.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WikibaseRebuildData
	 *
	 * @since 0.1
	 *
	 * @param callable $reportMessage // takes a string param and echos it
	 *
	 * @return boolean
	 */
	public static function onWikibaseRebuildData( $reportMessage ) {
		wfProfileIn( __METHOD__ );

		$store = StoreFactory::getStore();
		$stores = array_flip( $GLOBALS['wgWBStores'] );

		$reportMessage( 'Starting rebuild of the Wikibase repository ' . $stores[get_class( $store )] . ' store...' );

		$store->rebuild();

		$reportMessage( "done!\n" );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Reorder the groups for the special pages
	 *
	 * @since 0.4
	 *
	 * @param array &$groups
	 * @param bool &$moveOther
	 *
	 * @return boolean
	 */
	public static function onSpecialPage_reorderPages( &$groups, &$moveOther ) {
		$groups = array_merge( array( 'wikibaserepo' => null ), $groups );
		return true;
	}

	/**
	 * Deletes all the data stored on the repository.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WikibaseDeleteData
	 *
	 * @since 0.1
	 *
	 * @param callable $reportMessage // takes a string param and echos it
	 *
	 * @return boolean
	 */
	public static function onWikibaseDeleteData( $reportMessage ) {
		wfProfileIn( __METHOD__ );

		$reportMessage( 'Deleting data from changes table...' );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'wb_changes', '*', __METHOD__ );
		$dbw->delete( 'wb_changes_dispatch', '*', __METHOD__ );

		$reportMessage( "done!\n" );

		$reportMessage( 'Deleting revisions from Data NS...' );

		$namespaceList = $dbw->makeList( NamespaceUtils::getEntityNamespaces(), LIST_COMMA );

		$dbw->deleteJoin(
			'revision', 'page',
			'rev_page', 'page_id',
			array( 'page_namespace IN ( ' . $namespaceList . ')' )
		);

		$reportMessage( "done!\n" );

		$reportMessage( 'Deleting pages from Data NS...' );

		$dbw->delete(
			'page',
			array( 'page_namespace IN ( ' . $namespaceList . ')' )
		);

		$reportMessage( "done!\n" );

		$store = StoreFactory::getStore();
		$stores = array_flip( $GLOBALS['wgWBStores'] );

		$reportMessage( 'Deleting data from the ' . $stores[get_class( $store )] . ' store...' );

		$store->clear();

		$reportMessage( "done!\n" );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Used to append a css class to the body, so the page can be identified as Wikibase item page.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBodyAttributes
	 *
	 * @since 0.1
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param array $bodyAttrs
	 *
	 * @return bool
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, array &$bodyAttrs ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		if ( $entityContentFactory->isEntityContentModel( $out->getTitle()->getContentModel() ) ) {
			// we only add the classes, if there is an actual item and not just an empty Page in the right namespace
			$entityPage = new WikiPage( $out->getTitle() );
			$entityContent = $entityPage->getContent();

			/* @var EntityContent $entityContent */

			if( $entityContent !== null ) {
				// TODO: preg_replace kind of ridiculous here, should probably change the ENTITY_TYPE constants instead
				$entityType = preg_replace( '/^wikibase-/i', '', $entityContent->getEntity()->getType() );

				// add class to body so it's clear this is a wb item:
				$bodyAttrs['class'] .= " wb-entitypage wb-{$entityType}page";
				// add another class with the ID of the item:
				$bodyAttrs['class'] .= " wb-{$entityType}page-{$entityContent->getEntity()->getId()->getPrefixedId()}";

				if ( $sk->getRequest()->getCheck( 'diff' ) ) {
					$bodyAttrs['class'] .= ' wb-diffpage';
				}

				if ( $out->getRevisionId() !== $out->getTitle()->getLatestRevID() ) {
					$bodyAttrs['class'] .= ' wb-oldrevpage';
				}
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Special page handling where we want to display meaningful link labels instead of just the items ID.
	 * This is only handling special pages right now and gets disabled in normal pages.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinkBegin
	 *
	 * @param \DummyLinker $skin
	 * @param Title $target
	 * @param string $html
	 * @param array $customAttribs
	 * @param string $query
	 * @param array $options
	 * @param mixed $ret
	 * @return bool true
	 */
	public static function onLinkBegin( $skin, $target, &$html, array &$customAttribs, &$query, &$options, &$ret ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		//NOTE: the model returned by Title::getContentModel() is not reliable, see bug 37209
		$model = $target->getContentModel();
		$entityModels = $entityContentFactory->getEntityContentModels();


		// we only want to handle links to Wikibase entities differently here
		if ( !in_array( $model, $entityModels ) ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		// if custom link text is given, there is no point in overwriting it
		// but not if it is similar to the plain title
		if ( $html !== null && $target->getFullText() !== $html ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		global $wgTitle;

		// $wgTitle is temporarily set to special pages Title in case of special page inclusion! Therefore we can
		// just check whether the page is a special page and if not, disable the behavior.
		if( $wgTitle === null || !$wgTitle->isSpecialPage() ) {
			// no special page, we don't handle this for now
			// NOTE: If we want to handle this, messages would have to be generated in sites language instead of
			//       users language so they are cache independent.
			wfProfileOut( __METHOD__ );
			return true;
		}

		// The following three vars should all exist, unless there is a failurre
		// somewhere, and then it will fail hard. Better test it now!
		$page = new WikiPage( $target );
		if ( is_null( $page ) ) {
			// Failed, can't continue. This should not happen.
			wfProfileOut( __METHOD__ );
			return true;
		}
		$content = null;

		try {
			$content = $page->getContent();
		} catch ( MWContentSerializationException $ex ) {
			// if this fails, it's not horrible.
			wfWarn( "Failed to get entity object for [[" . $page->getTitle()->getFullText() . "]]"
					. ": " . $ex->getMessage() );
		}

		if ( is_null( $content ) || !( $content instanceof EntityContent ) ) {
			// Failed, can't continue. This could happen because the content is empty (page doesn't exist),
			// e.g. after item was deleted.

			// Due to bug 37209, we may also get non-entity content here, despite checking
			// Title::getContentModel up front.
			wfProfileOut( __METHOD__ );
			return true;
		}

		/* @var EntityContent $content */
		$entity = $content->getEntity();
		if ( is_null( $entity ) ) {
			// Failed, can't continue. This could happen because there is an illegal structure that could
			// not be parsed.
			wfProfileOut( __METHOD__ );
			return true;
		}

		// Try to find the most preferred available language to display data in current context.
		$languageFallbackChainFactory = WikibaseRepo::getDefaultInstance()->getLanguageFallbackChainFactory();
		$context = RequestContext::getMain();
		$languageFallbackChain = $languageFallbackChainFactory->newFromContext( $context );

		$labelData = $languageFallbackChain->extractPreferredValueOrAny( $entity->getLabels() );
		$descriptionData = $languageFallbackChain->extractPreferredValueOrAny( $entity->getDescriptions() );

		if ( $labelData ) {
			$labelText = $labelData['value'];
			$labelLang = Language::factory( $labelData['language'] );
		} else {
			$labelText = '';
			$labelLang = $context->getLanguage();
		}

		if ( $descriptionData ) {
			$descriptionText = $descriptionData['value'];
			$descriptionLang = Language::factory( $descriptionData['language'] );
		} else {
			$descriptionText = '';
			$descriptionLang = $context->getLanguage();
		}

		// Go on and construct the link
		$idHtml = Html::openElement( 'span', array( 'class' => 'wb-itemlink-id' ) )
			. wfMessage( 'wikibase-itemlink-id-wrapper', $target->getText() )->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );

		$labelHtml = Html::openElement( 'span', array( 'class' => 'wb-itemlink-label', 'lang' => $labelLang->getHtmlCode(), 'dir' => $labelLang->getDir() ) )
			. htmlspecialchars( $labelText )
			. Html::closeElement( 'span' );

		$html = Html::openElement( 'span', array( 'class' => 'wb-itemlink' ) )
			. wfMessage( 'wikibase-itemlink' )->rawParams( $labelHtml, $idHtml )->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );

		// Set title attribute for constructed link, and make tricks with the directionality to get it right
		$titleText = ( $labelText !== '' )
			? $labelLang->getDirMark() . $labelText . $context->getLanguage()->getDirMark()
			: $target->getPrefixedText();
		$customAttribs[ 'title' ] = ( $descriptionText !== '' ) ?
			wfMessage(
				'wikibase-itemlink-title',
				$titleText,
				$descriptionLang->getDirMark() . $descriptionText . $context->getLanguage()->getDirMark()
			)->inContentLanguage()->text() :
			$titleText; // no description, just display the title then

		// add wikibase styles in all cases, so we can format the link properly:
		$context->getOutput()->addModuleStyles( array( 'wikibase.common' ) );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Handler for the ApiCheckCanExecute hook in ApiMain.
	 *
	 * This implementation causes the execution of ApiEditPage (action=edit) to fail
	 * for all namespaces reserved for Wikibase entities. This prevents direct text-level editing
	 * of structured data, and it also prevents other types of content being created in these
	 * namespaces.
	 *
	 * @param ApiBase $module The API module being called
	 * @param User    $user   The user calling the API
	 * @param array|string|null   $message Output-parameter holding for the message the call should fail with.
	 *                            This can be a message key or an array as expected by ApiBase::dieUsageMsg().
	 *
	 * @return bool true to continue execution, false to abort and with $message as an error message.
	 */
	public static function onApiCheckCanExecute( ApiBase $module, User $user, &$message ) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		if ( $module instanceof ApiEditPage ) {
			$params = $module->extractRequestParams();
			$pageObj = $module->getTitleOrPageId( $params );
			$namespace = $pageObj->getTitle()->getNamespace();

			foreach ( $entityContentFactory->getEntityContentModels() as $model ) {
				/**
				 * @var EntityHandler $handler
				 */
				$handler = ContentHandler::getForModelID( $model );

				if ( $handler->getEntityNamespace() == $namespace ) {
					// trying to use ApiEditPage on an entity namespace
					$params = $module->extractRequestParams();

					// allow undo
					if ( $params['undo'] > 0 ) {
						wfProfileOut( __METHOD__ );
						return true;
					}

					// fail
					$message = array(
						'wikibase-no-direct-editing',
						$pageObj->getTitle()->getNsText()
					);

					wfProfileOut( __METHOD__ );
					return false;
				}
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Format the output when the search result contains entities
	 *
	 * @since 0.3
	 *
	 * @param SpecialSearch $searchPage
	 * @param SearchResult $result
	 * @param $terms
	 * @param &$link
	 * @param &$redirect
	 * @param &$section
	 * @param &$extract,
	 * @param &$score
	 * @param &$size
	 * @param &$date
	 * @param &$related
	 * @param &$html
	 *
	 * @return bool
	 */
	public static function onShowSearchHit( SpecialSearch $searchPage, SearchResult $result, $terms,
		&$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html
	) {
		wfProfileIn( __METHOD__ );

		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$model = $result->getTitle()->getContentModel();

		if ( $entityContentFactory->isEntityContentModel( $model ) ) {
			$lang = $searchPage->getLanguage();
			$page = WikiPage::factory( $result->getTitle() );

			/* @var EntityContent $content */
			$content = $page->getContent();

			if ( $content ) {
				$entity = $content->getEntity();
				$description = $entity->getDescription( $lang->getCode() ); // TODO: language fallback!

				if ( $description !== false && $description !== '' ) {
					$attr = array( 'class' => 'wb-itemlink-description' );
					$link .= wfMessage( 'colon-separator' )->text();
					$link .= Html::element( 'span', $attr, $description );
				}
			}

			$extract = ''; // TODO: set this to something useful.
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Handler for the TitleGetRestrictionTypes hook.
	 *
	 * Implemented to prevent people from protecting pages from being
	 * created or moved in an entity namespace (which is pointless).
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleGetRestrictionTypes
	 *
	 * @since 0.3
	 *
	 * @param Title $title
	 * @param array $types The types of protection available
	 *
	 * @return boolean
	 */
	public static function onTitleGetRestrictionTypes( Title $title, array &$types ) {
		if ( !NamespaceUtils::isEntityNamespace( $title->getNamespace() ) ) {
			return true;
		}

		// Remove create and move protection for Wikibase NSs
		if ( in_array( 'create', $types ) ) {
			unset( $types[ array_search( 'create', $types ) ] );
		}
		if ( in_array( 'move', $types ) ) {
			unset( $types[ array_search( 'move', $types ) ] );
		}

		return true;
	}

	/**
	 * Hook handler for AbuseFilter's AbuseFilter-contentToString hook, implemented
	 * to provide a custom text representation of Entities for filtering.
	 *
	 * @param Content $content The content object
	 * @param string  &$text The resulting text
	 *
	 * @return bool
	 */
	public static function onAbuseFilterContentToString( Content $content, &$text ) {
		if ( !( $content instanceof EntityContent ) ) {
			return true;
		}

		/* @var EntityContent $content */
		$text = $content->getTextForFilters();

		return false;
	}

	/**
	 * Pretty formatting of autocomments.
	 *
	 * Note that this function does _not_ use $title and $local but
	 * could use them if links should be created that points to something.
	 * Typically this could be links that moves to and highlight some
	 * section within the item itself.
	 *
	 * @param $data
	 * @param string $comment reference to the finalized autocomment
	 * @param string $pre the string before the autocomment
	 * @param string $auto the autocomment unformatted
	 * @param string $post the string after the autocomment
	 * @param Title $title use for further information
	 * @param boolean $local shall links be generated locally or globally
	 *
	 * @return boolean
	 */
	public static function onFormat( $data, &$comment, $pre, $auto, $post, $title, $local ) {
		global $wgLang, $wgTitle;

		list( $model, $root ) = $data;

		// If it is possible to avoid loading the whole page then the code will be lighter on the server.
		$title = $title === null ? $wgTitle : $title;

		if ( $title->getContentModel() !== $model ) {
			return true;
		}

		if ( preg_match( '/^([\-a-z]+?)\s*(:\s*(.*?))?\s*$/', $auto, $matches ) ) {

			// turn the args to the message into an array
			$args = ( 3 < count( $matches ) ) ? explode( '|', $matches[3] ) : array();

			// look up the message
			$msg = wfMessage( $root . '-summary-' . $matches[1] );
			if ( !$msg->isDisabled() ) {
				// parse the autocomment
				$auto = $msg->params( $args )->parse();

				// add pre and post fragments
				if ( $pre !== '' ) {
					// written summary $presep autocomment (summary /* section */)
					$pre .= wfMessage( 'autocomment-prefix' )->escaped();
				}
				if ( $post !== '' ) {
					// autocomment $postsep written summary (/* section */ summary)
					$auto .= wfMessage( 'colon-separator' )->escaped();
				}

				$auto = '<span class="autocomment">' . $auto . '</span>';
				$comment = $pre . $wgLang->getDirMark() . '<span dir="auto">' . $auto . $post . '</span>';
			}
		}
		return true;
	}

	/**
	 * Called when pushing meta-info from the ParserOutput into OutputPage.
	 * Used to transfer the 'wb-placeholders' from ParserOutput to OutputPage.
	 *
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 *
	 * @return bool
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		$placeholders = $parserOutput->getExtensionData( 'wikibase-view-chunks' );

		if ( $placeholders ) {
			$out->setProperty( 'wikibase-view-chunks', $placeholders );
		}

		return true;
	}

	/**
	 * Called when pushing HTML from the ParserOutput into OutputPage.
	 * Used to expand any placeholders in the OutputPage's 'wb-placeholders' property
	 * in the HTML.
	 *
	 * @param OutputPage $out
	 * @param string &$html the HTML to mangle
	 *
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( OutputPage $out, &$html ) {
		$placeholders = $out->getProperty( 'wikibase-view-chunks' );

		if ( $placeholders ) {
			$injector = new TextInjector( $placeholders );

			$expander = new EntityViewPlaceholderExpander(
				$out->getTitle(),
				$out->getUser(),
				$out->getLanguage(),
				WikibaseRepo::getDefaultInstance()->getEntityIdParser(),
				WikibaseRepo::getDefaultInstance()->getEntityLookup(),
				new UserLanguageLookup( $out->getUser() )
			);

			$html = $injector->inject( $html, array( $expander, 'getHtmlForPlaceholder' ) );
		}

		return true;
	}
}
