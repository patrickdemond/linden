<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      $db_respondent = $this->get_leaf_record();
      $db_response = $db_respondent->get_current_response();

      if( 'resend_mail' == $action )
      {
        $db_respondent->send_all_mail();
      }
      else if( 'force_submit' == $action )
      {
        $db_response->page_id = NULL;
        $db_response->submitted = true;
        $db_response->save();
      }
      else
      {
        if( 'proceed' == $action ) $db_response->move_to_next_page();
        else if( 'backup' == $action ) $db_response->move_to_previous_page();
        else if( 'set_language' == $action )
        {
          // set the response's new language
          $language_class_name = lib::get_class_name( 'database\language' );
          $db_language = $language_class_name::get_unique_record( 'code', $this->get_argument( 'code' ) );
          $db_response->set_language( $db_language );
        }

        // update the last datetime anytime the response is changed
        $db_response->last_datetime = util::get_datetime_object();
        $db_response->save();
      }
    }
  }
}
