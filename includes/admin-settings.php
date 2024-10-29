<?php
/**
 * BadgeOS Restrict Content Pro Settings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Include the License Class
 */

if ( file_exists( BOSRCP_INCLUDES_DIR . 'BOSRCP_License.php' ) ) {
    require_once BOSRCP_INCLUDES_DIR . 'BOSRCP_License.php';
}

/**
 * Class BadgeOS_LD_Pro_Admin_Settings
 */
class BOSRCP_Admin_Settings {

    private $license_class;
    public $page_tab;

    public function __construct() {

        $this->page_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

        add_filter( "plugin_action_links_" . BOSRCP_FILE , [ $this, "admin_settings_link" ] );

        add_filter( 'admin_footer_text', [ $this, 'remove_footer_admin' ] );
        add_action( 'admin_menu', [ $this, 'bosrcp_admin_settings_page'] );
        add_action( 'admin_post_bosrcp_admin_settings', [ $this, 'bosrcp_admin_settings_save' ] );
        add_action( 'admin_notices', [ $this, 'bosrcp_admin_notices'] );
        $this->license_class = new BOSRCP_License();
    }

    /**
     * Return License Class
     *
     * @return WBLG_License
     */
    public function get_license_class() {
        return $this->license_class;
    }

    public function admin_settings_link( $links ) {
        $settings_url = add_query_arg( array( 'page' => 'badgeos_rcp_settings' ), admin_url( 'admin.php' ) );
        $settings_link = '<a href=" ' . $settings_url . ' "> ' . __( 'Settings' , BOSRCP_LANG ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     *  Save plugin options
     */
    public function bosrcp_admin_settings_save() {

        // save some stuff here if needed.
    }

    /**
     * Display Notices
     */
    public function bosrcp_admin_notices() {

        $screen = get_current_screen();
        if( $screen->base != 'badgeos_page_badgeos_rcp_settings' ) {
            return;
        }

        if( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
            $class = 'notice notice-success is-dismissible';
            $message = __( 'Settings Saved', BOSRCP_LANG );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
    }

    /**
     * Create admin settings page
     */
    public function bosrcp_admin_settings_page() {

        add_submenu_page(
            'badgeos_badgeos',
            __( 'BadgeOS RCP Integration', BOSRCP_LANG ),
            __( 'BadgeOS RCP Integration', BOSRCP_LANG ),
            'manage_options',
            'badgeos_rcp_settings',
            [ $this, 'bosrcp_settings_callback_func' ]
        );
    }

    /**
     * Callback function for Setting Page
     */
    public function bosrcp_settings_callback_func() {
        ?>
        <div class="wrap">
            <div class="icon-options-general icon32"></div>
            <h1><?php echo __( 'BadgeOS Restrict Content Pro Settings', BOSRCP_LANG ); ?></h1>

            <div class="nav-tab-wrapper">
                <?php
                $bosrcp_settings_sections = $this->bosrcp_get_setting_sections();
                foreach( $bosrcp_settings_sections as $key => $bosrcp_settings_section ) {
                    ?>
                    <a href="?page=badgeos_rcp_settings&tab=<?php echo $key; ?>"
                       class="nav-tab <?php echo $this->page_tab == $key ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons <?php echo $bosrcp_settings_section['icon']; ?>"></span>
                        <?php _e( $bosrcp_settings_section['title'], BOSRCP_LANG ); ?>
                    </a>
                    <?php
                }
                ?>
            </div>

            <?php
            foreach( $bosrcp_settings_sections as $key => $bosrcp_settings_section ) {
                if( $this->page_tab == $key ) {
                    include( 'admin-templates/' . $key . '.php' );
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * WBLG Settings Sections
     *
     * @return mixed|void
     */
    public function bosrcp_get_setting_sections() {

        $bosrcp_settings_sections = array(
            'general' => array(
                'title' => __( 'License Option', BOSRCP_LANG ),
                'icon' => 'dashicons-admin-network',
            )
        );

        return apply_filters( 'bosrcp_settings_sections', $bosrcp_settings_sections );
    }

    /**
     * Add footer branding
     *
     * @param $footer_text
     * @return mixed
     */
    function remove_footer_admin ( $footer_text ) {
        if( isset( $_GET['page'] ) && ( $_GET['page'] == 'badgeos_rcp_settings' ) ) {
            _e('<span>Fueled by <a href="http://www.wordpress.org" target="_blank">WordPress</a> | developed and designed by <a href="https://wooninjas.com" target="_blank">The WooNinjas</a></span>', BOSRCP_LANG );
        } else {
            return $footer_text;
        }
    }
}

$GLOBALS['badgeos_rcp_options'] = new BOSRCP_Admin_Settings();