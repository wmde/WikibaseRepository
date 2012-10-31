<?php

namespace Wikibase;
use Html, ParserOutput, Title, Language, OutputPage, Sites, MediaWikiSite;

/**
 * Class for creating views for Wikibase\Property instances.
 * For the Wikibase\Property this basically is what the Parser is for WikitextContent.
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
 * @author Daniel Werner
 * @author H. Snater < mediawiki@snater.com >
 */
class PropertyView extends EntityView {

	const VIEW_TYPE = 'property';

	/**
	 * @see EntityView::getInnerHtml
	 *
	 * @param EntityContent $property
	 * @param \Language|null $lang
	 * @param bool $editable
	 * @return string
	 */
	public function getInnerHtml( EntityContent $property, Language $lang = null, $editable = true ) {
		$html = parent::getInnerHtml( $property, $lang, $editable );

		// add data value to default entity stuff
		/** @var PropertyContent $property */
		$html .= $this->getHtmlForDataType( $property->getProperty()->getDataType(), $lang, $editable );
		// TODO: figure out where to display type information more nicely

		return $html;
	}

	/**
	 * Builds and returns the HTML representing a property entity's data type information.
	 *
	 * @since 0.1
	 *
	 * @param \DataTypes\DataType $dataType the data type to render
	 * @param \Language|null $lang the language to use for rendering. if not given, the local context will be used.
	 * @param bool $editable whether editing is allowed (enabled edit links)
	 * @return string
	 */
	public function getHtmlForDataType( \DataTypes\DataType $dataType, Language $lang = null, $editable = true ) {
		if( $lang === null ) {
			$lang = $this->getLanguage();
		}
		return wfTemplate( 'wb-property-datatype',
			wfMessage( 'wikibase-datatype-label' )->text(),
			$dataType->getLabel( $lang->getCode() )
		);
	}
}
