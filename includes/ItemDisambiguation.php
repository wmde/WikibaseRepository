<?php

namespace Wikibase;
use IContextSource, MWException;

/**
 * Class representing the disambiguation of a list of WikibaseItems.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ItemDisambiguation extends \ContextSource {

	/**
	 * @since 0.1
	 * @var Item
	 */
	protected $items;

	protected $langCode;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param $items array of ItemContent
	 * @param string $langCode
	 * @param $context IContextSource|null
	 *
	 * @thorws MWException
	 */
	public function __construct( array $items, $langCode, IContextSource $context = null ) {
		$this->items = $items;
		$this->langCode = $langCode;

		if ( !is_null( $context ) ) {
			$this->setContext( $context );
		}
	}

	/**
	 * Builds and returns the HTML to represent the WikibaseItem.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getHTML() {
		$userLang = $this->getLanguage()->getCode();
		$searchLang = $this->langCode;

		return
			'<ul class="wikibase-disambiguation">' .
				implode( '', array_map(
					function( ItemContent $item ) use ( $userLang, $searchLang ) {

						$userLabel = $item->getItem()->getLabel( $userLang );
						$searchLabel = $item->getItem()->getLabel( $searchLang );

						// FIXME: Need a more general way to figure out the "q" thingy.
						// This should REALLY be something more elegant, but it is sufficient for now.
						$idLabel = \Html::openElement( 'span', array( 'class' => 'wb-itemlink-id' ) )
							. wfMessage( 'wikibase-itemlink-id-wrapper' )->params( $item->getTitle()->getText() )->escaped()
							. \Html::closeElement( 'span' );

						// link to the item. The text is the label in the user's language, or the id if no label exists
						$result =
							\Linker::link(
								$item->getTitle(),
								$userLabel ? htmlspecialchars( $userLabel ) : $idLabel
							);

						// display the label in the searched language in case it is different than in the user language
						if ( ( $userLang !== $searchLang ) && ( $userLabel !== $searchLabel ) ) {
							$result = $result
									// really ugly way to add parenthesis... ;/
									. ' ('
									. \Language::fetchLanguageName( $searchLang, $userLang )
									. wfMessage( 'colon-separator' )->escaped()
									. \Html::element(
										'span',
										array( 'class' => 'wb-itemlink-query-lang', 'lang' => $searchLang ),
										$searchLabel )
									. ')'
									;
						};

						// display the description in the user's language
						$description = htmlspecialchars( $item->getItem()->getDescription( $userLang ) );
						if ( $description === "" ) {
							// Display the ID if no description is available
							// do not display it if the ID was already displayed, i.e. if it was used instead of the label previously
							$result .= $userLabel ? ' ' . $idLabel : '';
						} else {
							$result .=
								wfMessage( 'colon-separator' )->escaped()
								. \Html::openElement( 'span', array( 'class' => 'wb-itemlink-description' ) )
								. $description
								. \Html::closeElement( 'span' );
						}

						$result = \Html::rawElement( 'li', array( 'class' => 'wikibase-disambiguation' ), $result );
						return $result;
					},
					$this->items
				) ).
			'</ul>';
	}

	/**
	 * Display the item using the set context.
	 *
	 * @since 0.1
	 */
	public function display() {
		$this->getOutput()->addHTML( $this->getHTML() );
	}

}
