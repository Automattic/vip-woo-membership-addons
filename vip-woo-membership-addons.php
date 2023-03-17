<?php
/*
Plugin Name: WPVIP Woo Membership Addons
Plugin URI: https://wpvip.com
Description: A plugin which adds some extra features to Woo memberships
Author URI: https://wpvip.com
*/

namespace VIPWooMembershipAddons;

/**
 * extend woocommwerce membership classes
 */

class VIP_Woocommerce_Memberships_Dependencies {

    private function get_plugin_path() {
        return WP_PLUGIN_DIR . '/woocommerce-memberships';
    }

    private function get_wc_plugin_path() {
        return WP_PLUGIN_DIR . '/woocommerce';
    }

    protected function load_framework() {
        require_once( $this->get_plugin_path() . '/vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-helper.php' );
        require_once( $this->get_plugin_path() . '/vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-plugin.php' );	}

    public function load_class( $local_path, $class_name ) {

        if ( ! class_exists( $class_name ) ) {
            require_once( $this->get_plugin_path() . $local_path );
        }

        return new $class_name;
	}

    public function includes() {

        $this->load_framework();

        require_once( $this->get_plugin_path() . '/class-wc-memberships.php' );

        // load helpers
        require_once( $this->get_plugin_path() . '/src/Helpers/Strings_Helper.php' );
    
        // load post types
        require_once( $this->get_plugin_path() . '/src/class-wc-memberships-post-types.php' );
    
        // load user messages helper
        require_once( $this->get_plugin_path() . '/src/class-wc-memberships-user-messages.php' );
    
        // load helper functions
        require_once( $this->get_plugin_path() . '/src/functions/wc-memberships-functions.php' );
    
        // load data stores
        require_once( $this->get_plugin_path() . '/src/Data_Stores/Profile_Field_Definition/Option.php' );
        require_once( $this->get_plugin_path() . '/src/Data_Stores/Profile_Field/User_Meta.php' );
    
        // load profile field objects
        require_once( $this->get_plugin_path() . '/src/Profile_Fields/Exceptions/Invalid_Field.php' );
        require_once( $this->get_plugin_path() . '/src/Profile_Fields/Profile_Field_Definition.php' );
        require_once( $this->get_plugin_path() . '/src/Profile_Fields/Profile_Field.php' );
        require_once( $this->get_plugin_path() . '/src/Profile_Fields.php' );
    
        // init general classes
        $this->plans            = $this->load_class( '/src/class-wc-memberships-membership-plans.php', 'WC_Memberships_Membership_Plans' );
        $this->user_memberships = $this->load_class( '/src/class-wc-memberships-user-memberships.php', 'WC_Memberships_User_Memberships' );
    
        // load utilities
        $this->utilities = $this->load_class( '/src/class-wc-memberships-utilities.php', 'WC_Memberships_Utilities' );
    
        $this->user_memberships_export   = $this->load_class( '/src/utilities/class-wc-memberships-csv-export-user-memberships.php', 'WC_Memberships_CSV_Export_User_Memberships' );
    }

}

function vip_woo_addons(){
    $dependencies = new VIP_Woocommerce_Memberships_Dependencies;
    $dependencies->includes();
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        include_once __DIR__ . '/inc/class-cli.php';
    }
    include_once __DIR__ . '/inc/class-membership.php';
}
add_action( 'plugins_loaded', '\VIPWooMembershipAddons\vip_woo_addons', 20, 2 );

function vip_woo_activate( ) {
    include_once __DIR__ . '/inc/class-membership.php';
    $membership = new \VIPWooMembershipAddons\Membership;
    $membership->schedule_cleanup();
  
}
register_activation_hook( __FILE__, 'VIPWooMembershipAddons\vip_woo_activate' );
add_action( 'vipwma_export_cleanup', '\VIPWooMembershipAddons\Membership\export_cleanup()' );


