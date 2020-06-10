define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question' );

  module.identifier.parent = {
    subject: 'page',
    column: 'page.id'
  };

  // The column list is different when looking at a qnaire's list of questions
  angular.extend( module.columnList, {
    module_name: {
      column: 'module.name',
      title: 'Module',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    page_name: {
      column: 'page.name',
      title: 'Page',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    question_name: {
      column: 'question.name',
      title: 'Question',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    type: { title: 'Type' }
  } );

  module.columnList.rank.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.name.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.question_option_count.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.precondition.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };

  module.addInput( '', 'type', { title: 'Type', type: 'enum' } );
  module.addInput( '', 'dkna_refuse', { title: 'Allow DKNA/Refuse', type: 'boolean' } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'default_answer', {
    title: 'Default Answer',
    type: 'string'
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'parent_name', { column: 'page.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question' );
          
          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page', 
              go: function() { return $state.go( 'page.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'page.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Questions'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );

  // extend the model factory
  cenozo.providers.decorator( 'CnQuestionListFactory', [
    '$delegate',
    function( $delegate ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel ) {
        // if we are looking at the list of questions in a qnaire then we must change the default column order
        var object = instance( parentModel );
        if( 'qnaire' == parentModel.getSubjectFromState() ) object.order.column = 'module.rank';
        return object;
      };
      return $delegate;
    }
  ] );

  // extend the base model factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnQuestionModelFactory', [
    '$delegate',
    function( $delegate ) {
      function extendModelObject( object ) {
        object.getAddEnabled = function() {
          // don't allow the add button while viewing the qnaire
          return 'qnaire' != object.getSubjectFromState() && object.$$getAddEnabled();
        };
        return object;
      }

      var instance = $delegate.instance;
      $delegate.root = extendModelObject( $delegate.root );
      $delegate.instance = function( parentModel, root ) { return extendModelObject( instance( root ) ); };

      return $delegate;
    }
  ] );
} );
