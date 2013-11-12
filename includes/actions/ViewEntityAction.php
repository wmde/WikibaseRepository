<?php

namespace Wikibase;
use Language, Article, \ValueFormatters\ValueFormatterFactory;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Repo\WikibaseRepo;

/**
 * Handles the view action for Wikibase entities.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler < daniel.kinzler@wikimedia.de >
 */
abstract class ViewEntityAction extends \ViewAction {

	/**
	 * @var LanguageFallbackChain
	 */
	protected $languageFallbackChain;

	/**
	 * Get the language fallback chain for current context.
	 *
	 * @since 0.4
	 *
	 * @return LanguageFallbackChain
	 */
	public function getLanguageFallbackChain() {
		if ( $this->languageFallbackChain === null ) {
			$this->languageFallbackChain = WikibaseRepo::getDefaultInstance()->getLanguageFallbackChainFactory()
				->newFromContext( $this->getContext() );
		}

		return $this->languageFallbackChain;
	}

	/**
	 * Set language fallback chain.
	 *
	 * @since 0.4
	 *
	 * @param LanguageFallbackChain $chain
	 */
	public function setLanguageFallbackChain( LanguageFallbackChain $chain ) {
		$this->languageFallbackChain = $chain;
	}

	/**
	 * Get the revision specified in the diff parameter or prev/next revision of oldid
	 *
	 * @since 0.4
	 * @deprecated since 0.5
	 * use ContentRetriever::getDiffRevision
	 *
	 * @param int $oldId
	 * @param string|int $diffValue
	 *
	 * @return Revision|null
	 */
	public function getDiffRevision( $oldId, $diffValue ) {
		$contentRetriever = new ContentRetriever();
		return $contentRetriever->getDiffRevision( $oldId, $diffValue );
	}

	/**
	 * @see Action::getName()
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getName() {
		return 'view';
	}

	/**
	 * Returns the current article.
	 *
	 * @since 0.1
	 *
	 * @return \Article
	 */
	protected function getArticle() {
		return $this->page;
	}

	/**
	 * @see FormlessAction::show()
	 *
	 * @since 0.1
	 *
	 * TODO: permissing checks?
	 * Parent is doing $this->checkCanExecute( $this->getUser() )
	 */
	public function show() {
		$contentRetriever = new ContentRetriever();
		$content = $contentRetriever->getContentForRequest(
			$this->getArticle(),
			$this->getTitle(),
			$this->getRequest()
		);

		if ( is_null( $content ) ) {
			$this->displayMissingEntity();
		}
		else {
			$this->getArticle()->getRevisionFetched();

			$this->displayEntityContent( $content );

			$formatterOptions = new FormatterOptions(); //TODO: Language Fallback
			$formatterOptions->setOption( ValueFormatter::OPT_LANG, $this->getContext()->getLanguage()->getCode() );

			$snakFormatter = WikibaseRepo::getDefaultInstance()->getSnakFormatterFactory()
								->getSnakFormatter( SnakFormatter::FORMAT_HTML_WIDGET, $formatterOptions );

			$dataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
			$entityLoader = WikibaseRepo::getDefaultInstance()->getStore()->getEntityRevisionLookup();
			$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

			$isEditableView = $this->isPlainView();

			$view = EntityView::newForEntityType(
				$content->getEntity()->getType(),
				$snakFormatter,
				$dataTypeLookup,
				$entityLoader,
				$entityContentFactory
			);

			$view->registerJsConfigVars(
				$this->getOutput(),
				$content->getEntityRevision(),
				$this->getLanguage()->getCode(),
				$isEditableView
			);
		}
	}

	/**
	 * Returns true if this view action is performing a plain view (not a diff, etc)
	 * of the page's current revision.
	 */
	public function isPlainView() {
		if ( !$this->getArticle()->getPage()->exists() ) {
			// showing non-existing entity
			return false;
		}

		if ( $this->getArticle()->getOldID() > 0
			&&  ( $this->getArticle()->getOldID() !== $this->getArticle()->getPage()->getLatest() ) ) {
			// showing old content
			return false;
		}

		$contentRetriever = new ContentRetriever();
		$content = $contentRetriever->getContentForRequest(
			$this->getArticle(),
			$this->getTitle(),
			$this->getRequest()
		);

		if ( !( $content instanceof EntityContent ) ) {
			//XXX: HACK against evil tricks in Article::getContentObject
			// showing strange content
			return false;
		}

		if ( $this->getContext()->getRequest()->getCheck( 'diff' ) ) {
			// showing a diff
			return false;
		}

		return true;
	}

	/**
	 * Displays the entity content.
	 *
	 * @since 0.1
	 *
	 * @param EntityContent $content
	 */
	protected function displayEntityContent( EntityContent $content ) {
		$out = $this->getOutput();

		// can edit?
		$editable = $this->isPlainView();
		$editable = ( $editable && $content->userCanEdit( null, false ) );

		// View it!
		$parserOptions = $this->getArticle()->getPage()->makeParserOptions( $this->getContext()->getUser() );

		if ( !$editable ) {
			// disable editing features ("sections" is a misnomer, it applies to the wikitext equivalent)
			$parserOptions->setEditSection( $editable );
		}

		$this->getArticle()->setParserOptions( $parserOptions );
		$this->getArticle()->view();

		// Figure out which label to use for title.
		$languageFallbackChain = $this->getLanguageFallbackChain();
		$labelData = $languageFallbackChain->extractPreferredValueOrAny( $content->getEntity()->getLabels() );

		if ( $labelData ) {
			$labelText = $labelData['value'];
		} else {
			$idPrefixer = WikibaseRepo::getDefaultInstance()->getIdFormatter();
			$labelText = strtoupper( $idPrefixer->format( $content->getEntity()->getId() ) );
		}

		// Create and set the title.
		if ( $this->getContext()->getRequest()->getCheck( 'diff' ) ) {
			// Escaping HTML characters in order to retain original label that may contain HTML
			// characters. This prevents having characters evaluated or stripped via
			// OutputPage::setPageTitle:
			$out->setPageTitle(
				$this->msg(
					'difference-title'
					// This should be something like the following,
					// $labelLang->getDirMark() . $labelText . $wgLang->getDirMark()
					// or should set the attribute of the h1 to correct direction.
					// Still note that the direction is "auto" so guessing should
					// give the right direction in most cases.
				)->rawParams( htmlspecialchars( $labelText ) )
			);
		} else {
			// Prevent replacing {{...}} by using rawParams() instead of params():
			$this->getOutput()->setHTMLTitle( $this->msg( 'pagetitle' )->rawParams( $labelText ) );
		}
	}

	/**
	 * Displays there is no entity for the current page.
	 *
	 * @since 0.1
	 */
	protected function displayMissingEntity() {
		global $wgSend404Code;

		$title = $this->getArticle()->getTitle();
		$oldid = $this->getArticle()->getOldID();

		$out = $this->getOutput();

		$out->setPageTitle( $title->getPrefixedText() );

		// TODO: Factor the "show stuff for missing page" code out from Article::showMissingArticle,
		//       so it can be re-used here. The below code is copied & modified from there...

		wfRunHooks( 'ShowMissingArticle', array( $this ) );

		# Show delete and move logs
		\LogEventsList::showLogExtract( $out, array( 'delete', 'move' ), $title, '',
			array(  'lim' => 10,
			        'conds' => array( "log_action != 'revision'" ),
			        'showIfEmpty' => false,
			        'msgKey' => array( 'moveddeleted-notice' ) )
		);

		if ( $wgSend404Code ) {
			// If there's no backing content, send a 404 Not Found
			// for better machine handling of broken links.
			$this->getContext()->getRequest()->response()->header( "HTTP/1.1 404 Not Found" );
		}

		$hookResult = wfRunHooks( 'BeforeDisplayNoArticleText', array( $this ) );

		// XXX: ...end of stuff stolen from Article::showMissingArticle

		if ( $hookResult ) {
			// Show error message
			if ( $oldid ) {
				$text = wfMessage( 'missing-article',
					$this->getTitle()->getPrefixedText(),
					wfMessage( 'missingarticle-rev', $oldid )->plain() )->plain();
			} else {
				/** @var $entityHandler EntityHandler */
				$entityHandler = \ContentHandler::getForTitle( $this->getTitle() );
				$entityCreationPage = $entityHandler->getSpecialPageForCreation();

				$text = wfMessage( 'wikibase-noentity' )->plain();

				if( $entityCreationPage !== null
					&& $this->getTitle()->quickUserCan( 'create', $this->getContext()->getUser() )
					&& $this->getTitle()->quickUserCan( 'edit', $this->getContext()->getUser() )
				) {
					/*
					 * add text with link to special page for creating an entity of that type if possible and
					 * if user has the rights for it
					 */
					$createEntityPage = \SpecialPage::getTitleFor( $entityCreationPage );
					$text .= ' ' . wfMessage(
						'wikibase-noentity-createone',
						$createEntityPage->getPrefixedText() // TODO: might be nicer to use an 'action=create' instead
					)->plain();
				}
			}

			$text = "<div class='noarticletext'>\n$text\n</div>";

			$out->addWikiText( $text );
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see Action::getDescription()
	 */
	protected function getDescription() {
		return '';
	}

	/**
	 * (non-PHPdoc)
	 * @see Action::requiresUnblock()
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * (non-PHPdoc)
	 * @see Action::requiresWrite()
	 */
	public function requiresWrite() {
		return false;
	}

}
