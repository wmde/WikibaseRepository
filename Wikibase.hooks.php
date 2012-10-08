<?php

namespace Wikibase;
use Title, Language, User, Revision, WikiPage, EditPage, ContentHandler, Html, MWException;


/**
 * File defining the hook handlers for the Wikibase extension.
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Nikola Smolenski
 * @author Daniel Werner
 * @author Michał Łazowik
 */
final class RepoHooks {

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
		global $wgNamespaceContentModels;

		$namespaces = Settings::get( 'entityNamespaces' );

		if ( empty( $namespaces ) ) {
			throw new MWException( 'Wikibase: Incomplete configuration: '
				. '$wgWBSettings["entityNamespaces"] has to be set to an array mapping content model IDs to namespace IDs. '
				. 'See ExampleSettings.php for details and examples.');
		}

		foreach ( $namespaces as $model => $ns ) {
			if ( !isset( $wgNamespaceContentModels[$ns] ) ) {
				$wgNamespaceContentModels[$ns] = $model;
			}
		}

		return true;
	}

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @since 0.1
	 *
	 * @param \DatabaseUpdater $updater
	 *
	 * @return boolean
	 */
	public static function onSchemaUpdate( \DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();

		if ( $type === 'mysql' || $type === 'sqlite' /* || $type === 'postgres' */ ) {
			$extension = $type === 'postgres' ? '.pg.sql' : '.sql';

			$updater->addExtensionTable(
				'wb_changes',
				__DIR__ . '/sql/changes' . $extension
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
		$testFiles = array(
			'ItemMove',
			'ItemContentDiffView',
			'ItemMove',
			'ItemView', 
			'Autocomment',
			'EditEntity',

			'actions/EditEntityAction',

			'api/ApiBotEdit',
			'api/ApiEditPage',
			'api/ApiGetItems',
			'api/ApiLabel',
			'api/ApiDescription',
			'api/ApiPermissions',
			'api/ApiSetAliases',
			'api/ApiSetItem',
			'api/ApiSetSiteLink',
			'api/ApiLinkTitles',

			'content/EntityContentFactory',
			'content/EntityHandler',
			'content/ItemContent',
			'content/ItemHandler',
			'content/PropertyContent',
			'content/PropertyHandler',
			'content/QueryContent',
			'content/QueryHandler',

			'specials/SpecialCreateItem',
			'specials/SpecialItemDisambiguation',
			'specials/SpecialItemByTitle',

			'store/IdGenerator',
			'store/StoreFactory',
			'store/Store',
			'store/TermCache',

			'store/sql/SqlIdGenerator',

			'updates/ItemDeletionUpdate',
			'updates/ItemModificationUpdate',
		);

		foreach ( $testFiles as $file ) {
			$files[] = __DIR__ . '/tests/phpunit/includes/' . $file . 'Test.php';
		}

		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * In Wikidata namespace, page content language is the same as the current user language.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentLanguage
	 *
	 * @since 0.1
	 *
	 * @param Title $title
	 * @param Language &$pageLanguage
	 * @param Language|\StubUserLang $language
	 *
	 * @return boolean
	 */
	public static function onPageContentLanguage( Title $title, Language &$pageLanguage, $language ) {
		global $wgNamespaceContentModels;

		// TODO: make this a little nicer
		if( array_key_exists( $title->getNamespace(), $wgNamespaceContentModels )
			&& in_array(
				$title->getContentModel(),
				EntityContentFactory::singleton()->getEntityContentModels()
			)
		) {
			$pageLanguage = $language;
		}

		return true;
	}

	/**
	 * Add new javascript testing modules. This is called after the addition of MediaWiki core test suites.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @since 0.1
	 *
	 * @param array &$testModules
	 * @param \ResourceLoader &$resourceLoader
	 *
	 * @return boolean
	 */
	public static function onResourceLoaderTestModules( array &$testModules, \ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['wikibase.tests'] = array(
			'scripts' => array(
				'tests/qunit/wikibase.tests.js',
				'tests/qunit/wikibase.Site.tests.js',
				'tests/qunit/wikibase.ui.AliasesEditTool.tests.js',
				'tests/qunit/wikibase.ui.DescriptionEditTool.tests.js',
				'tests/qunit/wikibase.ui.LabelEditTool.tests.js',
				'tests/qunit/wikibase.ui.SiteLinksEditTool.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableAliases.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableDescription.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableLabel.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableSiteLink.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.AutocompleteInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.Interface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.SiteIdInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface.tests.js',
				'tests/qunit/wikibase.ui.PropertyEditTool.EditableValue.ListInterface.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.EditGroup.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Group.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Label.tests.js',
				'tests/qunit/wikibase.ui.Toolbar.Button.tests.js',
				'tests/qunit/wikibase.ui.Tooltip.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.inherit.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.newExtension.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.ObservableObject.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.PersistentPromisor.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.inputAutoExpand.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.tagadata.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.eachchange.tests.js',
				'tests/qunit/wikibase.utilities/wikibase.utilities.jQuery.ui.wikibaseAutocomplete.tests.js'
			),
			'dependencies' => array(
				'wikibase.tests.qunit.testrunner',
				'wikibase',
				'wikibase.utilities',
				'wikibase.utilities.jQuery',
				'wikibase.ui.Toolbar',
				'wikibase.ui.PropertyEditTool'
			),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikibase/repo',
		);

		return true;
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
		if ( Utils::isEntityNamespace( $ns ) ) {
			$movable = false;
		}

		return true;
	}

	/**
	 * Called when a revision was inserted due to an edit.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 *
	 * @since 0.1
	 *
	 * @param weirdStuffButProbablyWikiPage $article
	 * @param Revision $revision
	 * @param integer $baseID
	 * @param User $user
	 *
	 * @return boolean
	 */
	public static function onNewRevisionFromEditComplete( $article, Revision $revision, $baseID, User $user ) {
		if ( EntityContentFactory::singleton()->isEntityContentModel( $article->getContent()->getModel() ) ) {
			/**
			 * @var $newEntity Entity
			 */
			$newEntity = $article->getContent()->getEntity();

			if ( is_null( $revision->getParentId() ) ) {
				$change = EntityCreation::newFromEntity( $newEntity );
			} else {
				$change = EntityUpdate::newFromEntities(
					Revision::newFromId( $revision->getParentId() )->getContent()->getEntity(),
					$newEntity
				);
			}

			$change->setFields( array(
				'revision_id' => $revision->getId(),
				'user_id' => $user->getId(),
				'object_id' => $newEntity->getId(),
				'time' => $revision->getTimestamp(),
			) );

			ChangeNotifier::singleton()->handleChange( $change );
		}

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
	 * @param \Content $content
	 * @param \LogEntryBase $logEntry
	 *
	 * @throws MWException
	 *
	 * @return boolean
	 */
	public static function onArticleDeleteComplete( WikiPage $wikiPage, User $user, $reason, $id,
		\Content $content = null, \LogEntryBase $logEntry = null
	) {

		if ( $content === null ) {
			throw new MWException( 'Hook ArticleDeleteComplete is missing an argument, please update your MediaWiki installation!' );
		}

		// Bail out if we are not in an entity namespace
		if ( !in_array( $content->getModel(), EntityContentFactory::singleton()->getEntityContentModels() ) ) {
			return true;
		}
		$item = $content->getItem();
		$change = EntityDeletion::newFromEntity( $item );
		$change->setFields( array(
			//'previous_revision_id' => $wikiPage->getLatest(),
			'revision_id' => 0, // there's no current revision
			'user_id' => $user->getId(),
			'object_id' => $item->getId(),
			'time' => $logEntry->getTimestamp(),
		) );

		ChangeNotifier::singleton()->handleChange( $change );

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
		$preferences['wb-languages'] = array(
			'type' => 'multiselect',
			'usecheckboxes' => false,
			'label-message' => 'wikibase-setting-languages',
			'options' => $preferences['language']['options'], // all languages available in 'language' selector
			'section' => 'personal/i18n',
			'prefix' => 'wb-languages-',
		);

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
		// pre-select default language in the list of fallback languages
		$defaultLang = $defaultOptions['language'];
		$defaultOptions[ 'wb-languages-' . $defaultLang ] = 1;

		return true;
	}

	/**
	 * Adds default settings.
	 * Setting name (string) => setting value (mixed)
	 *
	 * @param array &$settings
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public static function onWikibaseDefaultSettings( array &$settings ) {
		$settings = array_merge(
			$settings,
			array(
				// alternative: application/vnd.php.serialized
				'serializationFormat' => CONTENT_FORMAT_JSON,

				// Defaults to turn on deletion of empty items
				// set to true will always delete empty items
				'apiDeleteEmpty' => false,

				// Set API in debug mode
				// do not turn on in production!
				'apiInDebug' => false,

				// Additional settings for API when debugging is on to
				// facilitate testing, do not turn on in production!
				'apiDebugWithWrite' => true,
				'apiDebugWithPost' => false,
				'apiDebugWithRights' => false,
				'apiDebugWithTokens' => false,

				// settings for the user agent
				//TODO: This should REALLY be handled somehow as without it we could run into lots of trouble
				'clientTimeout' => 10, // this is before final timeout, without maxlag or maxage we can't hang around
				//'clientTimeout' => 120, // this is before final timeout, the maxlag value and then some
				'clientPageOpts' => array(
					'userAgent' => 'Wikibase',
				),

				'defaultStore' => 'sqlstore',

				'idBlacklist' => array(
					1,
					23,
					42,
					1337,
					9001,
					31337,
					720101010,
				)
			)
		);

		return true;
	}

	/**
	 * Modify line endings on history page.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryLineEnding
	 *
	 * @since 0.1
	 *
	 * @param \HistoryPager $history
	 * @param object &$row
	 * @param string &$s
	 * @param array &$classes
	 *
	 * @return boolean
	 */
	public static function onPageHistoryLineEnding( \HistoryPager $history, &$row, &$s, array &$classes  ) {
		$article = $history->getArticle();
		$rev = new Revision( $row );

		if ( EntityContentFactory::singleton()->isEntityContentModel( $history->getTitle()->getContentModel() )
			&& $article->getPage()->getLatest() !== $rev->getID()
			&& $rev->getTitle()->quickUserCan( 'edit', $history->getUser() )
		) {
			$link = \Linker::linkKnown(
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

		return true;
	}

	/**
	 * Alter the structured navigation links in SkinTemplates.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 *
	 * @since 0.1
	 *
	 * @param \SkinTemplate $sktemplate
	 * @param array $links
	 *
	 * @return boolean
	 */
	public static function onPageTabs( \SkinTemplate &$sktemplate, array &$links ) {
		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();

		if ( EntityContentFactory::singleton()->isEntityContentModel( $title->getContentModel() ) ) {
			unset( $links['views']['edit'] );

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

		return true;
	}

	/**
	 * Handles a rebuild request by rebuilding all secondary storage of the repository.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WikibaseRebuildData
	 *
	 * @since 0.1
	 *
	 * @param callable $reportMessage
	 *
	 * @return boolean
	 */
	public static function onWikibaseRebuildData( $reportMessage ) {
		$store = StoreFactory::getStore();
		$stores = array_flip( $GLOBALS['wgWBStores'] );

		$reportMessage( 'Starting rebuild of the Wikibase repository ' . $stores[get_class( $store )] . ' store...' );

		$store->rebuild();

		$reportMessage( "done!\n" );

		return true;
	}

	/**
	 * Deletes all the data stored on the repository.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WikibaseDeleteData
	 *
	 * @since 0.1
	 *
	 * @param callable $reportMessage
	 *
	 * @return boolean
	 */
	public static function onWikibaseDeleteData( $reportMessage ) {
		$reportMessage( 'Deleting revisions from Data NS...' );

		$dbw = wfGetDB( DB_MASTER );

		$namespaceList = $dbw->makeList(  Utils::getEntityNamespaces(), LIST_COMMA );

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

		return true;
	}

	/**
	 * Used to append a css class to the body, so the page can be identified as Wikibase item page.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBodyAttributes
	 *
	 * @since 0.1
	 *
	 * @param \OutputPage $out
	 * @param \Skin $sk
	 * @param array $bodyAttrs
	 *
	 * @return bool
	 */
	public static function onOutputPageBodyAttributes( \OutputPage $out, \Skin $sk, array &$bodyAttrs ) {
		if ( EntityContentFactory::singleton()->isEntityContentModel( $out->getTitle()->getContentModel() ) ) {
			// we only add the classes, if there is an actual item and not just an empty Page in the right namespace
			$entityPage = new WikiPage( $out->getTitle() );
			$entityContent = $entityPage->getContent();

			if( $entityContent !== null ) {
				// TODO: preg_replace kind of ridiculous here, should probably change the ENTITY_TYPE constants instead
				$entityType = preg_replace( '/^wikibase-/i', '', $entityContent->getEntity()->getType() );

				// add class to body so it's clear this is a wb item:
				$bodyAttrs['class'] .= " wb-entitypage wb-{$entityType}page";
				// add another class with the ID of the item:
				$bodyAttrs['class'] .= " wb-{$entityType}page-{$entityContent->getEntity()->getId()}";

				if ( $sk->getRequest()->getCheck( 'diff' ) ) {
					$bodyAttrs['class'] .= ' wb-diffpage';
				}

				if ( $out->getRevisionId() !== $out->getTitle()->getLatestRevID() ) {
					$bodyAttrs['class'] .= ' wb-oldrevpage';
				}
			}
		}
		return true;
	}

	/**
	 * Special page handling where we want to display meaningful link labels instead of just the items ID.
	 * This is only handling special pages right now and gets disabled in normal pages.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinkBegin
	 *
	 * @param \DummyLinker $skin
	 * @param Title $target
	 * @param string $text
	 * @param array $customAttribs
	 * @param string $query
	 * @param array $options
	 * @param mixed $ret
	 * @return bool true
	 */
	public static function onLinkBegin( $skin, $target, &$html, array &$customAttribs, &$query, &$options, &$ret ) {
		if(
			// if custom link text is given, there is no point in overwriting it
			$html !== null
			// we only want to handle links to Wikibase entities differently here
			|| !in_array(
				$target->getContentModel(),
				EntityContentFactory::singleton()->getEntityContentModels()
			)
			// as of MW 1.20 Linker shouldn't support anything but Title anyhow
			|| ! $target instanceof Title
		) {
			return true;
		}

		// $wgTitle is temporarily set to special pages Title in case of special page inclusion! Therefore we can
		// just check whether the page is a special page and if not, disable the behavior.
		global $wgTitle;

		if( $wgTitle === null || !$wgTitle->isSpecialPage() ) {
			// no special page, we don't handle this for now
			// NOTE: If we want to handle this, messages would have to be generated in sites language instead of
			//       users language so they are cache independent.
			return true;
		}

		global $wgLang, $wgOut;

		// The following three vars should all exist, unless there is a failurre
		// somewhere, and then it will fail hard. Better test it now!
		$page = new WikiPage( $target );
		if ( is_null( $page ) ) {
			// Failed, can't continue. This should not happen.
			return true;
		}
		$content = $page->getContent();
		if ( is_null( $content ) ) {
			// Failed, can't continue. This could happen because the content is empty (page doesn't exist),
			// e.g. after item was deleted.
			return true;
		}
		$entity = $content->getEntity();
		if ( is_null( $entity ) ) {
			// Failed, can't continue. This could happen because there is an illegal structure that could
			// not be parsed.
			return true;
		}

		// If this fails we will not find labels and descriptions later,
		// but we will try to get a list of alternate languages. The following
		// uses the user language as a starting point for the fallback chain.
		// It could be argued that the fallbacks should be limited to the user
		// selected languages.
		$lang = $wgLang->getCode();
		static $langStore = array();
		if ( !isset( $langStore[$lang] ) ) {
			$langStore[$lang] = array_merge( array( $lang ), Language::getFallbacksFor( $lang ) );
		}

		// Get the label and description for the first languages on the chain
		// that doesn't fail, use a fallback if everything fails. This could
		// use the user supplied list of acceptable languages as a filter.
		list( $labelCode, $labelText, $labelLang) = $labelTriplet =
			Utils::lookupMultilangText(
				$entity->getLabels( $langStore[$lang] ),
				$langStore[$lang],
				array( $wgLang->getCode(), null, $wgLang )
			);
		list( $descriptionCode, $descriptionText, $descriptionLang) = $descriptionTriplet =
			Utils::lookupMultilangText(
				$entity->getDescriptions( $langStore[$lang] ),
				$langStore[$lang],
				array( $wgLang->getCode(), null, $wgLang )
			);

		// Go on and construct the link
		$idHtml = Html::openElement( 'span', array( 'class' => 'wb-itemlink-id' ) )
			. wfMessage( 'wikibase-itemlink-id-wrapper', $target->getText() )->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );

		$labelHtml = Html::openElement( 'span', array( 'class' => 'wb-itemlink-label', 'lang' => $labelLang->getCode(), 'dir' => $labelLang->getDir() ) )
			. htmlspecialchars( $labelText )
			. Html::closeElement( 'span' );

		$html = Html::openElement( 'span', array( 'class' => 'wb-itemlink' ) )
			. wfMessage( 'wikibase-itemlink' )->rawParams( $labelHtml, $idHtml )->inContentLanguage()->escaped()
			. Html::closeElement( 'span' );

		// Set title attribute for constructed link, and make tricks with the directionality to get it right
		$titleText = ( $labelText !== '' )
			? $labelLang->getDirMark() . $labelText . $wgLang->getDirMark()
			: $target->getPrefixedText();
		$customAttribs[ 'title' ] = ( $descriptionText !== '' ) ?
			wfMessage(
				'wikibase-itemlink-title',
				$titleText,
				$descriptionLang->getDirMark() . $descriptionText . $wgLang->getDirMark()
			)->inContentLanguage()->text() :
			$titleText; // no description, just display the title then

		// add wikibase styles in all cases, so we can format the link properly:
		$wgOut->addModuleStyles( array( 'wikibase.common' ) );

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
	 * @param \ApiBase $module The API module being called
	 * @param User    $user   The user calling the API
	 * @param array|string|null   $message Output-parameter holding for the message the call should fail with.
	 *                            This can be a message key or an array as expected by ApiBase::dieUsageMsg().
	 *
	 * @return bool true to continue execution, false to abort and with $message as an error message.
	 */
	public static function onApiCheckCanExecute( \ApiBase $module, User $user, &$message ) {
		if ( $module instanceof \ApiEditPage ) {
			$params = $module->extractRequestParams();
			$pageObj = $module->getTitleOrPageId( $params );
			$namespace = $pageObj->getTitle()->getNamespace();

			foreach ( EntityContentFactory::singleton()->getEntityContentModels() as $model ) {
				/**
				 * @var EntityHandler $handler
				 */
				$handler = ContentHandler::getForModelID( $model );

				if ( $handler->getEntityNamespace() == $namespace ) {
					// trying to use ApiEditPage on an entity namespace - just fail
					$message = array(
						'wikibase-no-direct-editing',
						$pageObj->getTitle()->getNsText()
					);

					return false;
				}
			}
		}

		return true;
	}

}
