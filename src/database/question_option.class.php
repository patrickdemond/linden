<?php
/**
 * question_option.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace spruce\database;
use cenozo\lib, cenozo\log, spruce\util;

/**
 * question_option: record
 */
class question_option extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'question';
}
