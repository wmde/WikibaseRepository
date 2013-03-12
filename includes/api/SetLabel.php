<?php

namespace Wikibase\Api;

use ApiBase;

use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\Utils;

/**
 * API module to set the label for a Wikibase entity.
 * Requires API write mode to be enabled.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseRepo
 * @ingroup API
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 */
class SetLabel extends ModifyLangAttribute {

	/**
	 * @see \Wikibase\Api\ModifyEntity::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Entity $entity, array $params ) {
		$permissions = parent::getRequiredPermissions( $entity, $params );

		$permissions[] = ( isset( $params['value'] ) && 0<strlen( $params['value'] ) )
			? 'label-update'
			: 'label-remove';
		return $permissions;
	}

	/**
	 * @see \Wikibase\Api\ModifyEntity::modifyEntity()
	 */
	protected function modifyEntity( EntityContent &$entityContent, array $params ) {
		wfProfileIn( __METHOD__ );
		$summary = $this->createSummary( $params );

		if ( isset( $params['value'] ) ) {

			$label = Utils::trimToNFC( $params['value'] );
			$language = $params['language'];
			if ( 0 < strlen( $label ) ) {
				$summary->addAutoSummaryArgs( $label );
				$labels = array( $language => $entityContent->getEntity()->setLabel( $language, $label ) );
			}
			else {
				$old = $entityContent->getEntity()->getLabel( $language );
				$summary->addAutoSummaryArgs( $old );

				$entityContent->getEntity()->removeLabel( $language );
				$labels = array( $language => '' );
			}

			$this->addLabelsToResult( $labels, 'entity' );
		}

		wfProfileOut( __METHOD__ );
		return $summary;
	}

	/**
	 * @see \ApiBase::getDescription()
	 */
	public function getDescription() {
		return array(
			'API module to set a label for a single Wikibase entity.'
		);
	}

	/**
	 * @see \ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetlabel&id=42&language=en&value=Wikimedia&format=jsonfm'
				=> 'Set the string "Wikimedia" for page with id "42" as a label in English language and report it as pretty printed json',
		);
	}

	/**
	 * @see \ApiBase::getHelpUrls()
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetlabel';
	}

	/**
	 * @see \ApiBase::getVersion
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getVersion() {
		return __CLASS__ . '-' . WB_VERSION;
	}

}
