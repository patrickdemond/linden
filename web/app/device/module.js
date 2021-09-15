define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'device', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'device',
      plural: 'devices',
      possessive: 'device\'s'
    },
    columnList: {
      name: {
        title: 'Name',
        column: 'device.name'
      },
      url: {
        title: 'URL',
        column: 'device.url'
      }
    },
    defaultOrder: {
      column: 'device.name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      title: 'Name',
      type: 'string'
    },
    url: {
      title: 'URL',
      type: 'string'
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Test Connection',
    operation: async function( $state, model ) {
      try {
        this.working = true;
        await model.viewModel.testConnection();
      } finally {
        this.working = false;
      }
    },
    isDisabled: function( $state, model ) { return this.working; }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviceAdd', [
    'CnDeviceModelFactory',
    function( CnDeviceModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviceModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviceList', [
    'CnDeviceModelFactory',
    function( CnDeviceModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviceModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviceView', [
    'CnDeviceModelFactory',
    function( CnDeviceModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviceModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviceAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviceListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviceViewFactory', [
    'CnBaseViewFactory', 'CnHttpFactory', 'CnModalMessageFactory',
    function( CnBaseViewFactory, CnHttpFactory, CnModalMessageFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );

        this.testConnection = async function() {
          var modal = CnModalMessageFactory.instance( {
            title: 'Test Connection',
            message: 'Please wait while the connection to this device is tested.',
            block: true
          } );
          
          modal.show();
          var response = await CnHttpFactory.instance( {
            path: 'device/' + this.record.id + '?action=test_connection'
          } ).get();
          modal.close();

          await CnModalMessageFactory.instance( {
            title: 'Test Connection',
            message: response.data
              ? 'Connection succesful.'
              : 'Connection failed.'
          } ).show();
        };
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviceModelFactory', [
    'CnBaseModelFactory', 'CnDeviceAddFactory', 'CnDeviceListFactory', 'CnDeviceViewFactory',
    function( CnBaseModelFactory, CnDeviceAddFactory, CnDeviceListFactory, CnDeviceViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnDeviceAddFactory.instance( this );
        this.listModel = CnDeviceListFactory.instance( this );
        this.viewModel = CnDeviceViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

}  );
