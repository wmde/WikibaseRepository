<?php

namespace Wikibase\Repo\Specials;

use Html;
use Status;
use UserBlockedError;
use Wikibase\CopyrightMessageBuilder;
use Wikibase\EditEntity;
use Wikibase\EntityContent;
use Wikibase\Lib\Specials\SpecialWikibasePage;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * Page for creating new Wikibase entities.
 *
 * @since 0.1
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Jens Ohlig
 * @author John Erling Blad < jeblad@gmail.com >
 */
abstract class SpecialNewEntity extends SpecialWikibasePage {

	/**
	 * Contains pieces of the sub-page name of this special page if a subpage was called.
	 * E.g. array( 'a', 'b' ) in case of 'Special:NewEntity/a/b'
	 * @var string[]
	 */
	protected $parts = null;

	/**
	 * @var string
	 */
	protected $label = null;

	/**
	 * @var string
	 */
	protected $description = null;

	/**
	 * @var SummaryFormatter
	 */
	protected $summaryFormatter;

	/**
	 * @var string
	 */
	protected $rightsUrl;

	/**
	 * @var string
	 */
	protected $rightsText;

	/**
	 * @param $name String: name of the special page, as seen in links and URLs
	 * @param $restriction String: user right required, 'createpage' per default.
	 *
	 * @since 0.1
	 */
	public function __construct( $name, $restriction = 'createpage' ) {
		parent::__construct( $name, $restriction );

		// TODO: find a way to inject this
		$this->summaryFormatter = WikibaseRepo::getDefaultInstance()->getSummaryFormatter();

		$settings = WikibaseRepo::getDefaultInstance()->getSettings();

		$this->rightsUrl = $settings->getSetting( 'dataRightsUrl' );
		$this->rightsText = $settings->getSetting( 'dataRightsText' );
	}

	/**
	 * Main method.
	 *
	 * @since 0.1
	 *
	 * @param string|null $subPage
	 *
	 * @return boolean
	 */
	public function execute( $subPage ) {
		if ( !parent::execute( $subPage ) ) {
			return false;
		}

		$this->checkPermissions();
		$this->checkBlocked();
		$this->checkReadOnly();

		$this->parts = ( $subPage === '' ? array() : explode( '/', $subPage ) );
		$this->prepareArguments();

		$out = $this->getOutput();

		if ( $this->getRequest()->wasPosted()
			&&  $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'token' ) ) ) {

			if ( $this->hasSufficientArguments() ) {
				$entityContent = $this->createEntityContent();

				$status = $this->modifyEntity( $entityContent );

				if ( $status->isGood() ) {
					$summary = new Summary( 'wbeditentity', 'create' );
					$summary->setLanguage( $this->getLanguage()->getCode() );
					$summary->addAutoSummaryArgs( $this->label, $this->description );
					$editEntity = new EditEntity( $entityContent, $this->getUser(), false, $this->getContext() );
					$editEntity->attemptSave(
						$this->summaryFormatter->formatSummary( $summary ),
						EDIT_AUTOSUMMARY|EDIT_NEW,
						$this->getRequest()->getVal( 'token' )
					);

					$out = $this->getOutput();

					if ( !$editEntity->isSuccess() ) {
						$out->addHTML( '<div class="error">' );
						$out->addWikiText( $editEntity->getStatus()->getWikiText() );
						$out->addHTML( '</div>' );
					} elseif ( $entityContent !== null ) {
						$entityUrl = $entityContent->getTitle()->getFullUrl();
						$this->getOutput()->redirect( $entityUrl );
					}
				} else {
					$out->addHTML( '<div class="error">' );
					$out->addHTML( $status->getHTML() );
					$out->addHTML( '</div>' );
				}
			}
		}

		$this->getOutput()->addModuleStyles( array( 'wikibase.special' ) );

		foreach ( $this->getWarnings() as $warning ) {
			$out->addHTML( Html::element( 'div', array( 'class' => 'warning' ), $warning ) );
		}

		$this->createForm( $this->getLegend(), $this->additionalFormElements() );
	}

	/**
	 * Tries to extract argument values from web request or of the page's sub-page parts
	 *
	 * @since 0.1
	 */
	protected function prepareArguments() {
		$this->label = $this->getRequest()->getVal( 'label', isset( $this->parts[0] ) ? $this->parts[0] : '' );
		$this->description = $this->getRequest()->getVal( 'description', isset( $this->parts[1] ) ? $this->parts[1] : '' );
		return true;
	}

	/**
	 * Checks whether required arguments are set sufficiently
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	protected function hasSufficientArguments() {
		return $this->stringNormalizer->trimWhitespace( $this->label ) !== ''
			|| $this->stringNormalizer->trimWhitespace( $this->description ) !== '';
	}

	/**
	 * Create entity content
	 *
	 * @since 0.1
	 *
	 * @return EntityContent Created entity content of correct subtype
	 */
	abstract protected function createEntityContent();

	/**
	 * Attempt to modify entity
	 *
	 * @since 0.1
	 *
	 * @param EntityContent &$entity
	 *
	 * @return Status
	 */
	protected function modifyEntity( EntityContent &$entity ) {
		$lang = $this->getLanguage()->getCode();
		if ( $this->label !== '' ) {
			$entity->getEntity()->setLabel( $lang, $this->label );
		}
		if ( $this->description !== '' ) {
			$entity->getEntity()->setDescription( $lang, $this->description );
		}
		return \Status::newGood();
	}

	/**
	 * Build additional formelements
	 *
	 * @since 0.1
	 *
	 * @return string Formatted HTML for inclusion in the form
	 */
	protected function additionalFormElements() {
		global $wgLang;
		return Html::element(
			'label',
			array(
				'for' => 'wb-newentity-label',
				'class' => 'wb-label'
			),
			$this->msg( 'wikibase-newentity-label' )->text()
		)
		. Html::input(
			'label',
			$this->label ? $this->label : '',
			'text',
			array(
				'id' => 'wb-newentity-label',
				'size' => 12,
				'class' => 'wb-input',
				'lang' => $wgLang->getCode(),
				'dir' => $wgLang->getDir(),
			)
		)
		. Html::element( 'br' )
		. Html::element(
			'label',
			array(
				'for' => 'wb-newentity-description',
				'class' => 'wb-label'
			),
			$this->msg( 'wikibase-newentity-description' )->text()
		)
		. Html::input(
			'description',
			$this->description ? $this->description : '',
			'text',
			array(
				'id' => 'wb-newentity-description',
				'size' => 36,
				'class' => 'wb-input',
				'lang' => $wgLang->getCode(),
				'dir' => $wgLang->getDir(),
			)
		)
		. Html::element( 'br' );
	}

	/**
	 * Building the HTML form for creating a new item.
	 *
	 * @since 0.1
	 *
	 * @param string|null $legend initial value for the label input box
	 * @param string $additionalHtml initial value for the description input box
	 */
	public function createForm( $legend = null, $additionalHtml = '' ) {
		$this->addCopyrightText();

		$this->getOutput()->addHTML(
				Html::openElement(
					'form',
					array(
						'method' => 'post',
						'action' => $this->getPageTitle()->getFullUrl(),
						'name' => 'newentity',
						'id' => 'mw-newentity-form1',
						'class' => 'wb-form'
					)
				)
				. Html::openElement(
					'fieldset',
					array( 'class' => 'wb-fieldset' )
				)
				. Html::element(
					'legend',
					array( 'class' => 'wb-legend' ),
					$legend
				)
				. Html::hidden(
					'token',
					$this->getUser()->getEditToken()
				)
				. $additionalHtml
				. Html::input(
					'submit',
					$this->msg( 'wikibase-newentity-submit' )->text(),
					'submit',
					array(
						'id' => 'wb-newentity-submit',
						'class' => 'wb-button'
					)
				)
				. Html::closeElement( 'fieldset' )
				. Html::closeElement( 'form' )
		);
	}

	/**
	 * @todo could factor this out into a special page form builder and renderer
	 */
	protected function addCopyrightText() {
		$copyrightView = new SpecialPageCopyrightView(
			new CopyrightMessageBuilder(),
			$this->rightsUrl,
			$this->rightsText
		);

		$html = $copyrightView->getHtml( $this->getLanguage() );

		$this->getOutput()->addHTML( $html );
	}

	/**
	 * Get legend
	 *
	 * @since 0.1
	 *
	 * @return string Legend for the fieldset
	 */
	abstract protected function getLegend();

	/**
	 * Returns any warnings.
	 *
	 * @since 0.4
	 *
	 * @return string[] Warnings that should be presented to the user
	 */
	abstract protected function getWarnings();

	/**
	 * Output an error message telling the user that he is blocked
	 * @throws UserBlockedError
	 */
	function displayBlockedError() {
		throw new UserBlockedError( $this->getUser()->getBlock() );
	}

	/**
	 * Checks if user is blocked, and if he is blocked throws a UserBlocked
	 *
	 * @since 0.4
	 */
	public function checkBlocked() {
		if ( $this->getUser()->isBlocked() ) {
			$this->displayBlockedError();
		}
	}

}
