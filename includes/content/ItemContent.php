<?php

namespace Wikibase;

use InvalidArgumentException;
use Language;
use LogicException;
use MWException;
use Title;
use Wikibase\Lib\Store\EntityRedirect;
use Wikibase\Repo\ItemSearchTextGenerator;
use Wikibase\Repo\View\ClaimsView;
use Wikibase\Repo\View\FingerprintView;

/**
 * Content object for articles representing Wikibase items.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ItemContent extends EntityContent {

	/**
	 * For use in the wb-status page property to indicate that the entity is a "linkstub",
	 * that is, it contains sitelinks, but no claims.
	 *
	 * @see getEntityStatus()
	 */
	const STATUS_LINKSTUB = 60;

	/**
	 * @var Item
	 */
	private $item;

	/**
	 * @var EntityRedirect
	 */
	private $redirect;

	/**
	 * @var Title
	 */
	private $redirectTitle;

	/**
	 * Do not use to construct new stuff from outside of this class,
	 * use the static newFoobar methods.
	 *
	 * In other words: treat as protected (which it was, but now cannot
	 * be since we derive from Content).
	 *
	 * @param Item|null $item
	 * @param EntityRedirect|null $entityRedirect
	 * @param Title|null $redirectTitle
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		Item $item = null,
		EntityRedirect $entityRedirect = null,
		Title $redirectTitle = null
	) {
		parent::__construct( CONTENT_MODEL_WIKIBASE_ITEM );

		if ( is_null( $item ) === is_null( $entityRedirect ) ) {
			throw new InvalidArgumentException(
				'Either $item or $entityRedirect and $redirectTitle must be provided.' );
		}

		if ( is_null( $entityRedirect ) !== is_null( $redirectTitle ) ) {
			throw new InvalidArgumentException(
				'$entityRedirect and $redirectTitle must both be provided or both be empty.' );
		}

		if ( $redirectTitle !== null
			&& $redirectTitle->getContentModel() !== CONTENT_MODEL_WIKIBASE_ITEM
		) {
			if ( $redirectTitle->exists() ) {
				throw new InvalidArgumentException(
					'$redirectTitle must refer to a page with content model '
					. CONTENT_MODEL_WIKIBASE_ITEM );
			}
		}

		$this->item = $item;
		$this->redirect = $entityRedirect;
		$this->redirectTitle = $redirectTitle;
	}

	/**
	 * Create a new ItemContent object for the provided Item.
	 *
	 * @param Item $item
	 *
	 * @return ItemContent
	 */
	public static function newFromItem( Item $item ) {
		return new static( $item );
	}

	/**
	 * Create a new ItemContent object representing a redirect to the given item ID.
	 *
	 * @since 0.5
	 *
	 * @param EntityRedirect $redirect
	 * @param Title $redirectTitle
	 *
	 * @return ItemContent
	 */
	public static function newFromRedirect( EntityRedirect $redirect, Title $redirectTitle ) {
		return new static( null, $redirect, $redirectTitle );
	}

	/**
	 * @see Content::getRedirectTarget
	 *
	 * @return null|Title
	 */
	public function getRedirectTarget() {
		return $this->redirectTitle;
	}

	/**
	 * @see EntityContent::getEntityRedirect
	 *
	 * @return null|EntityRedirect
	 */
	public function getEntityRedirect() {
		return $this->redirect;
	}

	/**
	 * Returns the Item that makes up this ItemContent.
	 *
	 * @throws MWException when it's a redirect (targets will never be resolved)
	 * @throws LogicException
	 * @return Item
	 */
	public function getItem() {
		$redirect = $this->getRedirectTarget();

		if ( $redirect ) {
			throw new MWException( 'Unresolved redirect to [[' . $redirect->getFullText() . ']]' );
		}

		if ( !$this->item ) {
			throw new LogicException( 'Neither redirect nor item found in ItemContent!' );
		}

		return $this->item;
	}

	/**
	 * Returns a new empty ItemContent.
	 *
	 * @return ItemContent
	 */
	public static function newEmpty() {
		return new static( Item::newEmpty() );
	}

	/**
	 * @see EntityContent::getEntity
	 *
	 * @throws MWException when it's a redirect (targets will never be resolved)
	 * @return Item
	 */
	public function getEntity() {
		return $this->getItem();
	}

	/**
	 * @see EntityContent::getTextForSearchIndex()
	 */
	public function getTextForSearchIndex() {
		if ( $this->isRedirect() ) {
			return '';
		}

		wfProfileIn( __METHOD__ );

		// TODO: refactor ItemSearchTextGenerator to share an interface with EntitySearchTextGenerator,
		// so we don't have to re-implement getTextForSearchIndex() here.
		$searchTextGenerator = new ItemSearchTextGenerator();
		$text = $searchTextGenerator->generate( $this->getItem() );

		if ( !wfRunHooks( 'WikibaseTextForSearchIndex', array( $this, &$text ) ) ) {
			return '';
		}

		wfProfileOut( __METHOD__ );
		return $text;
	}

	/**
	 * @see getEntityView()
	 *
	 * @param FingerprintView $fingerprintView
	 * @param ClaimsView $claimsView
	 * @param Language $language
	 *
	 * @return ItemView
	 */
	protected function newEntityView(
		FingerprintView $fingerprintView,
		ClaimsView $claimsView,
		Language $language
	) {
		return new ItemView( $fingerprintView, $claimsView, $language );
	}

	/**
	 * @see EntityContent::getEntityPageProperties
	 *
	 * Records the number of sitelinks in the 'wb-sitelinks' key.
	 *
	 * @return array A map from property names to property values.
	 */
	public function getEntityPageProperties() {
		if ( $this->isRedirect() ) {
			return array();
		}

		$properties = parent::getEntityPageProperties();
		$properties['wb-sitelinks'] = $this->getItem()->getSiteLinkList()->count();

		return $properties;
	}

	/**
	 * @see EntityContent::getEntityStatus()
	 *
	 * An item is considered a stub if it has terms but no statements or sitelinks.
	 * If an item has sitelinks but no statements, it is considered a "linkstub".
	 * If an item has statements, it's not empty nor a stub.
	 *
	 * @see STATUS_LINKSTUB
	 *
	 * @note Will fail of this ItemContent is a redirect.
	 *
	 * @return int
	 */
	public function getEntityStatus() {
		$status = parent::getEntityStatus();
		$hasSiteLinks = !$this->getItem()->getSiteLinkList()->isEmpty();

		if ( $status === self::STATUS_EMPTY && $hasSiteLinks ) {
			$status = self::STATUS_LINKSTUB;
		} else if ( $status === self::STATUS_STUB && $hasSiteLinks ) {
			$status = self::STATUS_LINKSTUB;
		}

		return $status;
	}

}
