<?php
/**
 * cypress_manager.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Manages communication with Cypress services for communication with medical devices
 */
class cypress_manager extends \cenozo\base_object
{
  /**
   * Constructor.
   * 
   * @param database\device $db_device
   * @access protected
   */
  public function __construct( $db_device )
  {
    $this->db_device = $db_device;
  }

  /**
   * Returns the Cypress server's status (NULL
   * @return object (NULL if server is unreachable)
   * @access public
   */
  public function get_status()
  {
    $status = NULL;
    if( !is_null( $this->db_device ) )
    {
      try
      {
        // call the base of the URL to test if Cypress is online
        $status = $this->send( sprintf( '%s/status', $this->db_device->url ) );
      }
      catch( \cenozo\exception\runtime $e )
      {
        // ignore errors
      }
    }

    return $status;
  }

  /**
   * Attempts to launch a device by sending a POST request to the cypress service
   * 
   * @param array $data An associative array of data to send to Cypress
   * @access public
   */
  public function launch( $data )
  {
    // send a post request to cypress to start the device, it should respond with a UUID
    try
    {
      $response = $this->send( $this->db_device->url, 'POST', $data );
    }
    catch( \cenozo\exception\runtime $e )
    {
      if( 400 == $this->last_code )
      {
        throw lib::create( 'exception\notice',
          'The device has not been setup properly (input data is missing), please contact support.'."\n\n".
          $e->get_raw_message(),
          __METHOD__,
          $e
        );
      }
      else if( 409 == $this->last_code )
      {
        throw lib::create( 'exception\notice',
          'Cannot launch the device as it is already busy.',
          __METHOD__,
          $e
        );
      }
      else throw $e;
    }

    if( !property_exists( $response, 'sessionId' ) || 0 == strlen( $response->sessionId ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Invalid UUID returned from Cypress while launching %s', $this->db_device->name ),
        __METHOD__
      );
    }

    // return the UUID generated by Cypress
    return $response->sessionId;
  }

  /**
   * Requests that the device aborts a particular run by UUID
   * 
   * @param string $uuid The UUID of the exam that should be aborted
   * @access public
   */
  public function abort( $uuid )
  {
    try
    {
      // send a delete request to cypress to abort the device
      $this->send( sprintf( '%s/%s', $this->db_device->url, $uuid ), 'DELETE' );
    }
    catch( \cenozo\exception\runtime $e )
    {
      // ignore 404, it just means the UUID has already been cancelled
      if( 404 != $this->last_code )
      {
        // report other errors to the log but otherwise ignore them
        log::error( $e->get_raw_message() );
      }
    }
  }

  /**
   * Sends curl requests
   * 
   * @param string $api_path The internal cenozo path (not including base url)
   * @return varies
   * @access public
   */
  private function send( $api_path, $method = 'GET', $data = NULL )
  {
    $setting_manager = lib::create( 'business\setting_manager' );
    $user = $setting_manager->get_setting( 'utility', 'username' );
    $pass = $setting_manager->get_setting( 'utility', 'password' );
    $header_list = array( sprintf( 'Authorization: Basic %s', base64_encode( sprintf( '%s:%s', $user, $pass ) ) ) );

    $this->last_code = 0;

    // set URL and other appropriate options
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $api_path );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, $this->timeout );

    if( 'POST' == $method )
    {
      curl_setopt( $curl, CURLOPT_POST, true );
    }
    else if( 'GET' != $method )
    {
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
    }

    if( !is_null( $data ) )
    {
      $encoded_data = util::json_encode( $data );
      $header_list[] = 'Content-Type: application/json';
      $header_list[] = sprintf( 'Content-Length: %d', strlen( $encoded_data ) );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, $encoded_data );
    }

    curl_setopt( $curl, CURLOPT_HTTPHEADER, $header_list );

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when trying %s request to %s.  Message: %s',
                 curl_errno( $curl ),
                 $method,
                 $this->db_device->name,
                 curl_error( $curl ) ),
        __METHOD__ );
    }
    
    $this->last_code = (int) curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 204 == $this->last_code || 300 <= $this->last_code )
    {
      $message = 400 == $this->last_code ?
        sprintf( 'Invalid column value "%s".', $response ) :
        sprintf( 'Got response code %s "%s" when trying %s request to %s.',
          $this->last_code,
          $response,
          $method,
          $this->db_device->name
        );
      throw lib::create( 'exception\runtime', $message, __METHOD__ );
    }

    return util::json_decode( $response );
  }

  /**
   * The device to connect to
   * @var database\device
   * @access protected
   */
  protected $db_device = NULL;

  /**
   * The number of seconds to wait before giving up on connecting to the device
   * @var integer
   * @access protected
   */
  protected $timeout = 5;

  /**
   * The last HTTP code returned by Cypress
   * @var integer
   * @access protected
   */
  protected $last_code = NULL;
}
