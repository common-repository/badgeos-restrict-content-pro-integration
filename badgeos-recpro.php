<?php
/**
 * Plugin Name: BadgeOS Restrict Content Pro Integration
 * Description: The BadgeOS Restrict Content Pro add-on allows you to award the BadgeOS achievements on subscribing/renewing the membership levels (any, free, or paid) and more!
 * Plugin URI: https://badgeos.org/
 * Author: BadgeOS
 * Plugin URI: https://badgeos.org/
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: bos_rcp
 * Domain Path: domain/path
 */


if ( ! defined( 'ABSPATH' ) )  exit;

/**
 * Text Domain
 */
define( 'BOSRCP_LANG', 'bos_rcp' );


class BadgeOS_Restrict_Content_Pro_Integration {

	const VERSION = '1.0';

	/**
	 * Plugin Basename
	 *
	 * @var string
	 */
	public $basename = '';

	/**
	 * Plugin Directory Path
	 *
	 * @var string
	 */
	public $directory_path = '';

	/**
	 * Plugin Directory URL
	 *
	 * @var string
	 */
	public $directory_url = '';

	/**
	 * BadgeOS Restrict Content Pro Triggers
	 *
	 * @var array
	 */
	public $triggers = array();

	/**
	 * Actions to forward for splitting an action up
	 *
	 * @var array
	 */
	public $actions = array();

    public function __construct() {

    	/**
         * Define plugin constants
         */
		$this->basename 		= plugin_basename( __FILE__ );
		$this->directory_path 	= plugin_dir_path( __FILE__ );
		$this->directory_url 	= plugin_dir_url( __FILE__ );

		/**
         * If requirements are not met, deactivate our plugin
         */
		add_action( 'admin_notices', array( $this, 'maybe_disable_plugin' ) );

		$this->setup_constants();
		$this->setup_triggers();
		$this->setup_actions();

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );
		//add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
    }

    /**
     * Setup Constants
     */
    private function setup_constants() {

        /**
         * Directory
         */
        define( 'BOSRCP_FILE', 			plugin_basename(__FILE__) );
        define( 'BOSRCP_DIR', 			plugin_dir_path( __FILE__ ) );
        define( 'BOSRCP_DIR_FILE', 		BOSRCP_DIR . basename( __FILE__ ) );
        define( 'BOSRCP_INCLUDES_DIR', 	trailingslashit( BOSRCP_DIR . 'includes' ) );
        define( 'BOSRCP_BASE_DIR', 		plugin_basename( __FILE__ ) );
        define( 'BOSRCP_TEMPLATES_DIR', trailingslashit ( BOSRCP_DIR . 'templates' ) );

        /**
         * URLS
         */
        define( 'BOSRCP_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
        define( 'BOSRCP_ASSETS_URL', trailingslashit( BOSRCP_URL . 'assets' ) );
    }

    /**
     * Check if BadgeOS or Restrict Content Pro is available
     *
     * @return bool
     */
	public static function meets_requirements() {

		if ( !class_exists( 'BadgeOS' ) ) {
			return false;
		} elseif ( !class_exists( 'Restrict_Content_Pro' ) ) {
			return false;
		}

		return true;
	}

	/**
     * Generate a custom error message and deactivates the plugin if we don't meet requirements
     */
	public function maybe_disable_plugin() {

		if ( !$this->meets_requirements() ) {

		    if( ! is_admin() ) return;

		    $class = 'notice is-dismissible error';

		    if ( !class_exists( 'BadgeOS' ) && class_exists( 'Restrict_Content_Pro' ) ) {
				$message = __( 'BadgeOS Restrict Content Pro Integration add-on requires <a target="_blank" href="https://wordpress.org/plugins/badgeos/" >BadgeOS</a> plugin to be activated.', BOSRCP_LANG );
				printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
		    	deactivate_plugins ( plugin_basename ( __FILE__ ), true );
			} 

			if( !class_exists( 'Restrict_Content_Pro' ) && class_exists( 'BadgeOS' ) ) {
				$message = __( 'BadgeOS Restrict Content Pro Integration add-on requires <a target="_blank" href="https://restrictcontentpro.com/" >Restrict Content Pro</a> plugin to be activated.', BOSRCP_LANG );
				printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
		    	deactivate_plugins ( plugin_basename ( __FILE__ ), true );
			} 

			if(  !class_exists( 'BadgeOS' ) && !class_exists( 'Restrict_Content_Pro' )  ) {
				$message = __( 'BadgeOS Restrict Content Pro Integration add-on requires <a target="_blank" href="https://restrictcontentpro.com/" >Restrict Content Pro</a> and <a target="_blank" href="https://wordpress.org/plugins/badgeos/" >BadgeOS</a> plugins to be activated.', BOSRCP_LANG );
				printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
		    	deactivate_plugins ( plugin_basename ( __FILE__ ), true );
			}

			/**
             * Deactivate our plugin
             */
			deactivate_plugins( $this->basename );
		}
	}

    /**
     * Load the plugin textdomain and include files if plugin meets requirements
     */
	public function plugins_loaded() {

	    /**
         * Load translations
         */
		load_plugin_textdomain( BOSRCP_LANG, false, dirname( $this->basename ) . '/languages/' );

		if ( $this->meets_requirements() ) {

			if( file_exists( $this->directory_path . '/includes/rules-engine.php' ) ) {
	            require_once( $this->directory_path . '/includes/rules-engine.php' );
	        }

	        if( file_exists( $this->directory_path . '/includes/steps-ui.php' ) ) {
	            require_once( $this->directory_path . '/includes/steps-ui.php' );
	        }

			$this->action_forwarding();
		}

		if( file_exists( $this->directory_path . '/includes/admin-settings.php' ) ) {
            require_once( $this->directory_path . '/includes/admin-settings.php' );
        }

        if( file_exists( $this->directory_path . '/includes/BOSRCP_License_Handler.php' ) ) {
            require_once( $this->directory_path . '/includes/BOSRCP_License_Handler.php' );
        }
	}

	/**
     * BadgeOS RCP triggers
     */
    public function setup_triggers() {

		$this->triggers = apply_filters( 'badgeos_rcp_triggers' , array(
			'badgeos_rcp_subscribed_any_membership' 		=> __( 'Subscribe Any Membership', BOSRCP_LANG ),
			'badgeos_rcp_subscribed_free_membership' 		=> __( 'Subscribe Any Free Membership', BOSRCP_LANG ),
			'badgeos_rcp_subscribed_paid_membership' 		=> __( 'Subscribe Any Paid Membership', BOSRCP_LANG ),
			'badgeos_rcp_cancelled_membership' 				=> __( 'Cancel Any Membership', BOSRCP_LANG ),
			'badgeos_rcp_expired_membership' 				=> __( 'Expired Any Membership', BOSRCP_LANG ),
			'badgeos_rcp_renewed_membership' 				=> __( 'Renewed Any Membership', BOSRCP_LANG ),
			//'badgeos_rcp_subscribed_specific_membership' 	=> __( 'Subscribe Specific Membership', BOSRCP_LANG ),
			//'badgeos_rcp_cancelled_specific_membership' 	=> __( 'Cancel Any Specific Membership', BOSRCP_LANG ),
		));

		return $this->triggers;

    }

    /**
     * BadgeOS RCP Hooks
     */
    public function setup_actions() {

    	/**
         * Actions that we need split up
         */
		$this->actions = array(
			'rcp_membership_post_renew' 					=> 'badgeos_rcp_renewed_membership',
			'rcp_transition_membership_status_expired' 		=> 'badgeos_rcp_expired_membership',
			'rcp_transition_membership_status_cancelled' 	=> 'badgeos_rcp_cancelled_membership',
			'rcp_membership_post_activate' 		=> array(
				'actions' => array(
					'badgeos_rcp_subscribed_any_membership',
					'badgeos_rcp_subscribed_free_membership',
					'badgeos_rcp_subscribed_paid_membership',
				)
			)
		);

		return $this->actions;
    }

    /**
     * Forward WP actions into a new set of actions
     */
	public function action_forwarding() {
		foreach ( $this->actions as $action => $args ) {
			$priority = 10;
			$accepted_args = 20;

			if ( is_array( $args ) ) {
				if ( isset( $args[ 'priority' ] ) ) {
					$priority = $args[ 'priority' ];
				}

				if ( isset( $args[ 'accepted_args' ] ) ) {
					$accepted_args = $args[ 'accepted_args' ];
				}
			}
			
			add_action( $action, array( $this, 'action_forward' ), $priority, $accepted_args );
		}
	}

	/**
     * Forward a specific WP action into a new set of actions
     *
     * @return mixed|null
     */
	public function action_forward() {
		$action = current_filter();
		$args = func_get_args();

		rcp_log("WOOTEST: action called: " . $action );

		if ( isset( $this->actions[ $action ] ) ) {
			if ( is_array( $this->actions[ $action ] )
				 && isset( $this->actions[ $action ][ 'actions' ] ) && is_array( $this->actions[ $action ][ 'actions' ] )
				 && !empty( $this->actions[ $action ][ 'actions' ] ) ) {
				foreach ( $this->actions[ $action ][ 'actions' ] as $new_action ) {
					if ( 0 !== strpos( $new_action, strtolower( __CLASS__ ) . '_' ) ) {
						//$new_action = strtolower( __CLASS__ ) . '_' . $new_action;
						//$new_action = $new_action;
					}

					$action_args = $args;

					array_unshift( $action_args, $new_action );
					rcp_log( sprintf( "WOOTEST: params: %s", var_export($action_args, true) ) );
					call_user_func_array( 'do_action', $action_args );
				}

				return null;
			}
			elseif ( is_string( $this->actions[ $action ] ) ) {
				$action =  $this->actions[ $action ];
			}
		}

		if ( 0 !== strpos( $action, strtolower( __CLASS__ ) . '_' ) ) {
			//$action = strtolower( __CLASS__ ) . '_' . $action;
		}

		array_unshift( $args, $action );
		rcp_log( sprintf( "WOOTEST: params: %s", var_export($args, true) ) );
		return call_user_func_array( 'do_action', $args );
	}

}


if( !function_exists("dd") ) {
    function dd( $data, $exit_data = true) {
        echo '<pre>'.print_r($data, true).'</pre>';
        if($exit_data == false)
            echo '';
        else
            exit;
    }
}


/**
 * Initiate plugin main class
 */
function WBRCP() {
    $GLOBALS['badgeos_rcp'] = new BadgeOS_Restrict_Content_Pro_Integration();
}
add_action( 'plugins_loaded', 'WBRCP' );
