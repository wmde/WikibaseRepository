<?php

namespace Wikibase\Test;

use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpAliases;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\ItemContent;

/**
 * @covers Wikibase\ChangeOp\ChangeOpAliases
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpAliasesTest extends \PHPUnit_Framework_TestCase {


	private function getTermValidatorFactory() {
		$mockProvider = new ChangeOpTestMockProvider( $this );
		return $mockProvider->getMockTermValidatorFactory();
	}

	public function invalidConstructorProvider() {
		$args = array();
		$args[] = array( 42, array( 'myNewAlias' ), 'add' );
		$args[] = array( 'en', array( 'myNewAlias' ), 1234 );

		return $args;
	}

	/**
	 * @dataProvider invalidConstructorProvider
	 * @expectedException InvalidArgumentException
	 *
	 * @param string $language
	 * @param string[] $aliases
	 * @param string $action
	 */
	public function testInvalidConstruct( $language, $aliases, $action ) {
		// "INVALID" is invalid
		$validatorFactory = $this->getTermValidatorFactory();

		new ChangeOpAliases( $language, $aliases, $action, $validatorFactory );
	}

	public function changeOpAliasesProvider() {
		// "INVALID" is invalid
		$validatorFactory = $this->getTermValidatorFactory();

		$enAliases = array( 'en-alias1', 'en-alias2', 'en-alias3' );
		$existingEnAliases = array ( 'en-existingAlias1', 'en-existingAlias2' );
		$item = ItemContent::newEmpty();
		$entity = $item->getEntity();
		$entity->setAliases( 'en', $existingEnAliases );

		$args = array();
		$args[] = array ( clone $entity, new ChangeOpAliases( 'en', $enAliases, 'add', $validatorFactory ), array_merge( $existingEnAliases, $enAliases ) );
		$args[] = array ( clone $entity, new ChangeOpAliases( 'en', $enAliases, 'set', $validatorFactory ), $enAliases );
		$args[] = array ( clone $entity, new ChangeOpAliases( 'en', $enAliases, '', $validatorFactory ), $enAliases );
		$args[] = array ( clone $entity, new ChangeOpAliases( 'en', $existingEnAliases, 'remove', $validatorFactory ), array() );

		return $args;
	}

	/**
	 * @dataProvider changeOpAliasesProvider
	 *
	 * @param Entity $entity
	 * @param ChangeOpAliases $changeOpAliases
	 * @param string $expectedAliases
	 */
	public function testApply( $entity, $changeOpAliases, $expectedAliases ) {
		$changeOpAliases->apply( $entity );
		$this->assertEquals( $expectedAliases, $entity->getAliases( 'en' ) );
	}

	public function validateProvider() {
		// "INVALID" is invalid
		$validatorFactory = $this->getTermValidatorFactory();

		$args = array();
		$args['set invalid alias'] = array ( new ChangeOpAliases( 'fr', array( 'INVALID' ), 'set', $validatorFactory ) );
		$args['add invalid alias'] = array ( new ChangeOpAliases( 'fr', array( 'INVALID' ), 'add', $validatorFactory ) );
		$args['set invalid language'] = array ( new ChangeOpAliases( 'INVALID', array( 'valid' ), 'set', $validatorFactory ) );
		$args['add invalid language'] = array ( new ChangeOpAliases( 'INVALID', array( 'valid' ), 'add', $validatorFactory ) );
		$args['remove invalid language'] = array ( new ChangeOpAliases( 'INVALID', array( 'valid' ), 'remove', $validatorFactory ) );

		return $args;
	}

	/**
	 * @dataProvider validateProvider
	 *
	 * @param ChangeOp $changeOp
	 */
	public function testValidate( ChangeOp $changeOp ) {
		$entity = Item::newEmpty();

		$result = $changeOp->validate( $entity );
		$this->assertFalse( $result->isValid() );
	}

	public function testApplyWithInvalidAction() {
		$entity = Item::newEmpty();
		$validatorFactory = $this->getTermValidatorFactory();

		$changeOpAliases = new ChangeOpAliases( 'en', array( 'test' ), 'invalidAction', $validatorFactory );

		$this->setExpectedException( 'Wikibase\ChangeOp\ChangeOpException' );
		$changeOpAliases->apply( $entity );
	}

}
