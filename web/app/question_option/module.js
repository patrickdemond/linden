define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question_option', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question_option' );

  module.identifier.parent = {
    subject: 'question',
    column: 'question.id'
  };

  angular.extend( module.columnList, {
    exclusive: { title: 'Exclusive', type: 'boolean' },
    extra: { title: 'Extra', type: 'string' },
    multiple_answers: { title: 'Multiple Answers', type: 'boolean' }
  } );

  module.addInput( '', 'exclusive', { title: 'Exclusive', type: 'boolean' } );
  module.addInput( '', 'extra', { title: 'Extra', type: 'enum' } );
  module.addInput( '', 'multiple_answers', { title: 'Multiple Answers', type: 'boolean' } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    format: 'float',
    isExcluded: function( $state, model ) { return 'number' != model.viewModel.record.extra ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    format: 'float',
    isExcluded: function( $state, model ) { return 'number' != model.viewModel.record.extra ? true : 'add'; }
  } );
  module.addInput( '', 'parent_name', { column: 'question.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question_option' );
          
          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page', 
              go: function() { return $state.go( 'question.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Question Options'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question_option.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );
} );
