<?php
/**
 * respondent.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * respondent: record
 */
class respondent extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    $new = is_null( $this->id );

    // setup new respondents
    if( $new )
    {
      $this->token = static::generate_token();
      if( is_null( $this->start_datetime ) ) $this->start_datetime = util::get_datetime_object();
    }

    // if the end_datetime is empty then the respondent must be marked as not exported
    if( is_null( $this->end_datetime ) && !is_null( $this->export_datetime ) ) $this->export_datetime = NULL;

    parent::save();

    // schedule invitation and reminder emails if the qnaire requires it
    if( $new && $this->send_mail ) $this->send_all_mail();
  }

  /**
   * Override the parent method
   */
  public function delete()
  {
    // Note: we must delete all files associated with this response
    $db_qnaire = $this->get_qnaire();
    $db_participant = $this->get_participant();
    $respondent_id = $this->id;
    
    $this->remove_unsent_mail();
    parent::delete();

    if( !is_null( $respondent_id ) && !is_null( $db_qnaire ) )
    {
      $data_dir_list = glob(
        sprintf(
          '%s/*/%s',
          $db_qnaire->get_data_directory(),
          is_null( $db_participant ) ? $respondent_id : $db_participant->uid
        ),
        GLOB_ONLYDIR
      );
      foreach( $data_dir_list as $dir )
      {
        // delete all files in the directory
        foreach( glob( sprintf( '%s/*', $dir ) ) as $file ) if( is_file( $file ) ) unlink( $file );

        // now delete the directory itself
        rmdir( $dir );
      }
    }
  }

  /**
   * Determines whether the respondent has completed all required responses
   * 
   * Whether the respondent is complete depends on the number of responses the parent qnaire requires.
   * @return boolean
   */
  public function is_complete()
  {
    $db_qnaire = $this->get_qnaire();
    $max_responses = is_null( $db_qnaire->max_responses ) ? 1 : $db_qnaire->max_responses;
    $response_mod = lib::create( 'database\modifier' );
    $response_mod->where( 'submitted', '=', true );
    return $this->get_response_count( $response_mod ) == $max_responses;
  }

  /**
   * Returns the invitation mail for this respondent and given rank
   * @param integer $rank
   * @return database\respondent_mail
   */
  public function get_invitation_mail( $rank )
  {
    $respondent_mail_class_name = lib::get_class_name( 'database\respondent_mail' );
    $db_respondent_mail = $respondent_mail_class_name::get_unique_record(
      array( 'respondent_id', 'reminder_id', 'rank' ),
      array( $this->id, NULL, $rank )
    );
    return is_null( $db_respondent_mail ) ? NULL : $db_respondent_mail->get_mail();
  }

  /**
   * Returns this respondent's current response record
   * 
   * @return database\response
   * @access public
   */
  public function get_current_response( $create = false )
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query respondent with no primary key.' );
      return NULL;
    }

    $select = lib::create( 'database\select' );
    $select->from( 'respondent_current_response' );
    $select->add_column( 'response_id' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'respondent_id', '=', $this->id );

    $response_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );

    // if asked create a response if one doesn't exist yet
    $db_response = NULL;
    if( $response_id )
    {
      $db_response = lib::create( 'database\response', $response_id );
    }
    else if( $create )
    {
      // We need a semaphore to guard against duplicate responses.  The semaphore is specific to the parent
      // respondent id so that other respondents are not slowed down.
      $semaphore = lib::create( 'business\semaphore', $this->id );
      $semaphore->acquire();

      try
      {
        $db_response = lib::create( 'database\response' );
        $db_response->respondent_id = $this->id;
        $db_response->save();
      }
      catch( \cenozo\exception\base_exception $e )
      {
        // release the semaphore before re-throwing the exception
        $semaphore->release();
        throw $e;
      }

      $semaphore->release();
    }

    return $db_response;
  }

  /**
   * Gets the respondent's language
   * @return database\language
   */
  public function get_language()
  {
    // set the language to the last response, or the participant default there isn't one
    $db_current_response = $this->get_current_response();
    if( !is_null( $db_current_response ) ) return $db_current_response->get_language();

    return is_null( $this->participant_id ) ?
      $this->get_qnaire()->get_base_language() : $this->get_participant()->get_language();
  }

  /**
   * Reopens the respondent's last response
   */
  public function reopen()
  {
    $script_class_name = lib::get_class_name( 'database\script' );
    $event_class_name = lib::get_class_name( 'database\event' );

    $db_qnaire = $this->get_qnaire();
    $db_response = $this->get_current_response();
    $expression_manager = lib::create( 'business\expression_manager', $db_response );

    // determine the last valid page to set the response back on to
    if( $db_qnaire->stages )
    {
      // get the last complete stage
      $response_stage_mod = lib::create( 'database\modifier' );
      $response_stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
      $response_stage_mod->where( 'status', '=', 'completed' );
      $response_stage_mod->order_desc( 'stage.rank' );
      $response_stage_mod->limit( 1 );
      $response_stage_list = $db_response->get_response_stage_object_list( $response_stage_mod );
     
      if( 0 < count( $response_stage_list ) )
      {
        // if there is a completed stage then re-launch it then immediately pause it
        $db_response_stage = current( $response_stage_list );
        $db_response_stage->launch();
        $db_response_stage->pause();
      }
      else
      {
        // if there are no completed stages then reset the first stage
        $response_stage_mod = lib::create( 'database\modifier' );
        $response_stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
        $response_stage_mod->order( 'stage.rank' );
        $response_stage_mod->limit( 1 );
        $response_stage_list = $db_response->get_response_stage_object_list( $response_stage_mod );
        $db_response_stage = current( $response_stage_list );
        $db_response_stage->reset();
      }
    }
    else
    {
      // find the last valid page
      $db_module = $db_qnaire->get_last_module();
      $db_page = $db_module->get_last_page();
      if( !$expression_manager->evaluate( $db_module->precondition ) )
      {
        do { $db_module = $db_module->get_previous(); }
        while( !is_null( $db_module ) && !$expression_manager->evaluate( $db_module->precondition ) );
        $db_page = is_null( $db_module ) ? NULL : $db_module->get_last_page();
      }

      if( !is_null( $db_page ) && !$expression_manager->evaluate( $db_page->precondition ) )
        $db_page = $db_page->get_previous_for_response( $db_response );

      $db_response->page_id = $db_page->id;
    }

    $db_response->submitted = false;
    $db_response->save();

    $this->end_datetime = NULL;
    $this->save();

    // if not anonymous, remove the finished event if there is one
    if( !is_null( $this->participant_id ) )
    {
      $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $this->qnaire_id );
      if( !is_null( $db_script ) && !is_null( $db_script->finished_event_type_id ) )
      {
        $event_mod = lib::create( 'database\modifier' );
        $event_mod->where( 'event_type_id', '=', $db_script->finished_event_type_id );
        $event_mod->order_desc( 'datetime' );
        $event_mod->limit( 1 );
        $event_list = $this->get_participant()->get_event_object_list( $event_mod );
        if( 0 < count( $event_list ) )
        {
          $db_event = current( $event_list );
          $db_event->delete();
        }
      }
    }
  }

  /**
   * Sends all unsent invitations and reminders for this respondent
   */
  public function send_all_mail()
  {
    // we don't send mail to anonymous respondents
    if( is_null( $this->participant_id ) || !is_null( $this->end_datetime ) ) return;

    $db_qnaire = $this->get_qnaire();
    $number_of_iterations = $db_qnaire->repeated ? $db_qnaire->max_responses : 1;
    if( 0 == $number_of_iterations ) $number_of_iterations = 1; // infinitely repeated qnaires only get one invitation
    $db_current_response = $this->get_current_response();
    $lowest_rank = is_null( $db_current_response ) ? 1 : $db_current_response->rank;
    $now = util::get_datetime_object();
    $base_datetime = clone (
      is_null( $db_current_response ) || is_null( $db_current_response->start_datetime ) ?
      $now :
      $db_current_response->start_datetime
    );

    // filled in the first block and used by the second
    $respondent_invitation_mail_list = array();

    if( $db_qnaire->email_invitation )
    {
      // create an invitation for all iterations of the questionnaire;
      $mail_list = array();
      $past_due_count = 0;
      for( $rank = $lowest_rank; $rank <= $number_of_iterations; $rank++ )
      {
        $datetime = clone $base_datetime;

        if( 1 < $rank )
        { // add repeated span for iterations beyond the first
          $datetime->add( new \DateInterval( sprintf(
            'P%s%d%s',
            'hour' == $db_qnaire->repeated ? 'T' : '',
            $db_qnaire->repeat_offset * ( $rank - 1 ),
            strtoupper( substr( $db_qnaire->repeated, 0, 1 ) )
          ) ) );
        }

        $mail_list[] = array( 'rank' => $rank, 'datetime' => $datetime );
        if( $datetime < $now ) $past_due_count++;
      }

      // make sure there is a maximum of one mail in the past (avoid double-emailing passed emails)
      for( $i = 0; $i < ( $past_due_count-1 ); $i++ ) array_shift( $mail_list );

      foreach( $mail_list as $mail )
        $respondent_invitation_mail_list[$mail['rank']] = $this->add_mail( NULL, $mail['rank'], $mail['datetime'] );
    }

    foreach( $db_qnaire->get_reminder_object_list() as $db_reminder )
    {
      // create a reminder for all iterations of the questionnaire;
      for( $rank = $lowest_rank; $rank <= $number_of_iterations; $rank++ )
      {
        $db_respondent_mail = $respondent_invitation_mail_list[$rank];

        if( !is_null( $db_respondent_mail ) )
        {
          $datetime = clone (
            array_key_exists( $rank, $respondent_invitation_mail_list ) ?
            $respondent_invitation_mail_list[$rank]->get_mail()->schedule_datetime :
            $base_datetime
          );

          $datetime->add( new \DateInterval( sprintf(
            'P%s%d%s',
            'hour' == $db_reminder->delay_unit ? 'T' : '',
            $db_reminder->delay_offset,
            strtoupper( substr( $db_reminder->delay_unit, 0, 1 ) )
          ) ) );

          if( 1 < $rank )
          { // add repeated span for iterations beyond the first
            $datetime->add( new \DateInterval( sprintf(
              'P%s%d%s',
              'hour' == $db_qnaire->repeated ? 'T' : '',
              $db_qnaire->repeat_offset * ( $rank - 1 ),
              strtoupper( substr( $db_qnaire->repeated, 0, 1 ) )
            ) ) );
          }

          if( $datetime >= $now ) $this->add_mail( $db_reminder, $rank, $datetime );
        }
      }
    }
  }

  /**
   * Removes all unsent invitations and reminders for this respondent
   */
  public function remove_unsent_mail()
  {
    // we don't send mail to anonymous respondents
    if( is_null( $this->participant_id ) ) return;

    // get a list of all mail that wasn't sent
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'mail', 'respondent_mail.mail_id', 'mail.id' );
    $modifier->where( 'mail.sent_datetime', '=', NULL );
    $respondent_mail_list = $this->get_respondent_mail_object_list( $modifier );

    // now delete the mail which is no longer needed
    foreach( $respondent_mail_list as $db_respondent_mail ) $db_respondent_mail->get_mail()->delete();
  }

  /**
   * Returns this respondent's URL
   * @return string
   */
  public function get_url()
  {
    return sprintf(
      'https://%s%s/respondent/run/%s',
      $_SERVER['HTTP_HOST'],
      str_replace( '/api', '', ROOT_URL ),
      $this->token
    );
  }

  /**
   * Schedules mail for this respondent (if it hasn't already been sent)
   * @param database\reminder $db_reminder The reminder, or NULL if this is the invitation
   * @param integer $rank Which response rank to send
   * @param datetime $datetime When to schedule the mail, or now if no value is provided
   * @return database\respondent_mail The resulting respondent-mail record (either new or existing)
   */
  private function add_mail( $db_reminder, $rank, $datetime = NULL )
  {
    // we don't send mail to anonymous respondents
    if( is_null( $this->participant_id ) ) return;

    $respondent_mail_class_name = lib::get_class_name( 'database\respondent_mail' );

    $db_respondent_mail = NULL;
    $db_participant = $this->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_subject_description = is_null( $db_reminder )
                            ? $db_qnaire->get_description( 'invitation subject', $this->get_language() )
                            : $db_reminder->get_description( 'subject', $this->get_language() );
    $db_body_description = is_null( $db_reminder )
                         ? $db_qnaire->get_description( 'invitation body', $this->get_language() )
                         : $db_reminder->get_description( 'body', $this->get_language() );
    if( is_null( $db_subject_description ) || is_null( $db_body_description ) )
    {
      log::critical( sprintf(
        'Unable to send %s to %s since description is missing.',
        is_null( $db_reminder ) ?
          'invitation' :
          sprintf( '%s %s reminder', $db_reminder->delay_offset, $db_reminder->delay_unit ),
        $db_participant->uid
      ) );
    }
    else if( $db_subject_description->value && $db_body_description->value )
    {
      if( is_null( $datetime ) ) $datetime = util::get_datetime_object();

      if( $db_participant->email && $db_qnaire->email_from_name && $db_qnaire->email_from_address )
      {
        $db_respondent_mail = $respondent_mail_class_name::get_unique_record(
          array( 'respondent_id', 'reminder_id', 'rank' ),
          array( $this->id, is_null( $db_reminder ) ? NULL : $db_reminder->id, $rank )
        );

        $db_mail = NULL;
        if( !is_null( $db_respondent_mail ) ) $db_mail = $db_respondent_mail->get_mail();

        // don't schedule an email if it has already been sent
        if( is_null( $db_mail ) || is_null( $db_mail->sent_datetime ) )
        {
          if( is_null( $db_mail ) ) $db_mail = lib::create( 'database\mail' );
          $db_mail->participant_id = $db_participant->id;
          $db_mail->from_name = $db_qnaire->email_from_name;
          $db_mail->from_address = $db_qnaire->email_from_address;
          $db_mail->to_name = $db_participant->get_full_name();
          $db_mail->to_address = $db_participant->email;
          $db_mail->schedule_datetime = $datetime;
          $db_mail->subject = $db_subject_description->get_compiled_value( $this, $rank );
          $db_mail->body = $db_body_description->get_compiled_value( $this, $rank );
          $db_mail->note = sprintf(
            'Automatically added from a Pine questionnaire %s iteration #%d.',
            is_null( $db_reminder ) ? 'invitation' : 'reminder',
            $rank
          );
          $db_mail->save();
        }

        // now record the respondent mail if we don't have one yet
        if( is_null( $db_respondent_mail ) )
        {
          $db_respondent_mail = lib::create( 'database\respondent_mail' );
          $db_respondent_mail->respondent_id = $this->id;
          $db_respondent_mail->mail_id = $db_mail->id;
          if( !is_null( $db_reminder ) ) $db_respondent_mail->reminder_id = $db_reminder->id;
          $db_respondent_mail->rank = $rank;
          $db_respondent_mail->save();
        }
      }
    }

    return $db_respondent_mail;
  }

  /**
   * Creates a unique token to be used for identifying a respondent
   * 
   * @access private
   */
  private static function generate_token()
  {
    $created = false;
    $count = 0;
    while( 100 > $count++ )
    {
      $token = sprintf(
        '%s-%s-%s-%s',
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) )
      );

      // make sure it isn't already in use
      if( null == static::get_unique_record( 'token', $token ) ) return $token;
    }

    // if we get here then something is wrong
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique respondent token.', __METHOD__ );
  }

  /**
   * Sent called then this instance will not automatically send mail when first written to the database
   */
  public function do_not_send_mail()
  {
    $this->send_mail = false;
  }

  /**
   * Tracks whether to sent mail when creating the respondent
   * @var boolean $send_mail
   */
  private $send_mail = true;
}
