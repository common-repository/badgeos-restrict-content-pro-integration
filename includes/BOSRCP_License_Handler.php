<?php
/**
 * License handler for Easy Digital Downloads
 *
 * This class should simplify the process of adding license information
 * to new EDD extensions.
 *
 * @version 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * License_Handler Class
 */
class BOSRCP_License_Handler {
    private $file;
    private $license;
    private $item_name;
    private $item_shortname;
    private $version;
    private $author;
    private $optname = '';
    private $active = false;
    private $expired = false;
    private $last_err = '';
    private $api_url = 'https://wooninjas.com';

    /**
	 * @var BOSRCP_Plugin_Updater
	 */
    private $plugin_updater = null;

    /**
     * Class constructor
     *
     * @global  array $edd_options
     * @param string  $_file
     * @param string  $_item_name
     * @param string  $_version
     * @param string  $_author
     * @param string  $_optname
     * @param string  $_api_url
     */
    function __construct( $_file, $_item_name, $_version, $_author, $_optname = null, $_api_url = null ) {
        global $edd_options;
        
        $this->file           = $_file;
        $this->item_name      = $_item_name;
        $this->item_shortname = 'edd_' . preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $this->item_name ) ) );
        $this->version        = $_version;
        $this->license        = get_option( $_optname, '' );
        $this->author         = $_author;
        $this->optname        = $_optname;
        $this->active         = 'valid' == get_option( $this->item_shortname . '_license_active' );
        $this->api_url        = is_null( $_api_url ) ? $this->api_url : $_api_url;

        // Setup hooks
        $this->includes();
        $this->hooks();
        $this->auto_updater();
        $this->check_license();
    }

    /**
     * Include the updater class
     *
     * @access  private
     * @return  void
     */
    private function includes() {
        require_once 'BOSRCP_Plugin_Updater.php';
    }

    /**
     * Setup hooks
     *
     * @access  private
     * @return  void
     */
    private function hooks() {
        // Activate license key on settings save
        add_action( 'admin_init', [ $this, 'activate_license' ] );

        // Deactivate license key
        add_action( 'admin_init', [ $this, 'deactivate_license' ] );
    }

    /**
     * Auto updater
     *
     * @access  private
     * @global  array $edd_options
     * @return  void
     */
    private function auto_updater() {
        // Setup the updater
        $this->plugin_updater = new BOSRCP_Plugin_Updater(
            $this->api_url,
            $this->file,
            array(
                'version'   => $this->version,
                'license'   => $this->license,
                'item_name' => $this->item_name,
                'author'    => $this->author
            )
        );
    }


    /**
     * Activate the license key
     *
     * @access  public
     * @return  void
     */
    public function activate_license() {
        if ( ! isset( $_POST['bosrcp_activate_license'] ) )
            return;

        if ( ! isset( $_POST[ $this->optname ] ) || empty( $_POST[ $this->optname ] ) )
            return;

        if ( 'valid' == get_option( $this->item_shortname . '_license_active' ) )
            return;

        $license = sanitize_text_field( $_POST[ $this->optname ] );
        update_option( $this->optname, $license );

        // Data to send to the API
        $api_params = array(
            'edd_action' => 'activate_license',
            'license'    => $license,
            'item_name'  => urlencode( $this->item_name ),
            'url'        => urlencode( home_url() )
        );

        // Call the API
        $response = wp_remote_get(
            esc_url_raw( add_query_arg( $api_params, $this->api_url ) ),
            array(
                'timeout'   => 15,
                'body'      => $api_params,
                'sslverify' => false
            )
        );

        // Make sure there are no errors
        if ( is_wp_error( $response ) )
            return;

        // Decode license data
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );

	    $this->last_err = isset( $license_data->error ) ? $license_data->error : '';

        $this->active = ( 'valid' == $license_data->license );
        $this->expired = ( 'expired' == $license_data->error );
        update_option( $this->item_shortname . '_license_active', $license_data->license );
    }


    /**
     * Deactivate the license key
     *
     * @access  public
     * @return  void
     */
    public function deactivate_license() {
        if ( ! isset( $_POST['bosrcp_deactivate_license'] ) )
            return;

        $license = get_option( $this->optname );

        if ( empty( $license ) )
            return;

        // Data to send to the API
        $api_params = array(
            'edd_action' => 'deactivate_license',
            'license'    => $this->license,
            'item_name'  => urlencode( $this->item_name ),
            'url'        => urlencode( home_url() )
        );

        // Call the API
        $response = wp_remote_get(
            esc_url_raw( add_query_arg( $api_params, $this->api_url ) ),
            array(
                'timeout'   => 15,
                'sslverify' => false
            )
        );

        // Make sure there are no errors
        if ( is_wp_error( $response ) )
            return;

        // Decode the license data
        $license_data = json_decode( wp_remote_retrieve_body( $response ) );

	    $this->last_err = isset( $license_data->error ) ? $license_data->error : '';

	    if ( $license_data->license === 'deactivated' || $license_data->license === 'failed' ) {
            delete_option( $this->item_shortname . '_license_active' );
            $this->active = false;
        }
    }

    /**
     * Check License info
     *
     * @access  public
     * @return  void
     */
    public function check_license() {
        if ( isset( $_POST['bosrcp_activate_license'] ) || isset( $_POST['bosrcp_deactivate_license'] ) )
            return;

        $license = get_option( $this->optname );

        if ( empty( $license ) )
            return;

        $check_license_data = wp_cache_get( 'wn_check_license', BOSRCP_LANG );

        if ( ! empty( $check_license_data ) ) {
            $license_data = $check_license_data;
        } else {
            // Data to send to the API
            $api_params = array(
                'edd_action' => 'check_license',
                'license' => $this->license,
                'item_name' => urlencode( $this->item_name ),
                'url' => urlencode( home_url() )
            );

            // Call the API
            $response = wp_remote_get(
                esc_url_raw( add_query_arg( $api_params, $this->api_url ) ),
                array(
                    'timeout' => 15,
                    'sslverify' => false
                )
            );

            // Make sure there are no errors
            if ( is_wp_error( $response ) )
                return;

            // Decode the license data
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );

            wp_cache_set( 'wn_check_license', $license_data, BOSRCP_LANG );
        }

        if ( ! isset( $license_data ) ) {
            return;
        }

	    $this->last_err = isset( $license_data->error ) ? $license_data->error : '';

        if ( $license_data->license == 'expired' ) {
            $this->expired = true;
            $this->active = false;
            delete_option( $this->item_shortname . '_license_active' );
        }
    }

    /**
     * Check if license is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->active;
    }

    /**
     * Check if license is expired
     *
     * @return bool
     */
    public function is_expired() {
        return $this->expired;
    }

    /**
     * Get last error
     *
     * @return string
     */
    public function last_err() {
        return $this->last_err;
    }

	/**
	 * @return BOSRCP_Plugin_Updater
	 */
	public function get_plugin_updater() {
		return $this->plugin_updater;
	}
}
