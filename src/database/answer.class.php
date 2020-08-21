<?php
/**
 * answer.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * answer: record
 * 
 * Note that the value column of this record is a JSON value with the following example values:
 * dkna: { "dkna": true }
 * refuse: { "refuse": true }
 * boolean: true
 * number: 1
 * string: "value"
 * list: [ 1, 2, { "id":3, "value":"rawr"}, { "id":12, "value": ["one", "two", "three"] } ]
 */
class answer extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    $question_option_class_name = lib::get_class_name( 'database\question_option' );
    $expression_manager = lib::create( 'business\expression_manager' );
    $new = is_null( $this->id );

    // always set the language to whatever the response's current language is
    $db_response = lib::create( 'database\response', $this->response_id );
    $this->language_id = $db_response->language_id;

    parent::save();

    // When changing an answer we may have to update other answers on the same page which are affected by the answer that
    // was just provided since their preconditions may depend on it.
    if( !$new )
    {
      $db_question = $this->get_question();

      // There are two different types of data which needs to be removed if their precondition is no longer met
      // after setting this answer's value

      // 1) the answer refers to a question which should no longer be asked due to this answer's value or selected option
      $question_sel = lib::create( 'database\select' );
      $question_sel->add_column( 'id' );
      $question_sel->add_column( 'precondition' );
      $question_mod = lib::create( 'database\modifier' );
      $question_mod->where( 'question.precondition', 'RLIKE', sprintf( '\\$%s(:[^$]+)?\\$', $db_question->name ) );
      foreach( $db_question->get_page()->get_question_list( $question_sel, $question_mod ) as $question )
      {
        if( !$expression_manager->evaluate( $db_response, $question['precondition'] ) )
        {
          $db_answer = static::get_unique_record(
            array( 'response_id', 'question_id' ),
            array( $db_response->id, $question['id'] )
          );
          if( !is_null( $db_answer ) && 'null' != $db_answer->value )
          {
            $db_answer->value = 'null';
            $db_answer->save();
          }
        }
      }

      // 2) the answer includes a question-option which should no longer be allowed due to this answer's value or selected option
      $question_option_sel = lib::create( 'database\select' );
      $question_option_sel->add_column( 'id' );
      $question_option_sel->add_column( 'precondition' );
      $question_option_sel->add_table_column( 'question', 'id', 'question_id' );
      $question_option_mod = lib::create( 'database\modifier' );
      $question_option_mod->join( 'question', 'question_option.question_id', 'question.id' );
      $question_option_mod->where( 'question.type', '=', 'list' );
      $question_option_mod->where( 'question.page_id', '=', $db_question->page_id );
      $question_option_mod->where( 'question_option.precondition', 'RLIKE', sprintf( '\\$%s(:[^$]+)?\\$', $db_question->name ) );
      foreach( $question_option_class_name::select( $question_option_sel, $question_option_mod ) as $question_option )
      {
        if( !$expression_manager->evaluate( $db_response, $question_option['precondition'] ) )
        {
          $db_answer = static::get_unique_record(
            array( 'response_id', 'question_id' ),
            array( $db_response->id, $question_option['question_id'] )
          );

          if( !is_null( $db_answer ) ) $db_answer->remove_answer_value_by_option_id( $question_option['id'] );
        }
      }
    }
  }

  /**
   * Override parent method
   */
  public static function get_unique_record( $column, $value )
  {
    $record = NULL;

    // convert token column to a response_id
    if( is_array( $column ) && in_array( 'token', $column ) )
    {
      $index = array_search( 'token', $column );
      if( false !== $index )
      {
        $respondent_class_name = lib::get_class_name( 'database\respondent' );
        $db_respondent = $respondent_class_name::get_unique_record( 'token', $value[$index] );
        $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
        $column[$index] = 'response_id';
        $value[$index] = is_null( $db_response ) ? 0 : $db_response->id;
      }
    }

    return parent::get_unique_record( $column, $value );
  }

  /**
   * Returns whether the answer is complete
   * @return boolean
   */
  public function is_complete()
  {
    $expression_manager = lib::create( 'business\expression_manager' );
    $value = util::json_decode( $this->value );
    $db_response = $this->get_response();
    $db_question = $this->get_question();
    
    // comment questions are always complete
    if( 'comment' == $db_question->type ) return true;

    // hidden questions are always complete
    if( !$expression_manager->evaluate( $db_response, $db_question->precondition ) ) return true;

    // null values are never complete
    if( is_null( $value ) ) return false;

    // dkna/refused questions are always complete
    if( is_object( $value ) )
    {
      $dkna = array_key_exists( 'dkna', $value ) && $value->dkna;
      $refuse = array_key_exists( 'refuse', $value ) && $value->refuse;
      if( $dkna || $refuse ) return true;
    }

    if( 'list' == $db_question->type )
    {
      // get the list of all preconditions for all options belonging to this question
      $question_option_sel = lib::create( 'database\select' );
      $question_option_sel->add_column( 'id' );
      $question_option_sel->add_column( 'precondition' );
      $precondition_list = array();
      foreach( $db_question->get_question_option_list( $question_option_sel ) as $question_option )
        $precondition_list[$question_option['id']] = $question_option['precondition'];

      // make sure that any selected item with extra data has provided that data
      foreach( $value as $selected_option )
      {
        $selected_option_id = is_object( $selected_option ) ? $selected_option->id : $selected_option;
        if( is_object( $selected_option ) ) {
          if( $expression_manager->evaluate( $db_response, $precondition_list[$selected_option_id] ) && (
              ( is_array( $selected_option->value ) && 0 == count( $selected_option->value ) ) ||
              is_null( $selected_option->value )
          ) ) return false;
        }
      }

      // make sure there is at least one selected option
      foreach( $value as $selected_option )
      {
        $selected_option_id = is_object( $selected_option ) ? $selected_option->id : $selected_option;
        if( $expression_manager->evaluate( $db_response, $precondition_list[$selected_option_id] ) )
        {
          if( is_object( $selected_option ) )
          {
            if( is_array( $selected_option->value ) )
            {
              // make sure there is at least one option value
              foreach( $selected_option->value as $selected_option_value ) if( !is_null( $selected_option_value ) ) return true;
            }
            else if( !is_null( $selected_option->value ) ) return true;
          }
          else if( !is_null( $selected_option ) ) return true;
        }
      }

      return false;
    }

    return true;
  }

  /**
   * Removes any answer associated with the given option ID
   * @param integer $option_id
   */
  public function remove_answer_value_by_option_id( $option_id )
  {
    $select = lib::create( 'database\select' );
    $select->add_column( sprintf( 'JSON_SEARCH( value, "one", %d )', $option_id ), 'search', false );
    $select->from( 'answer' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'id', '=', $this->id );

    $json_path = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    if( !is_null( $json_path ) && false === strpos( $json_path, '.value' ) )
    {
      static::db()->execute( sprintf(
        'UPDATE answer SET value = JSON_REMOVE( value, %s ) WHERE id = %d',
        str_replace( '.id', '', $json_path ),
        $this->id
      ) );

      // replace empty arrays with null
      static::db()->execute( sprintf(
        'UPDATE answer SET value = "null" WHERE id = %d AND value = "[]"',
        $this->id
      ) );
    }
  }

  /**
   * Removes any empty answer values stored in the record
   */
  public function remove_empty_answer_values()
  {
    $select = lib::create( 'database\select' );
    $select->add_column( 'JSON_SEARCH( value, "all", "null" )', 'search', false );
    $select->from( 'answer' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'id', '=', $this->id );

    $json_path = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    if( !is_null( $json_path ) && '"$"' != $json_path )
    {
      $matches = util::json_decode( $json_path );
      if( !is_array( $matches ) ) $matches = array( $matches );
      foreach( $matches as $match )
      {
        static::db()->execute( sprintf(
          'UPDATE answer SET value = JSON_REMOVE( value, "%s" ) WHERE id = %d',
          $match,
          $this->id
        ) );
      }
    }
  }
}
