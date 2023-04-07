<?php

namespace VIPWooMembershipAddons;

use SkyVerge\WooCommerce\Memberships\Profile_Fields;
use SkyVerge\WooCommerce\PluginFramework\v5_10_13 as Framework;

class Membership extends \WC_Memberships_CSV_Export_User_Memberships{

    /** @var string exports folder name */
	private $exports_dir;

	protected $identifier = 'vipwma';

    public function __construct(){
        $this->action      = 'csv_export_user_memberships';
		$this->data_key    = 'user_membership_ids';
		$this->exports_dir = 'memberships_csv_exports';
    }

    public function export_subscribers( $params ) {

        // Create a new export_id
        $params['export_id'] = dechex( microtime( true ) * 1000 ) . bin2hex( random_bytes( 16 ) );

        $attrs = [
            'user_membership_ids'    => [],
            'include_profile_fields' => isset( $params['include_profile_fields'] ) && 'yes' === $params['include_profile_fields'],
            'include_meta_data'      => isset( $params['include_meta'] ) && 'yes' === $params['include_meta'],
            'fields_delimiter'       => ! empty( $params['fields_delimiter'] ) ? $params['fields_delimiter'] : 'comma',
			'export_id' => $params['export_id'],
        ];

        $this->job = $this->create_job( $attrs );

        // get the tmp dir - a cli/cron process on VIP can relay on tmp for the duration of the process
        $export_tmp_folder = get_temp_dir() . '/vip-woo-subscriber-exports/';
        if( ! file_exists( $export_tmp_folder ) ){
            mkdir( $export_tmp_folder ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir
        }

        $export_tmp_file_path = $export_tmp_folder . 'export-woo-subscribers-' . $params['export_id'] . '.csv';

        // avoid appending to an existing file if the export has been restarted with the same export id 
        if( file_exists( $export_tmp_file_path ) ) {
			unlink( $export_tmp_file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}

        // get all memberships (we might need to batch this if we experience OOMs)
        $headers = $this->get_headers( $params );
        $subscribers = $this->get_subscribers( $params );

		$this->job->total = count( $subscribers );
		$this->job->percentage = 0;
		$this->job->status = 'processing';
		$this->job = $this->update_job( $this->job );

        // output as csv to tmp file
        $file_handle = fopen($export_tmp_file_path,"a"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
        $delimiter = $this->get_csv_delimiter( $this->job );
        $enclosure = $this->get_csv_enclosure();
        fputcsv( $file_handle, $this->prepare_csv_row_data( $headers, $headers ), $delimiter, $enclosure ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
        
		$processed_memberships = 0;

        foreach( $subscribers as $subscriber ){
            fputcsv( $file_handle, $this->prepare_csv_row_data( $headers, $subscriber ), $delimiter, $enclosure ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			$processed_memberships += 1;
        }

        fclose( $file_handle );

        // copy file to export location
        copy( $export_tmp_file_path, $this->job->file_path );

		// update job status
		$this->job->status = 'completed';
		$this->job->progress  = $processed_memberships;
		$this->job->percentage = $this->get_percentage( $this->job );
		$this->job = $this->update_job( $this->job );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::log('file copied to ' . $this->job->file_url );
        }

    }

    private function get_headers() {

        $headers = [
            'user_membership_id',
            'user_id',
            'user_name',
            'member_first_name',
            'member_last_name',
            'member_email',
            'member_role',
            'membership_plan_id',
            'membership_plan',
            'membership_plan_slug',
            'membership_status',
            'has_access',
            'product_id',
            'subscription_id',
            'installment_plan',
            'order_id',
            'member_since',
            'membership_expiration',
        ];

        $keyed_headers = [];

        foreach($headers as $header){
            $keyed_headers[ $header ] = $header; 
        }

        return $keyed_headers;
        
    }

    private function get_subscribers( $params ) {
        
        $subscribers = [];

        $subscriber_posts = $this->get_subscriber_posts( $params );

        $job = $this->job;

        if ( is_array( $subscriber_posts ) && count( $subscriber_posts ) > 0 ) {

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                \WP_CLI::log( count($subscriber_posts) . ' subscriber(s) to export' );
                $progress = \WP_CLI\Utils\make_progress_bar( 'Exporting subscribers', count($subscriber_posts) );
            }

            foreach ( $subscriber_posts as $sp ) {
                $subscriber = [];
                foreach ( $this->get_headers() as $header ) {

                    $user = \get_user_by( 'ID', $sp->post_author );
                    $membership_plan = get_post( $sp->post_parent );
                    $user_membership = wc_memberships_get_user_membership( $sp->ID );

                    switch ( $header ) {
                        case 'user_membership_id' :
							$value = $sp->ID;
						break;

						case 'user_id' :
							$value = $sp->post_author;
						break;

						case 'user_name' :
							$value = $user instanceof \WP_User ? $user->user_login : '';
						break;

						case 'member_first_name' :
							$value = $user instanceof \WP_User ? $user->first_name : '';
						break;

						case 'member_last_name' :
							$value = $user instanceof \WP_User ? $user->last_name : '';
						break;

						case 'member_email' :
							$value = $user instanceof \WP_User ? $user->user_email : '';
						break;

						case 'member_role' :
							$role  = $user instanceof \WP_User ? array_shift( $user->roles ) : '';
							$value = is_string( $role ) ? $role : '';
						break;

						case 'membership_plan_id' :
							$value = $membership_plan instanceof \WP_post ? $membership_plan->ID : '';
						break;

						case 'membership_plan' :
							$value = $membership_plan instanceof \WP_post ? $membership_plan->post_title : '';
						break;

						case 'membership_plan_slug' :
							$value = $membership_plan instanceof \WP_post ? $membership_plan->post_name : '';
						break;

						case 'membership_status' :
							$value = $this->get_status( $sp );
						break;

						case 'has_access' :
							$value = $user_membership->is_active() ? strtolower( __( 'Yes', 'woocommerce-memberships' ) ) : strtolower( __( 'No', 'woocommerce-memberships' ) );
						break;

						case 'product_id' :
							$value = $user_membership->get_product_id();
						break;

						case 'subscription_id' :
							$value = $this->get_subscription_id( $user_membership );
						break;

						case 'installment_plan' :
							$value = $this->has_installment_plan( $user_membership );
						break;

						case 'order_id' :
							$value = $user_membership->get_order_id();
						break;

						case 'member_since' :
							$value = 'UTC' === $this->get_csv_timezone( $job ) ? $user_membership->get_start_date() : $user_membership->get_local_start_date();
						break;

						case 'membership_expiration' :
							$value = 'UTC' === $this->get_csv_timezone( $job ) ? $user_membership->get_end_date()   : $user_membership->get_local_end_date();
						break;

                    }

                    $subscriber[ $header ] = $value;
                }

                $subscribers[] = $subscriber;
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    $progress->tick();
                }
            }

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                $progress->finish();
            }

        }


        return $subscribers;
    }

    /**
	 * Returns user membership to export. 
	 *
	 * @param array $export_args export arguments
	 * @return array User Membership posts or  empty array if none found
	 * 
	 */
	private function get_subscriber_posts( array $export_args ) {

		$query_args = [];

		// non filterable args
		$query_args['post_type'] = 'wc_user_membership';
		$query_args['fields']    = 'all';
        $query_args['post_status'] = 'any';
		$query_args['nopaging']  = true; // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.nopaging_nopaging

		// specific user membership IDs may be specified in the bulk action
		if ( isset( $export_args['user_membership_ids'] ) ) {

			if ( is_array( $export_args['user_membership_ids'] ) ) {

				$query_args['post__in'] = array_map( 'absint', $export_args['user_membership_ids'] );

			} elseif ( 'undefined' === $export_args['user_membership_ids'] ) {

				throw new Framework\SV_WC_Plugin_Exception( esc_html__( 'No User Memberships selected for export.', 'woocommerce-memberships' ) );
			}
		}

		$start_from_date = ! empty( $export_args['start_from_date'] ) ? $export_args['start_from_date'] : false;
		$start_to_date   = ! empty( $export_args['start_to_date'] )   ? $export_args['start_to_date']   : false;
		$end_from_date   = ! empty( $export_args['end_from_date'] )   ? $export_args['end_from_date']   : false;
		$end_to_date     = ! empty( $export_args['end_to_date'] )     ? $export_args['end_to_date']     : false;

		// perhaps add meta query args for dates if there's at least one date set
		if ( $start_from_date || $start_to_date || $end_from_date || $end_to_date ) {

			$query_args['meta_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

			// query for User Memberships created within some date
			if ( $start_from_date || $start_to_date ) {
				$query_args['meta_query'][] = $this->get_date_range_meta_query_args( '_start_date', $start_from_date, $start_to_date );
			}

			// query for User Memberships expiring within some date
			if ( $end_from_date || $end_to_date ) {
				$query_args['meta_query'][] = $this->get_date_range_meta_query_args( '_end_date', $end_from_date, $end_to_date );
			}

			// join date query arguments
			if ( 2 === count( $query_args['meta_query'] ) ) {
				$query_args['meta_query']['relation'] = 'AND';
			}
		}

		// query User Memberships with specific plans only
		if ( ! empty( $export_args['plan_ids'] ) ) {
			$query_args['post_parent__in'] = array_map( 'absint', (array) $export_args['plan_ids'] );
		}

		// query User Memberships that have specific statuses (defaults to 'any' otherwise)
		if ( ! empty( $export_args['plan_statuses'] ) ) {
			$query_args['post_status'] = (array) $export_args['plan_statuses'];
		} else {
			$query_args['post_status'] = 'any';
		}

		/**
		 * Filters CSV Export User Memberships query args.
		 *
		 * @since 1.6.0
		 *
		 * @param array $query_args query parameters intended for `get_posts()`
		 */
		$query_args = (array) apply_filters( 'vip_woo_membership_addons_export_user_memberships_query_args', $query_args );
		
		$results = get_posts( $query_args );

		// for debugging limited dataset only // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// $results = array_slice( $results, 0, 250 ); // phpcs:ignore Squiz.PHP.CommentedOutCode.Found

		return $results;
	}

	public function get_export_list( $assoc_args ) {
		$jobs = $this->get_jobs( ['queued', 'processing', 'completed'] );
		//print_r( $jobs );

		$data = [];
		foreach( $jobs as $job ) {
			$data[] = [
				'id' => $job->id,
				'created' => $job->created_at,
				'status' => $job->status,
			];
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, [ 'id', 'created', 'status', ], 'exports' );
		$formatter->display_items( $data );
	}

	public function get_export( $id ) {
		$job = $this->get_job( $id );
		if( ! is_null( $job ) ) {
			return $job;
		}
	}

	public function delete_export( $job ) {
		// delete the export file
		if( is_file( $job->file_path ) ) {
			unlink( $job->file_path );
		}

		// delete the job
		$this->delete_job( $job->id );

		return true;
	}

	/**
	 * @desc schedule a daily cron event to delete all exports over 24 hours old
	 */
	public function schedule_cleanup( ) {
		if ( ! wp_next_scheduled( 'vipwma_export_cleanup') ) {
			$time = time();
			wp_schedule_event($time, 'daily', 'vipwma_export_cleanup');
		}
	}

	public function export_cleanup( $manual=false ) {
		$jobs = $this->get_jobs( ['queued', 'processing', 'completed'] );
		foreach( $jobs as $job ){
			$time_now = time();
			$job_time = strtotime( $job->created_at );
			if( ( $time_now - $job_time ) > 60*60*24 ){
				\WP_CLI::log( 'job ' . $job->id . ' is over 24 hours old, deleting' );
				$this->delete_job( $job->id );
			} else {
				\WP_CLI::log( 'job ' . $job->id . ' is less than 24 hours old, leaving' );
			}
		}
	}

    private function get_status( $subscriber_post ) {
        return 0 === strpos( $subscriber_post->post_status, 'wcm-' ) ? substr( $subscriber_post->post_status, 4 ) : $subscriber_post->post_status;
    }

	private function is_active( $sp ) {

		$current_status = $this->get_status( $sp );
		$active_period  = $this->is_in_active_period();
		$is_active      = in_array( $current_status, wc_memberships()->get_user_memberships_instance()->get_active_access_membership_statuses(), true );

		// sanity check: an active membership should always be within the active period time range
		if ( $is_active && ! $active_period ) {

			// this means the status is active, but the current time is out of the start/end dates boundaries
			if ( $this->get_start_date( 'timestamp' ) > current_time( 'timestamp', true ) ) {
				// if we're before the start date, membership should be delayed
				$this->update_status( 'delayed' );
			} else {
				// if we're beyond the end date, the membership should expire
				$this->expire_membership();
			}

			$is_active = false;

		// the membership status is not active, yet the current time is between the start/end dates, so perhaps should be activated
		} elseif ( $active_period ) {

			if ( 'delayed' === $current_status ) {

				// the time has come and membership is ready for activation
				$this->activate_membership();

				$is_active = true;

			} elseif ( 'expired' ===  $current_status ) {

				// if the membership is expired, we don't reactivate it, but it can't be in active period, so we update the end date to now
				$this->set_end_date( current_time( 'mysql', true ) );

				$is_active = false;
			}
		}

		return $is_active;
	}

    private function is_in_active_period( ) {

		$start = $this->get_start_date( 'timestamp' );
		$now   = current_time( 'timestamp', true );
		$end   = $this->get_end_date( 'timestamp', ! $this->is_expired() );

		return ( $start ? $start <= $now : true ) && ( $end ? $now <= $end : true );
	}

    private function get_start_date( $format = 'mysql' ) {

		$date = get_post_meta( $this->id, $this->start_date_meta, true );

		return ! empty( $date ) ? wc_memberships_format_date( $date, $format ) : null;
	}

	private function get_subscription_id( $user_membership ) {
		$subscription_id = null;

		if ( $user_membership instanceof \WC_Memberships_User_Membership ) {

			$integration     = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
			$subscription_id = $integration ? $integration->get_user_membership_subscription_id( $user_membership->get_id() ) : null;
		}

		return is_numeric( $subscription_id ) ? (int) $subscription_id : null;
	}

	private function has_installment_plan( $user_membership ) {
		$has_installment_plan = 'no';

		if ( $user_membership instanceof \WC_Memberships_User_Membership && class_exists( 'WC_Memberships_Integration_Subscriptions_User_Membership' ) ) {
			$user_membership_id      = is_object( $user_membership ) ? $user_membership->get_id() : (int) $user_membership;
			$subscription_membership = is_numeric( $user_membership_id ) ? new \WC_Memberships_Integration_Subscriptions_User_Membership( $user_membership_id ) : null;
			$has_installment_plan = $subscription_membership && $subscription_membership->has_installment_plan() ? 'yes' : 'no';
		}

		return $has_installment_plan;
	}

    /**
	 * Creates a new export job and its corresponding output file.
	 *
	 * @since 1.10.0
	 *
	 * @param array $attrs associative array
	 * @return null|\stdClass job created
	 * @throws Framework\SV_WC_Plugin_Exception
	 */
	public function create_job( $attrs ) {



		// makes the current export job file name unique
		$file_id   = $attrs['export_id'];
		$file_name = $this->get_file_name( $file_id );
		$file_path = $this->get_file_path( $file_name );
		$file_url  = $this->get_file_url( $file_name );

		// given that it could be filtered, we need to ensure there's a valid file name produced
		if ( '' === $file_name ) {
			throw new Framework\SV_WC_Plugin_Exception( esc_html__( "No valid filename given for export file, can't export memberships.", 'woocommerce-memberships' ) );
		}

		$job = parent::create_job( wp_parse_args( $attrs, [
			'file_name'              => $file_name,
			'file_path'              => $file_path,
			'file_url'               => $file_url,
			'fields_delimiter'       => 'comma',
			'include_profile_fields' => false,
			'include_meta_data'      => false,
			'results'                => (object) [
				'skipped'   => 0,
				'exported'  => 0,
				'processed' => 0,
				'html'      => '',
			],
		] ) );

		return $job;
	}

    /**
	 * Returns the export file name.
	 *
	 * @since 1.10.0
	 *
	 * @param string $file_id unique identifier
	 * @return string
	 */
	private function get_file_name( $file_id ) {

		// file name default: blog_name_user_memberships_{$file_id}_YYYY_MM_DD.csv // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		$file_name = str_replace( '-', '_', sanitize_file_name( strtolower( get_bloginfo( 'name' ) . '_user_memberships_' . $file_id . '_' . date_i18n( 'Y_m_d', time() ) .  '.csv' ) ) );

		/**
		 * Filters the User Memberships CSV export file name.
		 *
		 * @since 1.6.0
		 *
		 * @param string $file_name file name
		 * @param \WC_Memberships_CSV_Export_User_Memberships_Background_Job $export_instance instance of the export class
		 */
		$file_name = apply_filters( 'wc_memberships_csv_export_user_memberships_file_name', $file_name, $this );

		return is_string( $file_name ) ? trim( $file_name ) : '';
	}

    /**
	 * Returns the export file path.
	 *
	 * @since 1.10.0
	 *
	 * @param string $file_name
	 * @return string
	 */
	private function get_file_path( $file_name = '' ) {

		$upload_dir   = wp_upload_dir( null, false );
		$exports_path = trailingslashit( $upload_dir['basedir'] ) . $this->exports_dir;

		return "{$exports_path}/{$file_name}";
	}

    /**
	 * Returns the export file URL.
	 *
	 * @since 1.10.0
	 *
	 * @param string $file_name
	 * @return string
	 */
	private function get_file_url( $file_name ) {

		$upload_url  = wp_upload_dir( null, false );
		$exports_url = trailingslashit( $upload_url['baseurl']  ) . $this->exports_dir;

		return "{$exports_url}/{$file_name}";
	}

	/**
	 * Returns the tmp export file path available only for the batch session.
	 *
	 * @since 1.22.7
	 *
	 * @param string $file_name
	 * @return string
	 */
	private function get_tmp_file_path( $file_name = '' ) {

		$tmp_file = wp_tempnam( $file_name );

		if( file_exists( $tmp_file ) ) {
			unlink( $tmp_file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
		return  $tmp_file;
	}

    /**
	 * Prepares and sanitizes array data for CSV insertion.
	 *
	 * @since 1.10.0
	 *
	 * @param array $headers CSV headers
	 * @param array $row row data (or headers themselves, for the first row)
	 * @return array
	 */
	private function prepare_csv_row_data( $headers, $row ) {

		$data = array();

		foreach ( $headers as $header_key ) {

			if ( ! isset( $row[ $header_key ] ) ) {
				$row[ $header_key ] = '';
			}

			$value = '';

			// strict string comparison, as values like '0' are valid
			if ( '' !== $row[ $header_key ]  ) {
				$value = $row[ $header_key ];
			}

			// escape spreadsheet sensitive characters with a single quote
			// to prevent CSV injections, by prepending a single quote `'`
			// see: http://www.contextis.com/resources/blog/comma-separated-vulnerabilities/
			$untrusted = Framework\SV_WC_Helper::str_starts_with( $value, '=' ) ||
			             Framework\SV_WC_Helper::str_starts_with( $value, '+' ) ||
			             Framework\SV_WC_Helper::str_starts_with( $value, '-' ) ||
			             Framework\SV_WC_Helper::str_starts_with( $value, '@' );

			if ( $untrusted ) {
				$value = "'" . $value;
			}

			$data[] = $value;
		}

		return $data;
	}

}