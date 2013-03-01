<?php

namespace Wikibase\Api;

use ApiBase;

use Wikibase\Entity;
use Wikibase\EntityContent;
use Wikibase\Utils;

/**
 * API module for the language attributes for a Wikibase entity.
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
class SetDescription extends ModifyLangAttribute {

	/**
	 * @see  \Wikibase\Api\ModifyEntity::getRequiredPermissions()
	 */
	protected function getRequiredPermissions( Entity $entity, array $params ) {
		$permissions = parent::getRequiredPermissions( $entity, $params );

		$permissions[] = ( isset( $params['value'] ) && 0<strlen( $params['value'] ) )
			? 'description-update'
			: 'description-remove';
		return $permissions;
	}

	/**
	 * @see \Wikibase\Api\ModifyEntity::modifyEntity()
	 */
	protected function modifyEntity( EntityContent &$entityContent, array $params ) {
		wfProfileIn( __METHOD__ );

		if ( isset( $params['value'] ) ) {
			$description = Utils::trimToNFC( $params['value'] );
			$language = $params['language'];
			if ( 0 < strlen( $description ) ) {
				$descriptions = array( $language => $entityContent->getEntity()->setDescription( $language, $description ) );
			}
			else {
				$entityContent->getEntity()->removeDescription( $language );
				$descriptions = array( $language => '' );
			}

			$this->addDescriptionsToResult( $descriptions, 'entity' );
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * @see \ApiBase::getDescription()
	 */
	public function getDescription() {
		return array(
			'API module to set a description for a single Wikibase entity.'
		);
	}

	/**
	 * @see \ApiBase::getExamples()
	 */
	protected function getExamples() {
		return array(
			'api.php?action=wbsetdescription&id=42&language=en&value=An%20encyclopedia%20that%20everyone%20can%20edit'
				=> 'Set the string "An encyclopedia that everyone can edit" for page with id "42" as a decription in English language',
		);
	}

	/**
	 * @see \ApiBase::getHelpUrls()
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:Wikibase/API#wbsetdescription';
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
