<?php
/**
 * admin ajax functions. 
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'AutomatePlusWooAjax' ) ) :

class AutomatePlusWooAjax {
	
	private static $instance;

	/**
	 * Initiator
	 */
	public static function instance() 
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new AutomatePlusWooAjax();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	public function hooks()
	{
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
	public static function import_donors_to_mautic() {

		$give_Payments_Query = new Give_Payments_Query();

		$payments = $give_Payments_Query->get_payments();
		
		// loop through all donations
		
		foreach ( $payments as $payment ) {

			$payment_id = $payment->ID;
			$status = $payment->post_status;
			echo $result = APMautic_Give::apm_give_status_change( $payment_id, $status ) ;
		}
		die();
	}

	/** 
	 * Add proactive abandoned leads to Mautic
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_proactive_abandoned_leads() {

		$give_options = AMPW_Mautic_Init::get_amp_options();
		$seg_action_ab = array_key_exists( 'config_give_segment_ab', $give_options ) ? $give_options['config_give_segment_ab'] : '';
		// General global config conditions
		$customer_ab = array(
			'add_segment' => array(),
			'remove_segment' => array()
		);
		array_push( $customer_ab['add_segment'], $seg_action_ab );

		$ab_email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

 		$api_data = AP_Mautic_Api::get_api_method_url( $ab_email );
		$url = $api_data['url'];
		$method = $api_data['method'];

		$body = array(
			'email'		=> $ab_email
		);

		$ab_segment = $customer_ab['add_segment'];

		if( sizeof( $ab_segment ) > 0 ) {

			AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_ab );
		}
		die();
	}
}
$AutomatePlusWooAjax = AutomatePlusWooAjax::instance();
endif;