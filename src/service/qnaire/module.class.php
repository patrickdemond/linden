<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extends the parent method
   */
  public function validate()
  {
    $util_class_name = lib::get_class_name( 'util' );

    parent::validate();

    if( !$this->service->may_continue() ) return;

    // if a version is provided then make sure they match
    $pine_version_header = $util_class_name::get_header( 'Pine-Version' );
    if( !is_null( $pine_version_header ) )
    {
      $setting_manager = lib::create( 'business\setting_manager' );
      $version = $setting_manager->get_setting( 'general', 'version' );
      $build = $setting_manager->get_setting( 'general', 'build' );

      $parts = explode( ' ', $pine_version_header );
      $remote_version = $parts[0];
      $remote_build = $parts[1];

      if( $remote_version != $version || $remote_build != $build )
      {
        $this->get_status()->set_code( 306 );
        $this->service->set_data( 'Your software is out of date.  Please update and try again.' );
      }
    }
  }

  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    $session = lib::create( 'business\session' );

    parent::prepare_read( $select, $modifier );

    $modifier->join( 'language', 'qnaire.base_language_id', 'base_language.id', '', 'base_language' );

    // add the total number of modules
    $this->add_count_column( 'module_count', 'module', $select, $modifier );

    // add the total time spent
    $this->add_count_column( 'respondent_count', 'respondent', $select, $modifier );

    if( $select->has_column( 'repeat_detail' ) )
    {
      $select->add_column(
        'IF( '.
          'repeated IS NOT NULL, '.
          'CONCAT( "every ", IF( 1 < repeat_offset, CONCAT( repeat_offset, " ", repeated, "s" ), repeated ) ), '.
          '"no" '.
        ')',
        'repeat_detail',
        false
      );
    }

    if( $select->has_column( 'average_time' ) )
    {
      $modifier->join( 'qnaire_average_time', 'qnaire.id', 'qnaire_average_time.qnaire_id' );
      $select->add_table_column( 'qnaire_average_time', 'time', 'average_time' );
    }

    $db_qnaire = $this->get_resource();
    if( !is_null( $db_qnaire ) )
    {
      if( $select->has_column( 'anonymous_url' ) )
      {
        $select->add_constant(
          sprintf(
            '%s/respondent/run/q=%s',
            $session->get_application()->url,
            urlencode( $db_qnaire->name )
          ),
          'anonymous_url'
        );
      }

      if( $select->has_column( 'first_page_id' ) )
      {
        $first_page_id = NULL;
        $db_first_module = $db_qnaire->get_first_module();
        if( !is_null( $db_first_module ) )
        {
          $db_first_page = $db_first_module->get_first_page();
          if( !is_null( $db_first_page) ) $first_page_id = $db_first_page->id;
        }
        $select->add_constant( $first_page_id, 'first_page_id', 'integer' );
      }
    }
  }
}
