<?php

/**
 * Plugin Name:       Advanced ActiveCampaign Site Tracking
 * Plugin URI:        https://github.com/danielmcclure/advanced-ac-tracking
 * Description:       Adds ActiveCampaign site tracking code and links to users email if logged in. 
 * Version:           1.1.0
 * Author:            danielmcclure
 * Author URI:        https://danielmcclure.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       advanced-ac-tracking
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AdvancedACSettings {
    // Holds the values to be used in the fields callbacks
    private $options;

    // Start Up
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    // Add Options Page
    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
            'AC Tracking', 
            'AC Tracking', 
            'manage_options', 
            'advanced_ac_settings_admin', 
            array( $this, 'create_admin_page' )
        );
    }

    // Options page callback
    public function create_admin_page() {
        // Set class property
        $this->options = get_option( 'activecampaign_account' );
        ?>
        <div class="wrap">
            <h1>Advanced ActiveCampaign Tracking Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ac_account_settings' );
                do_settings_sections( 'advanced-ac-settings-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    // Register and add settings
    public function page_init() {        
        register_setting(
            'ac_account_settings', // Option group
            'activecampaign_account', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'activecampaign_account_details', // ID
            'ActiveCampaign Account Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'advanced-ac-settings-admin' // Page
        );  

        add_settings_field(
            'activecampaign_id_number', // ID
            'ActiveCampaign Account ID', // Title 
            array( $this, 'activecampaign_id_number_callback' ), // Callback
            'advanced-ac-settings-admin', // Page
            'activecampaign_account_details' // Section           
        ); 

        add_settings_field(   
            'activecampaign_optin_req',  // ID                       
            'Require opt-in for tracking?', // Title                 
            array( $this, 'activecampaign_optin_req_callback' ), // Callback   
            'advanced-ac-settings-admin', // Page
            'activecampaign_account_details' // Section  
        );       
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['activecampaign_id_number'] ) )
            $new_input['activecampaign_id_number'] = absint( $input['activecampaign_id_number'] );
        if( isset( $input['activecampaign_optin_req'] ) )
            $new_input['activecampaign_optin_req'] = absint( $input['activecampaign_optin_req'] );

        return $new_input;
    }

    // Print the Section text
    public function print_section_info() {
        print 'Enter your ActiveCampaign account details below:';
    }

    // Get the settings option array and print one of its values
    public function activecampaign_id_number_callback() {
        printf(
            '<input type="text" id="activecampaign_id_number" name="activecampaign_account[activecampaign_id_number]" value="%s" />',
            isset( $this->options['activecampaign_id_number'] ) ? esc_attr( $this->options['activecampaign_id_number']) : ''
        );
    }

    // Get the settings option array and print one of its values
    public function activecampaign_optin_req_callback() {
        printf(
            '<input type="checkbox" id="activecampaign_optin_req" name="activecampaign_account[activecampaign_optin_req]" value="1" ' . checked( 1, isset( $this->options['activecampaign_optin_req'] ) ? esc_attr( $this->options['activecampaign_optin_req']) :  0, false ) . '/>'
        );
    }

}

// Generate Settings in Admin View
if( is_admin() )
    $advanced_ac_settings = new AdvancedACSettings();
    
// Insert Advanced Active Campaign Tracking into Wordpress with Email from logged in users.
function advanced_ac_tracking_inject() {
    $advanced_ac_options = get_option( 'activecampaign_account' );
    
    if ( isset( $advanced_ac_options['activecampaign_id_number'] ) ) {
        $ac_id = $advanced_ac_options['activecampaign_id_number'];
        $user_info = get_userdata( get_current_user_id() );
        $user_email = $user_info->user_email;

        if( isset( $advanced_ac_options['activecampaign_optin_req'] ) && $advanced_ac_options[ 'activecampaign_optin_req' ] ) {
            $activecampaign_optin_req = 'var trackByDefault = false;';
        } else {
            $activecampaign_optin_req = 'var trackByDefault = true;';
        }
        
        ?>
        <script type="text/javascript">
        <?php echo $activecampaign_optin_req; ?>

        function acEnableTracking() {
            var expiration = new Date(new Date().getTime() + 1000 * 60 * 60 * 24 * 30);
            document.cookie = "ac_enable_tracking=1; expires= " + expiration + "; path=/";
            acTrackVisit();
        }

        function acTrackVisit() {
            var trackcmp_email = '<?php echo $user_email; ?>';
            var ac_id = '<?php echo $ac_id; ?>';
            var trackcmp = document.createElement("script");
            trackcmp.async = true;
            trackcmp.type = 'text/javascript';
            trackcmp.src = '//trackcmp.net/visit?actid='+encodeURIComponent(ac_id)+'&e='+encodeURIComponent(trackcmp_email)+'&r='+encodeURIComponent(document.referrer)+'&u='+encodeURIComponent(window.location.href);
            var trackcmp_s = document.getElementsByTagName("script");
            if (trackcmp_s.length) {
                trackcmp_s[0].parentNode.appendChild(trackcmp);
            } else {
                var trackcmp_h = document.getElementsByTagName("head");
                trackcmp_h.length && trackcmp_h[0].appendChild(trackcmp);
            }
        }

        if (trackByDefault || /(^|; )ac_enable_tracking=([^;]+)/.test(document.cookie)) {
            acEnableTracking();
        }
        </script>
        <?php       
    } else {
        $ac_id = '';
        echo '<!-- Please add your ActiveCampaign Account ID to enable Site Tracking -->';
    }

}
add_filter( 'wp_footer', 'advanced_ac_tracking_inject' );