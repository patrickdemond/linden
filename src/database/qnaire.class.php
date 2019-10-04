<?php
/**
 * qnaire.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire: record
 */
class qnaire extends \cenozo\database\record
{
  /**
   * TODO: document
   */
  public function get_first_module()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to get first module of qnaire with no primary key.' );
      return NULL;
    }

    $module_class_name = lib::get_class_name( 'database\module' );
    return $module_class_name::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_question( $name )
  {
    $select = lib::create( 'database\select' );
    $select->from( 'qnaire' );
    $select->add_table_column( 'question', 'id' );

    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'qnaire.id', 'module.qnaire_id' );
    $modifier->join( 'page', 'module.id', 'page.module_id' );
    $modifier->join( 'question', 'page.id', 'question.page_id' );
    $modifier->where( 'question.name', '=', $name );

    $question_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    return is_null( $question_id ) ? NULL : lib::create( 'database\question', $question_id );
  }
}
