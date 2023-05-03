<?php

class VIP_Woo_Membership_CLI {

    /**
     * Export subscribers
     *
     * ## OPTIONS
     *
     * [--start_from_date=<start_from_date>]
     * : The start date exports should start from YYYY-MM-DD.
     * 
     * [--start_to_date=<start_to_date>]
     * : The start date exports should end YYYY-MM-DD.
     * 
     * [--end_from_date=<end_from_date>]
     * : The end date exports should start from YYYY-MM-DD
     * 
     * [--end_to_date=<end_to_date>]
     * : The end date exports should end YYYY-MM-DD
     */

    // Usage: wp vip-woo-membership exportsubscribers
    public function exportsubscribers( $args, $assoc_args ) {

        /**
         * Default is to export all subscribers but args and assoc args 
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

    /**
     * List current exports
     */

    // Usage: wp vip-woo-membership listexports
    public function listexports( $args, $assoc_args ) {
        $membership = new \VIPWooMembershipAddons\Membership;
        $exports = $membership->get_export_list( $assoc_args );
    }

    /**
     * View export info 
     *  *
     * ## OPTIONS
     *
     * <id>
     * : The id of the export (as displayed in the export list wp vip-woo-membership listexports).
     * 
     * ## EXAMPLES
     *
     * wp vip-woo-membership export info <id>
     */
    public function exportinfo( $args, $assoc_args ) {
        if(sizeof( $args ) > 0 && strlen( trim( $args[0] ) ) > 0 ){
            // check if the export exists
            $membership = new \VIPWooMembershipAddons\Membership;
            $export = $membership->get_export( trim( $args[0] ) );
            if ( $export !== null ) {

                $data = [
                    [
                        'Key'    => 'id',
                        'Value'  => $export->id,
                    ],
                    [
                        'Key'    => 'created',
                        'Value'  => $export->created_at,
                    ],
                    [
                        'Key'    => 'status',
                        'Value'  => $export->status,
                    ],
                    [
                        'Key' => 'percentage',
                        'Value' => $export->percentage . "%",
                    ],
                    [
                        'Key' => 'download',
                        'Value' =>  ( 'completed' === $export->status ) ? $export->file_url : 'not yet available',
                    ],

                ];

                $formatter = new \WP_CLI\Formatter( $assoc_args, [ 'Key', 'Value' ] );
		        $formatter->display_items( $data );
            }
        }
    }

    /**
     * Delete specific export by id
     *
     * ## OPTIONS
     *
     * <id>
     * : The id of the export (as displayed in the export list wp vip-woo-membership listexports).
     * 
     * ## EXAMPLES
     *
     * wp vip-woo-membership deleteexports <id>
     */

    // Usage: wp vip-woo-membership deleteexports <id>
    public function deleteexport( $args, $assoc_args ) {
        if(sizeof( $args ) > 0 && strlen( trim( $args[0] ) ) > 0 ){
            // check if the export exists
            $membership = new \VIPWooMembershipAddons\Membership;
            $export = $membership->get_export( trim( $args[0] ) );
            if ( $export !== null ) { 
                if ( $membership->delete_export( $export ) ) {
                    WP_CLI::log( 'export deleted' );
                }
            }
        }
    }

    /**
     * Delete all exports over 24 hours old
     */

    // Usage: wp vip-woo-membership exportcleanup
    public function exportcleanup( ) {
        $membership = new \VIPWooMembershipAddons\Membership;
        $membership->export_cleanup( true );
    }

}
WP_CLI::add_command( 'vip-woo-membership', 'VIP_Woo_Membership_CLI' );