/**
 * QUnit tests for editable value component of property edit tool
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater
 * @author Daniel Werner
 */

( function( mw, wb, $, QUnit, undefined ) {
	'use strict';

var
	/**
	 * Enum with API error responses useful for testing
	 * @var Object
	 */
	API_ERRORS = {
		NO_PERMISSION: {
			'error': {
				'code': 'no-permissions',
				'info': 'The logged in user does not have sufficient rights'
			}
		},
		FAKE_ERROR: {
			'error': {
				'code': 'some-nonexistant-error-code',
				'info': 'This error code should not be defined.'
			}
		},
		CLIENT_ERROR: {
			'error': {
				'code': 'client-error',
				'info': 'The connection to the client page failed.'
			}
		}
	},
	DATAVALUES = {
		// NOTE: all values inside arrays because EditableValue.setValue()/getValue() handle arrays sincer
		// there is no DataValue object yet. This is important in SiteLink for example where the value contains
		// out of two strings/objects
		VALID: [ [ 'test' ], [ 'test 2' ] ],
		INVALID: [ [ '' ] ],
		EMPTY: [ [ '' ] ]
	},
	/**
	 * Factory for creating a new editable value with certain additional functionality suited for testing.
	 *
	 * @param jQuery subject
	 * @param Object overwrites allows to give properties which will be overwritten in the fabricated EditableValue
	 * @return wb.ui.PropertyEditTool.EditableValue
	 */
	newTestEV = function( subject, overwrites ) {
		var propertyEditTool = new wb.ui.PropertyEditTool( subject ), // required for creating suited toolbar
			editableValue = new wb.ui.PropertyEditTool.EditableValue(),
			toolbar = propertyEditTool._buildSingleValueToolbar( editableValue );

		// add functions for testing:
		/**
		 * Calling this function will manipulate the EditableValue so all API related actions will trigger an error
		 *
		 * @param error json describing an error as returned by the API. Can be one of API_ERRORS. By default, if
		 *        nothing is set explicitly, this falls back to API_ERRORS.NO_PERMISSION
		 */
		editableValue.simulateApiFailure = function( error ) {
			error = error || API_ERRORS.NO_PERMISSION;
			editableValue.queryApi = function( deferred, apiAction ) {
				deferred.reject( 'error', error ).promise();
			};
		};

		/**
		 * Calling this function will manipulate the EditableValue so all API related actions will trigger success.
		 */
		editableValue.simulateApiSuccess = function() {
			editableValue.queryApi = function( deferred, apiAction ) { // override AJAX API call
				deferred.resolve( {} ).promise();
			};
		};

		// overwrite _getValueFromApiResponse so save() gets some server sided validated value from fake API call.
		// TODO: this is not nice! Some better way to do this should be investigated.
		editableValue._getValueFromApiResponse = function() {
			return this.getValue();
		};

		// apply options or other overwrites:
		$.extend( editableValue, overwrites || {} );

		editableValue.simulateApiSuccess(); // make sure we won't ever do any API requests from the beginning!

		// initialize:
		editableValue.init( subject, toolbar );

		return editableValue;
	};

	QUnit.module( 'wikibase.ui.PropertyEditTool.EditableValue', QUnit.newWbEnvironment( {
		setup: function() {},
		teardown: function() {}
	} ) );

	QUnit.test( 'test helper functions for testing EditableValue properly', function( assert ) {
		var subject = $( '<div id="parent"><div class="wb-value"/></div>' ),
			ev = newTestEV( subject );

		assert.ok(
			ev instanceof wb.ui.PropertyEditTool.EditableValue,
			'EditableValue test factory returned sufficient instance'
		);

		QUnit.assert.equal(
			ev.getSubject()[0],
			subject[0],
			'verified subject node of new EditableValue'
		);

		QUnit.assert.equal(
			ev._getToolbarParent().parent().attr( 'id' ),
			'parent',
			'parent node for toolbar exists'
		);

		QUnit.assert.ok(
			ev._interfaces.length === 1
				&& ev._interfaces[0] instanceof wb.ui.PropertyEditTool.EditableValue.Interface,
			'initialized one interface'
		);

		// have to change value because if value isNew and empty, there will be no API call
		ev.setValue( DATAVALUES.VALID[0] );
		ev.simulateApiFailure();

		QUnit.assert.equal(
			ev.remove().state(),
			'rejected',
			'simulateApiFailure() we use for simulating failures of API related functions works'
		);

		ev.setValue( '' ); // reset to initial value

		ev.simulateApiSuccess(); // initial state by default api actions in our tests are a success!

		QUnit.assert.equal(
			ev.save().state(),
			'resolved',
			'simulateApiFailure() we use for simulating success of API related functions works'
		);

		// tests after destroy()
		ev.destroy();

		QUnit.assert.equal(
			ev.getToolbar(),
			null,
			'destroyed toolbar'
		);

		QUnit.assert.equal(
			ev._instances,
			null,
			'destroyed instances'
		);
	} );

	QUnit.test( 'initial check', function( assert ) {
		var ev = newTestEV( $( '<div><div class="wb-value"/></div>' ) );

		assert.equal(
			ev.getInputHelpMessage(),
			'',
			'checked help message'
		);

		assert.equal(
			ev.isPending(), // see todo in EditableValue. This behaves strange, use isNew()
			false,
			'value is not pending'
		);

		assert.equal(
			ev.isNew(),
			true,
			'value is new'
		);

		assert.equal(
			ev.isEmpty(),
			true,
			'value is empty'
		);

		ev.stopEditing( true ); // TODO: test returned promise
		assert.ok(
			ev.isInEditMode(),
			'can\'t go out of edit mode if empty (no) value'
		);

		assert.equal(
			ev.valueCompare( ev.getValue(), '' ),
			true,
			'value is empty string'
		);

		assert.equal(
			ev.isInEditMode(),
			true,
			'in edit mode initially because empty string is initial value'
		);

		assert.ok(
			ev.getToolbar() instanceof wb.ui.Toolbar,
			'instantiated toolbar'
		);

	} );

	QUnit.test( 'dis- and enabling', function( assert ) {
		var ev = newTestEV( $( '<div><div class="wb-value"/></div>' ) );

		assert.equal(
			ev.getState(),
			ev.STATE.MIXED,
			'Mixed state in the beginning because in edit mode (since empty value)'
		);

		assert.equal(
			ev.enable(),
			false,
			'can\'t fully enable because of empty value, toolbar stays disabled'
		);

		assert.ok(
			ev.getToolbar().isDisabled(),
			'Toolbar is disabled'
		);

		ev.setValue( DATAVALUES.VALID[0] );
		assert.equal(
			ev.isEnabled(),
			true,
			'is fully enabled after valid value was set'
		);

		assert.equal(
			ev.isDisabled(),
			false,
			'not disabled'
		);

		assert.ok(
			ev.disable() === true && ev.isDisabled() === true,
			'disabled successfully'
		);

		assert.equal(
			ev.isEnabled(),
			false,
			'not enabled'
		);

		assert.ok(
			ev.getToolbar().enable() === true && ev.getToolbar().isEnabled() === true,
			'enabled toolbar successfully'
		);

		assert.equal(
			ev.getState(),
			wb.ui.StateExtension.prototype.STATE.MIXED,
			'mixed state'
		);

		assert.equal(
			ev.isDisabled(),
			false,
			'not disabled'
		);

		assert.equal(
			ev.isEnabled(),
			false,
			'not enabled'
		);

		assert.equal(
			ev.enable(),
			true,
			'enabling'
		);

		assert.equal(
			ev.isEnabled(),
			true,
			'is enabled'
		);

		assert.equal(
			ev.isDisabled(),
			false,
			'is not disabled'
		);

	} );

	QUnit.test( 'edit', function( assert ) {
		var ev = newTestEV( $( '<div><div class="wb-value"/></div>' ) );

		assert.equal(
			ev.startEditing(),
			false,
			'EditableValue is in edit mode initially, so startEditing() returns false'
		);

		assert.equal(
			ev.isInEditMode(),
			true,
			'is in edit mode'
		);

		ev.setValue( DATAVALUES.VALID[0] ); // set value in edit mode
		assert.ok(
			ev.getValue() instanceof Array
				&& ev.valueCompare( ev.getValue(), DATAVALUES.VALID[0] ),
			'changed value'
		);

		assert.equal(
			ev.stopEditing( false ).promisor.apiAction, // leave edit mode, don't save value
			wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION.NONE,
			'stopped edit mode without saving value'
		);

		assert.ok(
			!ev.valueCompare( ev.getValue(), DATAVALUES.VALID[0] ),
			'value not saved after leaving edit mode without saving value'
		);

		assert.equal(
			ev.stopEditing( false ).promisor.apiAction,
			wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION.NONE,
			'stop edit mode again, do not save, no API action performed'
		);

		assert.equal(
			ev.preserveEmptyForm === true && ev.isInEditMode() === true,
			true,
			'still in edit mode because \'preserveEmptyForm\' option is set and no value (empty)'
		);

		ev.setValue( DATAVALUES.VALID[0] );

		assert.ok(
			ev.getValue() instanceof Array
				&& ev.valueCompare( ev.getValue(), DATAVALUES.VALID[0] ),
			'changed value'
		);

		assert.equal(
			ev.stopEditing( true ).promisor.apiAction,
			wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION.SAVE,
			'stopped edit mode, save'
		);

		assert.equal(
			ev.isInEditMode(),
			false,
			'is not in edit mode'
		);

		ev.setValue( DATAVALUES.VALID[1] ); // initial value when starting edit mode

		assert.ok(
			ev.getValue() instanceof Array
				&& ev.valueCompare( ev.getValue(), DATAVALUES.VALID[1] ),
			'changed value'
		);

		assert.equal(
			ev.startEditing(),
			true,
			'started edit mode'
		);

		assert.equal(
			ev.startEditing(),
			false,
			'try to start edit mode again'
		);

		assert.equal(
			ev.validate( DATAVALUES.INVALID[0] ),
			false,
			'empty value not validated'
		);

		assert.equal(
			ev.validate( DATAVALUES.VALID[0] ),
			true,
			'validated input'
		);

		ev.setValue( DATAVALUES.EMPTY[0] );
		assert.ok(
			ev.valueCompare( ev.getValue(), DATAVALUES.EMPTY[0] ),
			'set empty value'
		);

		assert.equal(
			ev.isEmpty(),
			true,
			'editable value is empty'
		);

		assert.ok(
			ev.valueCompare( ev.getInitialValue(), DATAVALUES.VALID[1] ),
			'remembers the right initial value'
		);

		assert.equal(
			ev.valueCompare( ev.getValue(), ev.getInitialValue() ),
			false,
			'current (empty) and initial value aren\'t equal'
		);

		assert.ok(
			ev.valueCompare( ev.setValue( DATAVALUES.VALID[1] ), DATAVALUES.VALID[1] ),
			'reset value to initial value'
		);

		assert.ok(
			ev.valueCompare( ev.getValue(), ev.getInitialValue() ),
			true,
			'compared current and initial value'
		);

		ev.remove();
	} );

	QUnit.test( 'error handling', function( assert ) {
		var ev = newTestEV( $( '<div><div class="wb-value"/></div>' ) );

		ev.simulateApiFailure();

		ev.startEditing();
		ev.setValue( DATAVALUES.VALID[0] );

		assert.equal(
			ev.isInEditMode(),
			true,
			'started editing ans set value'
		);

		assert.equal(
			ev.stopEditing( true ).state(),
			'rejected',
			'save while leaving edit mode has failed'
		);

		assert.equal(
			ev.isInEditMode(),
			true,
			'is still in edit mode after receiving error'
		);

		assert.ok(
			ev._toolbar.editGroup.btnSave.getTooltip() instanceof wb.ui.Tooltip,
			'attached tooltip to save button'
		);

		ev.simulateApiSuccess();

		assert.equal(
			ev.stopEditing( true ).state(),
			'resolved',
			'save while leaving edit mode was successful'
		);

		assert.equal(
			ev.isInEditMode(),
			false,
			'cancelled editing'
		);

		ev.simulateApiFailure();

		ev.preserveEmptyForm = true;
		ev.remove();

		assert.equal(
			ev.getValue()[0],
			ev.getInitialValue()[0],
			'emptied input interface resetting to default value and preserving the input interface'
		);

		ev.preserveEmptyForm = false;
		ev.remove();

		assert.ok(
			ev._toolbar.editGroup.btnRemove.getTooltip() instanceof wb.ui.Tooltip,
			'attached tooltip to remove button after trying to remove with API action'
		);

		ev.simulateApiFailure( API_ERRORS.FAKE_ERROR );
		ev.startEditing();
		ev.setValue( DATAVALUES.VALID[0] );
		ev.stopEditing( true );
		assert.equal(
			ev._toolbar.editGroup.btnSave.getTooltip()._error.shortMessage,
			mw.msg( 'wikibase-error-save-generic' ),
			"when getting unknown error-code from API, generic message should be shown"
		);

		ev.simulateApiFailure( API_ERRORS.CLIENT_ERROR );
		ev.startEditing();
		ev.setValue( DATAVALUES.VALID[0] );
		ev.stopEditing( true );
		assert.equal(
			ev._toolbar.editGroup.btnSave.getTooltip()._error.shortMessage,
			mw.msg( 'wikibase-error-ui-client-error' ),
			"when getting an registered error-code from API, the corresponding message should be shown"
		);

	} );

}( mediaWiki, wikibase, jQuery, QUnit ) );
