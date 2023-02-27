<?php

class VIP_Woo_Membership_CLI {

    /**
     * This is for generating users locally for testing only
     */

    // Usage: wp vip-woo-membership generateusers
    public function generateusers() {
        WP_CLI::log( 'generating users' );

        $required_user_count = 8000;
        $user_membership = 12;

        $prefix = base64_encode(random_bytes(3));

        $progress = \WP_CLI\Utils\make_progress_bar( 'Generating Posts', $required_user_count );

        for( $i=0; $i < $required_user_count; $i++ ) {

            $username = 'member' . $prefix . $i+1;

            $user_id = wp_insert_user( [
                'user_login' => $username,
                'user_pass' => md5( $username ),
                'user_email' => $username .'@example.com',
                'first_name' => $username,
                'last_name' => 'Lastname',
                'display_name' => $username,
                'role' => 'customer'
            ] );

            // add a user membership
            $membership_post = [
                'post_type' => 'wc_user_membership',
                'post_status' =>  'wcm-active',
                'post_name' => $username,
                'post_parent' => $user_membership,
                'post_author' => $user_id,
            ];
    
            // update/insert post 
            $post_id = wp_insert_post( $membership_post );

            $progress->tick();

        }

        $progress->finish();
    }

    public function exportsubscribers( $args, $assoc_args ) {

        /**
         * TODO: default is to export all subscribers but args and assoc args 
         * can be specified to pass date and other params to match fields in
         * the membership export
         */

        $params = [
            'format' => 'csv',
            'limit' => false,
            'date_start' => false,
            'date_end' => false,
            'export_id' => false
        ];

        $params = array_merge( $params, $assoc_args );

        WP_CLI::log( 'exporting subscribers' );

        $membership = new \VIPWooMembershipAddons\Membership;
        $membership->export_subscribers( $params );

    }

}
WP_CLI::add_command( 'vip-woo-membership', 'VIP_Woo_Membership_CLI' );