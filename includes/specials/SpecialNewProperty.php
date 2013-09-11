<?php

namespace Wikibase\Repo\Specials;

use Html;
use Status;
use Wikibase\DataTypeSelector;
use Wikibase\PropertyContent;
use Wikibase\EntityContent;
use Wikibase\Repo\WikibaseRepo;

/**
 * Page for creating new Wikibase properties.
 *
 * @since 0.2
 * @licence GNU GPL v2+
 * @author John Erling Blad < jeblad@gmail.com >
 */
class SpecialNewProperty extends SpecialNewEntity {

	/**
	 * @since 0.2
	 *
	 * @var string|null
	 */
	protected $dataType = null;

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 */
	public function __construct() {
		parent::__construct( 'NewProperty', 'property-create' );
	}

	/**
	 * @see SpecialNewEntity::prepareArguments()
	 */
	protected function prepareArguments() {
		parent::prepareArguments();
		$this->dataType = $this->getRequest()->getVal( 'datatype', isset( $this->parts[2] ) ? $this->parts[2] : '' );
		return true;
	}

	/**
	 * @see SpecialNewEntity::hasSufficientArguments()
	 */
	protected function hasSufficientArguments() {
		// TODO: Needs refinement
		return parent::hasSufficientArguments() && ( $this->dataType !== '' );
	}

	/**
	 * @see SpecialNewEntity::createEntityContent
	 */
	protected function createEntityContent() {
		return PropertyContent::newEmpty();
	}

	/**
	 * @see SpecialNewEntity::modifyEntity()
	 *
	 * @param EntityContent $propertyContent
	 *
	 * @return Status
	 */
	protected function modifyEntity( EntityContent &$propertyContent ) {
		/**
		 * @var PropertyContent $propertyContent
		 */
		$status = parent::modifyEntity( $propertyContent );

		if ( $this->dataType !== '' ) {
			if ( $this->dataTypeExists() ) {
				$propertyContent->getProperty()->setDataTypeId( $this->dataType );
			}
			else {
				$status->fatal( 'wikibase-newproperty-invalid-datatype' );
			}
		}

		return $status;
	}

	protected function dataTypeExists() {
		$dataTypeFactory = WikibaseRepo::getDefaultInstance()->getDataTypeFactory();
		return $dataTypeFactory->getType( $this->dataType ) !== null;
	}

	/**
	 * @see SpecialNewEntity::additionalFormElements()
	 */
	protected function additionalFormElements() {
		$dataTypeFactory = WikibaseRepo::getDefaultInstance()->getDataTypeFactory();

		$selector = new DataTypeSelector( $dataTypeFactory->getTypes(), $this->getLanguage()->getCode() );

		return parent::additionalFormElements()
			. Html::element(
				'label',
				array(
					'for' => 'wb-newproperty-datatype',
					'class' => 'wb-label'
				),
				$this->msg( 'wikibase-newproperty-datatype' )->text()
			)
			. $selector->getHtml( 'wb-newproperty-datatype' )
			. Html::element( 'br' );
	}

	/**
	 * @see SpecialNewEntity::getLegend()
	 */
	protected function getLegend() {
		return $this->msg( 'wikibase-newproperty-fieldset' );
	}

	/**
	 * @see SpecialCreateEntity::getWarnings()
	 */
	protected function getWarnings() {
		$warnings = array();

		if ( $this->getUser()->isAnon() ) {
			$warnings[] = $this->msg(
				'wikibase-anonymouseditwarning',
				$this->msg( 'wikibase-entity-property' )
			);
		}

		return $warnings;
	}

}
