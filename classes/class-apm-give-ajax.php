<?php
/**
 * Give admin ajax.
 *
 * @package automate-mautic-give
 * @since 1.0.0
 */

if ( ! class_exists( 'APM_Give_Ajax' ) ) :

	/**
	 * Initiator
	 * Create class APMautic_Give
	 * Handles Ajax operations
	 */
	class APM_Give_Ajax {

		/**
		 * Declare a static variable instance.
		 *
		 * @var instance
		 */
		private static $instance;

		/**
		 * Initiate class
		 *
		 * @since 1.0.0
		 * @return class instance
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new APM_Give_Ajax();
				self::$instance->hooks();
			}
			return self::$instance;
		}

		/**
		 * Call hooks
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function hooks() {
			add_action( 'wp_ajax_import_apm_give_donors', array( $this, 'import_donors_to_mautic' ) );

			add_action( 'wp_ajax_nopriv_add_give_proctive_leads', array( $this, 'add_proactive_abandoned_leads' ) );
			add_action( 'wp_ajax_add_give_proctive_leads', array( $this, 'add_proactive_abandoned_leads' ) );
		}

		/**
		 * Apply setting to all data
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function import_donors_to_mautic() {
			check_ajax_referer( 'apm_give_import_donors', 'nonce' );
			$obj_payments = new Give_Payments_Query();
			$payments = $obj_payments->get_payments();

			// loop through all donations.
			foreach ( $payments as $payment ) {

				$payment_id = $payment->ID;
				$status = $payment->post_status;
				$result = APMautic_Give::apm_give_status_change( $payment_id, $status );
			}
			wp_send_json_success( $result );
		}

		/**
		 * Add proactive abandoned leads to Mautic
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function add_proactive_abandoned_leads() {

			check_ajax_referer( 'amp_give_proactive_abandoned', 'nonce' );
			$seg_action_ab = apm_get_option( 'config_give_segment_ab' );

			// General global config conditions.
			$customer_ab = array(
			'add_segment' => array(),
			'remove_segment' => array(),
			);

			$customer_ab['add_segment'][0] = $seg_action_ab;
			$ab_email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

			$body = array(
				'email'		=> $ab_email,
			);

			if ( ! empty( $customer_ab['add_segment'] ) ) {

				$instance = APMautic_Services::get_service_instance( AP_MAUTIC_SERVICE );
				$result = $instance->subscribe( $ab_email, $body, $customer_ab );
			}
			wp_send_json_success( $result );
		}
	}
	APM_Give_Ajax::instance();
endif;
