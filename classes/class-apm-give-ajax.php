<?php
/**
 * Give admin ajax.
 *
 * @package automateplus-mautic-give
 * @since 1.0.0
 */

if ( ! class_exists( 'AutomatePlusGiveAjax' ) ) :

	/**
	 * Initiator
	 * Create class APMautic_Give
	 * Handles Ajax operations
	 */
	class AutomatePlusGiveAjax {

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
				self::$instance = new AutomatePlusGiveAjax();
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

			$seg_action_ab = apm_get_option( 'config_give_segment_ab' );

			// General global config conditions.
			$customer_ab = array(
			'add_segment' => array(),
			'remove_segment' => array(),
			);

			$customer_ab['add_segment'][0] = $seg_action_ab;
			$ab_email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

			$api_data = AP_Mautic_Api::get_api_method_url( $ab_email );
			$url = $api_data['url'];
			$method = $api_data['method'];

			$body = array(
				'email'		=> $ab_email,
			);

			if ( ! empty( $customer_ab['add_segment'] ) ) {

				$result = AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_ab );
			}
			wp_send_json_success( $result );
		}
	}
	AutomatePlusGiveAjax::instance();
endif;
