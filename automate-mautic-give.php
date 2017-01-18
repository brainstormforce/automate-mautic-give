<?php
/**
 * Plugin Name: AutomatePlus - Mautic for Give
 * Plugin URI: http://www.brainstormforce.com/
 * Description: Integrate Mautic with your Give donation forms. Add donors to Mautic segment when they donate to your Cause.
 * Version: 1.0.0
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com/
 * Text Domain: automateplus-mautic-give
 */
/**
 * AutomatePlus - Mautic for Give
 * Copyright (C) 2017
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 **/

// exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'APMautic_Give' ) ) :

	class APMautic_Give {

		private static $instance;

		public static function instance() {

			if ( ! isset( self::$instance ) ) {
				self::$instance = new APMautic_Give();
				self::$instance->set_constants();
				self::$instance->hooks();
				self::$instance->includes();
			}
			return self::$instance;
		}

		public function set_constants() {
			
			define( 'AUTOMATEPLUS_MAUTIC_GIVE_DIR', plugin_dir_path( __FILE__ ) );
			define( 'AUTOMATEPLUS_MAUTIC_GIVE_URL', plugins_url( '/', __FILE__ ) );
		}

		public function includes() {

			require_once AUTOMATEPLUS_MAUTIC_GIVE_DIR . 'classes/class-apm-give-ajax.php';
		}

		public function hooks() {

			add_action( 'admin_notices', array( $this, 'apm_give_notices' ), 1000);
			add_action( 'network_admin_notices', array( $this, 'apm_give_notices' ), 1000);
			
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			if ( is_plugin_active( 'give/give.php' ) ) {

				add_action( 'admin_enqueue_scripts', array( $this, 'apm_give_styles_scripts' ) );

				// order complete actions
				add_action( 'give_payment_receipt_after', array( $this, 'give_mautic_config' ), 11, 1 );

				// if proactive abandoned tracking
				add_action( 'give_donation_form_bottom', array( $this, 'give_abandoned_tracking' ) );

				// status change
				add_action( 'give_update_payment_status', array( $this, 'apm_give_status_change' ), 10, 2 );

				//  Add tab
				add_action( 'amp_new_options_tab', array( $this, 'render_give_tab' ) );
				add_action( 'amp_options_tab_content', array( $this, 'render_give_tab_content' ) );
				add_action( 'amp_update_tab_content', array( $this, 'update_give_tab_content' ) );
				
				add_filter( 'update_footer', array( $this, 'send_customers'), 199 );
			}
		}

		public function apm_give_status_change( $payment_id, $status ) {

			$payment = array(
				'ID' => $payment_id, 
				'post_status' => $status
			);

			// cast array as object
			$payment = (object) $payment;

			self::give_mautic_config( $payment );
		}

		public function apm_give_notices() {
			
			if ( ! is_plugin_active( 'automate-mautic/automate-mautic.php' ) ) {

				$url = network_admin_url() . 'plugin-install.php?s=AutomatePlus+-+Mautic+for+WordPress';
				$message = __('Please install','automateplus-mautic-give') . ' <i><a href="' . $url . '" target="_blank">' . __( 'AutomatePlus - Mautic for WordPress', 'automateplus-mautic-give' ) . '</a><i> ' . __( ' plugin in order to use Automate Mautic Give.', 'automateplus-mautic-give' );
				echo '<div class="update-nag bsf-update-nag">' . $message . '</div>';
			}

			if ( ! is_plugin_active( 'give/give.php' ) ) {

				$url = network_admin_url() . 'plugin-install.php?s=give&tab=search';
				$message = __('Please install and activate','automateplus-mautic-give') . ' <i><a href="' . $url . '">' . __( 'Give - WordPress Donation Plugin','automateplus-mautic-give' ) . '</a></i> ' . __( ' plugin in order to use Automate Mautic Give.', 'automateplus-mautic-give' );
				echo '<div class="update-nag bsf-update-nag">' . $message . '</div>';
			}
		}

		public function apm_give_styles_scripts() {
			
			if ( ( isset( $_REQUEST['page'] ) && 'automate-mautic' == $_REQUEST['page'] ) ) {
				
				wp_enqueue_script( 'apm-give-admin-script', AUTOMATEPLUS_MAUTIC_GIVE_URL . 'assets/js/give-admin.js' , array( 'jquery','jquery-ui-sortable','wp-util' ) );
			}
		}

		public function give_abandoned_tracking() {

			$options = AMPW_Mautic_Init::get_amp_options();
			$enable_proact_tracking	= false;

			if ( ! empty( $options ) && array_key_exists( 'amp_give_proactive_abandoned', $options ) ) {
				
				if( $options['amp_give_proactive_abandoned'] == 1 ) {

					$enable_proact_tracking = true;
				} else {

					$enable_proact_tracking = false;
				}
			}

			if ( $enable_proact_tracking ) {

				$adminajax =  admin_url( 'admin-ajax.php' );
				$select_params = array(
					'ajax_url'	=> $adminajax
				);
				wp_enqueue_script( 'give-proactive-ab' , AUTOMATEPLUS_MAUTIC_GIVE_URL . 'assets/js/give-proactive-ab.js', __FILE__ , array( 'jquery' ));
				wp_localize_script( 'give-proactive-ab', 'amp_loc', $select_params );
			}
		}

		public function select_all_forms( $select = null ) {

			$args = array( 'post_type'	=>	'give_forms', 'posts_per_page' => -1, 'post_status' => 'publish' );
			$give_forms = get_posts( $args );
			$all_forms = '<select id="amp-give-forms" class="amp-give-forms form-control" name="sub_give_forms">';
			$all_forms .= '<option>' . __( 'Select Form', 'automateplus-mautic-give' ) . '</option>';

				foreach ( $give_forms as $form ) : setup_postdata( $form );
					$all_forms .= APM_RulePanel::make_option( $form->ID, $form->post_title, $select );
				endforeach;

			$all_forms .='</select>';
			wp_reset_postdata();
			echo $all_forms;
		}

		public function send_customers( $footer_text ) {

			$screen = get_current_screen();

			if ( $screen->id == 'settings_page_automate-mautic' ) {
				$refresh_text = '<a type="button" name="refresh-mautic" id="send-give-donors" class="refresh-mautic-data">' . __( 'Send Give donors to Mautic', 'automateplus-mautic-give' ) . '</a>';
				$footer_text  = $refresh_text . ' | ' . $footer_text;
			}

			return $footer_text;
		}

		/** 
		 * Add purchasers to Mautic
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function give_mautic_config( $payment ) {

			$payment_id = $payment->ID;
			$status = $payment->post_status;

			$m_tags = $all_forms = array();

			$remove_from_all_segment	= false;
			$give_options = AMPW_Mautic_Init::get_amp_options();
			$give_gateway	= array_key_exists( 'amp_give_gateway', $give_options ) ? $give_options['amp_give_gateway'] : '';
			
			$give_payment = array_key_exists( 'amp_give_payment', $give_options ) ? $give_options['amp_give_payment'] : '';
			
			$give_form_tag	= array_key_exists( 'amp_give_form_tag', $give_options ) ? $give_options['amp_give_form_tag'] : '';

			$seg_action_id = array_key_exists( 'config_give_segment', $give_options ) ? $give_options['config_give_segment'] : '';
			
			$seg_action_failed = array_key_exists( 'config_give_segment_failed', $give_options ) ? $give_options['config_give_segment_failed'] : '';
			
			$seg_action_refund = array_key_exists( 'config_give_segment_refund', $give_options ) ? $give_options['config_give_segment_refund'] : '';
			
			$seg_action_cancel = array_key_exists( 'config_give_segment_cancel', $give_options ) ? $give_options['config_give_segment_cancel'] : '';
			
			$seg_action_pending = array_key_exists( 'config_give_segment_pending', $give_options ) ? $give_options['config_give_segment_pending'] : '';
			
			$seg_action_revoked = array_key_exists( 'config_give_segment_revoked', $give_options ) ? $give_options['config_give_segment_revoked'] : '';
			
			$seg_action_ab = array_key_exists( 'config_give_segment_ab', $give_options ) ? $give_options['config_give_segment_ab'] : '';
			
			$seg_action_form = array_key_exists( 'config_give_segment_form', $give_options ) ? $give_options['config_give_segment_form'] : '';
			
			$give_form = array_key_exists( 'config_give_form', $give_options ) ? $give_options['config_give_form'] : '';
			

			if ( array_key_exists( 'apm_give_remove_segment', $give_options ) ) {
				
				if( $give_options['apm_give_remove_segment'] == 1 ) {
					
					$remove_from_all_segment = true;
				} else {
					$remove_from_all_segment = false;
				}
			}

			if ( array_key_exists( 'remove_segment_ap', $give_options ) ) {
				
				if( $give_options['remove_segment_ap'] == 1 ) {
					
					$remove_from_segment_purchase = true;
				} else {
					$remove_from_segment_purchase = false;
				}
			}

			// General global config conditions
			
			$all_customer = $customer_failed = $customer_revoked = $customer_abandoned = $customer_pending = $customer_refund = $customer_cancel = $default = array(
				
				'add_segment' => array(),
				'remove_segment' => array()
			);

			$curr_form = get_post_meta( $payment_id, '_give_payment_form_id', true );
			
			// Check if selected form is used
			if ( $give_form === $curr_form ) {

				array_push( $default['add_segment'], $seg_action_form );
			}

			//all customers to Mautic
			array_push( $all_customer['add_segment'], $seg_action_id );

			//customers with status failed
			array_push( $customer_failed['add_segment'], $seg_action_failed );

			//customers with status revoked
			array_push( $customer_revoked['add_segment'], $seg_action_revoked );

			//customers with status hold
			array_push( $customer_abandoned['add_segment'], $seg_action_ab );

			//customers with status pending
			array_push( $customer_pending['add_segment'], $seg_action_pending );

			//customers with status refund
			array_push( $customer_refund['add_segment'], $seg_action_refund );

			//customers with status cancel
			array_push( $customer_cancel['add_segment'], $seg_action_cancel );

			//remove user from pending if payment is completed 
			array_push( $all_customer['remove_segment'], $seg_action_pending );

			if ( sizeof( $default['add_segment'] ) > 0 ) {

				$all_customer['add_segment'] = array_merge( $default['add_segment'], $all_customer['add_segment'] );

				$customer_failed['add_segment'] = array_merge( $default['add_segment'], $customer_failed['add_segment'] );

				$customer_revoked['add_segment'] = array_merge( $default['add_segment'], $customer_revoked['add_segment'] );

				$customer_abandoned['add_segment'] = array_merge( $default['add_segment'], $customer_abandoned['add_segment'] );

				$customer_pending['add_segment'] = array_merge( $default['add_segment'], $customer_pending['add_segment'] );

				$customer_refund['add_segment'] = array_merge( $default['add_segment'], $customer_refund['add_segment'] );

				$customer_abandoned['add_segment'] = array_merge( $default['add_segment'], $customer_abandoned['add_segment'] );
			}

			// get the payment meta
			$payment_meta = get_post_meta( $payment_id, '_give_payment_meta', true );

			// unserialize the payment meta
			$user_info = maybe_unserialize( $payment_meta['user_info'] );

			// get donor's email
			$email = get_post_meta( $payment_id, '_give_payment_user_email', true );

			$api_data = AP_Mautic_Api::get_api_method_url( $email );
			$url = $api_data['url'];
			$method = $api_data['method'];

			$body = array(
				'firstname'	=>	$user_info['first_name'],
				'lastname'	=>	$user_info['last_name'],
				'email'		=>	$email,
				'address1'	=>	$user_info['address']
			);

			$m_tags = self::get_tags_by_payment( $payment_id );

			if( ( sizeof( $m_tags ) > 0 ) ) {

				$m_tags = implode( "," , $m_tags );
				$body['tags'] = $m_tags;
			}

			switch ( $status ) {
				
				case 'publish':

					if( $remove_from_segment_purchase ) {

						self::remove_from_all_segment( $email );
					}

					if( sizeof( $all_customer['add_segment'] ) > 0 && $all_customer['add_segment'][0] != 'Select Segment' ) {
						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $all_customer );
					}
					break;

				case 'failed':

					if( sizeof( $customer_failed['add_segment'] ) > 0 && $customer_failed['add_segment'][0] != 'Select Segment' ) {
						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_failed );
					}
					break;

				case 'refunded':

					if( $remove_from_all_segment ) {

						self::remove_from_all_segment( $email );
					}

					if( sizeof( $customer_refund['add_segment'] ) > 0 && $customer_refund['add_segment'][0] != 'Select Segment' ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_refund );
					}
					break;

				case 'pending':
		
					if( sizeof( $customer_pending['add_segment'] ) > 0 && $customer_pending['add_segment'][0] != 'Select Segment' ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_pending );
					}
					break;

				case 'revoked':

					if( sizeof( $customer_revoked['add_segment'] ) > 0 && $customer_revoked['add_segment'][0] != 'Select Segment' ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_revoked );
					}
					break;

				case 'cancelled':

					if( sizeof( $customer_cancel['add_segment'] ) > 0 && $customer_cancel['add_segment'][0] != 'Select Segment' ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_cancel );
					}
					break;

				case 'abandoned':

					if( sizeof( $customer_abandoned['add_segment'] ) > 0 && $customer_abandoned['add_segment'][0] != 'Select Segment' ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_abandoned );
					}
					break;
			}
		}

		public function render_give_tab( $active_tab ) {

			if ( is_plugin_active( 'give/give.php' ) ) { ?>
				<a href="<?php APM_AdminSettings::render_page_url( "&tab=give_mautic" ); ?>" class="nav-tab <?php echo $active_tab == 'give_mautic' ? 'nav-tab-active' : ''; ?>"> <?php _e('Give', 'automateplus-mautic-give'); ?> </a>
			<?php }
		}

		public static function update_give_tab_content() {

			if ( isset( $_POST['amp-mautic-nonce-give'] ) && wp_verify_nonce( $_POST['amp-mautic-nonce-give'], 'ampmauticgive' ) ) {

				$give_options = AMPW_Mautic_Init::get_amp_options();
				$give_options['amp_give_gateway'] = $give_options['amp_give_payment'] = $give_options['amp_give_form_tag'] = $give_options['amp_give_proactive_abandoned'] = $give_options['apm_give_remove_segment'] = false;

				if( isset( $_POST['amp_give_gateway'] ) ) {	
					
					$give_options['amp_give_gateway'] = true;
				}
				
				if( isset( $_POST['amp_give_payment'] ) ) {	
					
					$give_options['amp_give_payment'] = true;
				}
				if( isset( $_POST['amp_give_form_tag'] ) ) {	
					$give_options['amp_give_form_tag'] = true;	
				}
				if( isset( $_POST['amp_give_proactive_abandoned'] ) ) {	
						
						$give_options['amp_give_proactive_abandoned'] = true;	
					}
				if( isset( $_POST['apm_give_remove_segment'] ) ) {	
						
						$give_options['apm_give_remove_segment'] = true;	
					}
				if( isset( $_POST['remove_segment_ap'] ) ) {	
						
						$give_options['remove_segment_ap'] = true;	
					}

				if( isset( $_POST['ss_seg_action'][0] ) ) {	
						
						$give_options['config_give_segment'] = sanitize_text_field( $_POST['ss_seg_action'][0] );
					}
				if( isset( $_POST['ss_seg_action'][1] ) ) {	
						
						$give_options['config_give_segment_failed'] = sanitize_text_field( $_POST['ss_seg_action'][1] );
					}
				if( isset( $_POST['ss_seg_action'][2] ) ) {	
						
						$give_options['config_give_segment_revoked'] = sanitize_text_field( $_POST['ss_seg_action'][2] );
					}
				if( isset( $_POST['ss_seg_action'][3] ) ) {	
						
						$give_options['config_give_segment_ab'] = sanitize_text_field( $_POST['ss_seg_action'][3] );
					}
				if( isset( $_POST['ss_seg_action'][4] ) ) {	
						
						$give_options['config_give_segment_pending'] = sanitize_text_field( $_POST['ss_seg_action'][4] );
					}
				if( isset( $_POST['ss_seg_action'][5] ) ) {	
						
						$give_options['config_give_segment_refund'] = sanitize_text_field( $_POST['ss_seg_action'][5] );
					}
				if( isset( $_POST['ss_seg_action'][6] ) ) {	
						
						$give_options['config_give_segment_cancel'] = sanitize_text_field( $_POST['ss_seg_action'][6] );
					}
				if( isset( $_POST['ss_seg_action'][7] ) ) {	
						
						$give_options['config_give_segment_form'] = sanitize_text_field( $_POST['ss_seg_action'][7] );
					}

				if( isset( $_POST['sub_give_forms'] ) ) {	

						$give_options['config_give_form'] = sanitize_text_field( $_POST['sub_give_forms'] ); 
				}

				update_option( 'ampw_mautic_config', $give_options );
				
				$redirect = APM_AdminSettings::get_render_page_url( "&tab=give_mautic" );
				wp_redirect( $redirect );
			}
		}

		/**
		 * Get tags of pall supplied roducts
		 * @return tags array
		 */
		public function get_tags_by_payment( $payment_id ) {

			$give_options = AMPW_Mautic_Init::get_amp_options();
			$give_gateway = array_key_exists( 'amp_give_gateway', $give_options ) ? $give_options['amp_give_gateway'] : '';
			$give_payment	= array_key_exists( 'amp_give_payment', $give_options ) ? $give_options['amp_give_payment'] : '';
			$give_form	= array_key_exists( 'amp_give_form_tag', $give_options ) ? $give_options['amp_give_form_tag'] : '';
			$m_tags = array();
			// get form name
			if( $give_form ) {

				$form_title = get_post_meta( $payment_id, '_give_payment_form_title', true );
				array_push( $m_tags, $form_title );
			}

			// get payment mode
			if( $give_payment ) {

				$payment_mode = get_post_meta( $payment_id, '_give_payment_mode', true );
				array_push( $m_tags, $payment_mode );
			}

			// get payment gateway
			if( $give_gateway ) {

				$payment_gateway = get_post_meta( $payment_id, '_give_payment_gateway', true );
				array_push( $m_tags, $payment_gateway );
			}

			return $m_tags;
		}

		/** 
		 * remove contact from all segments
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function remove_from_all_segment( $email ) {
			
			$contact_id = self::get_mautic_contact_id( $email );

			if( isset( $contact_id ) ) {
				
				// get all segments contact is member of

				$url = "/api/contacts/" . $contact_id . "/segments";
				$method = "GET";
				 
				$segments = AP_Mautic_Api::ampw_mautic_api_call( $url, $method );

				$credentials =  AMPW_Mautic_Init::get_mautic_credentials();

				if( empty( $segments ) ) {

						return;
				}

				foreach( $segments->lists as $list ) {

					$segment_id = $list->id;
					$segment_id = (int)$segment_id;
					$action = "remove";
					AP_Mautic_Api::mautic_contact_to_segment( $segment_id, $contact_id, $credentials, $action );
				}
			}
		}

		public static function get_mautic_contact_id( $email ) {

			$credentials =  AMPW_Mautic_Init::get_mautic_credentials();
			$data = array();
			
			if( isset( $_COOKIE['mtc_id'] ) ) {
				$contact_id = $_COOKIE['mtc_id'];
				$contact_id = (int)$contact_id;

				$email_cid = AP_Mautic_Api::mautic_get_contact_by_email( $email, $credentials );
				if( isset( $email_cid ) ) {

					$contact_id = (int)$email_cid;
				}
			}
			else {
				$contact_id = AP_Mautic_Api::mautic_get_contact_by_email( $email, $credentials );

			}

			return $contact_id;
		}


		public function render_give_tab_content( $active ) {
			
			if( $active == 'give_mautic' ) {

				if ( is_plugin_active( 'give/give.php' ) ) {
					$give_options = AMPW_Mautic_Init::get_amp_options();

					$give_gateway	= ( array_key_exists( 'amp_give_gateway', $give_options ) && $give_options['amp_give_gateway'] == 1 )  ? 'checked' :'';
					$give_payment = ( array_key_exists( 'amp_give_payment', $give_options ) && $give_options['amp_give_payment'] == 1 )  ? 'checked' : '';
					$give_form_tag	= ( array_key_exists( 'amp_give_form_tag', $give_options ) && $give_options['amp_give_form_tag'] == 1 )  ? 'checked' : '';

					$proactive_tracking = ( array_key_exists( 'amp_give_proactive_abandoned', $give_options ) && $give_options['amp_give_proactive_abandoned'] == 1 )  ? ' checked' : '';
					$apm_give_remove_segment = ( array_key_exists( 'apm_give_remove_segment', $give_options ) && $give_options['apm_give_remove_segment'] == 1 )  ? ' checked' : '';
					$remove_segment_ap = ( array_key_exists( 'remove_segment_ap', $give_options ) && $give_options['remove_segment_ap'] == 1 )  ? ' checked' : '';

					$ss_seg_action = ( array_key_exists( 'config_give_segment', $give_options ) ) ? $give_options['config_give_segment'] : '';
					$ss_seg_action_failed = ( array_key_exists( 'config_give_segment_failed', $give_options ) ) ? $give_options['config_give_segment_failed'] : '';
					$ss_seg_action_refund = ( array_key_exists( 'config_give_segment_refund', $give_options ) ) ? $give_options['config_give_segment_refund'] : '';
					$ss_seg_action_cancel = ( array_key_exists( 'config_give_segment_cancel', $give_options ) ) ? $give_options['config_give_segment_cancel'] : '';
					$ss_seg_action_pending = ( array_key_exists( 'config_give_segment_pending', $give_options ) ) ? $give_options['config_give_segment_pending'] : '';
					$ss_seg_action_revoked = ( array_key_exists( 'config_give_segment_revoked', $give_options ) ) ? $give_options['config_give_segment_revoked'] : '';
					$ss_seg_action_hold = ( array_key_exists( 'config_give_segment_ab', $give_options ) ) ? $give_options['config_give_segment_ab'] : '';
				 
					$seg_action_form = ( array_key_exists( 'config_give_segment_form', $give_options ) ) ? $give_options['config_give_segment_form'] :'';
					$give_form = array_key_exists( 'config_give_form', $give_options ) ? $give_options['config_give_form'] : '';
					?>
					<div class="amp-config-fields">
						<h4><?php _e( 'Add Users in Segments', 'automateplus-mautic-give' ); ?></h4>
						<p>
							<label><?php _e( 'After complete donation, add users to:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">
								<?php APM_RulePanel::select_all_segments( $ss_seg_action ); ?>
							</div>
							<p style="margin: 2px;">
								<input type="checkbox" class="amp-enabled-panels" name="remove_segment_ap" value="" <?php echo $remove_segment_ap; ?> ><?php _e( 'Remove users from all segments', 'automateplus-mautic-give' ); ?>
							</p>
						</p>
						<p>
							<label><?php _e( 'Add customer with failed order to:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">
								<?php APM_RulePanel::select_all_segments( $ss_seg_action_failed ); ?>
							</div>
						</p>

						<p>
							<label><?php _e( 'Add customer with revoked order to:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">

								<?php APM_RulePanel::select_all_segments( $ss_seg_action_revoked ); ?>

							</div>
						</p>

						<p>
							<label><?php _e( 'Add customer with abandoned order to:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">

								<?php APM_RulePanel::select_all_segments( $ss_seg_action_hold ); ?>

							</div>
							<p style="margin: 2px;">
								<input type="checkbox" class="amp-enabled-panels" name="amp_give_proactive_abandoned" value="" <?php echo $proactive_tracking; ?> ><?php _e( 'Enable Proactive Abandonment Tracking', 'automateplus-mautic-give' ); ?>
							</p>
						</p>

						<p>
							<label><?php _e( 'Add customer with pending order to:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">

								<?php APM_RulePanel::select_all_segments( $ss_seg_action_pending ); ?>

							</div>
						</p>

						<p>
							<label><?php _e( 'Add refunded users in:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">

								<?php APM_RulePanel::select_all_segments( $ss_seg_action_refund ); ?>

							</div>
							<p style="margin: 2px;">
								<input type="checkbox" class="amp-enabled-panels" name="apm_give_remove_segment" value="" <?php echo $apm_give_remove_segment; ?> ><?php _e( 'Remove users from all segments', 'automateplus-mautic-give' ); ?>
							</p>
						</p>

						<p>
							<label><?php _e( 'Add cancelled donors in:', 'automateplus-mautic-give' ); ?></label>
							<div class="second-action" style="display:inline;">

								<?php APM_RulePanel::select_all_segments( $ss_seg_action_cancel ); ?>

							</div>
						</p>

						<h4><?php _e( 'Add Users by Give Form', 'automateplus-mautic-give' ); ?></h4>

						<p>
							<label><?php _e( 'Add users who donate with form ', 'automateplus-mautic-give' ); ?></label>
						 	<?php self::select_all_forms( $give_form ); ?>
							<label><?php _e( ' to segment ', 'automateplus-mautic-give' ); ?></label>
							<?php APM_RulePanel::select_all_segments( $seg_action_form ); ?>
						</p>

						<h4><?php _e( 'Give Default Tags', 'automateplus-mautic-give' ); ?></h4>	
						<p>

							<label>
								<input type="checkbox" class="amp-enabled-panels" name="amp_give_form_tag" value="" <?php echo $give_form_tag; ?> ><?php _e( 'Automatically add Give form title as a tag in Mautic', 'automateplus-mautic-give' ); ?>
							</label><br>

							<label>
								<input type="checkbox" class="amp-enabled-panels" name="amp_give_gateway" value="" <?php echo $give_gateway; ?> ><?php _e( 'Automatically add Give payment gateway as a tag in Mautic', 'automateplus-mautic-give' ); ?>
							</label><br>

							<label>
								<input type="checkbox" class="amp-enabled-panels" name="amp_give_payment" value="" <?php echo $give_payment; ?> ><?php _e( 'Automatically add Give payment mode as a tag in Mautic
	', 'automateplus-mautic-give' ); ?>
							</label><br>
						 
						</p>

						<p class="submit">
							<input type="submit" name="save-give-tab" id="save-amp-settings" class="button-primary button button-large" value="<?php esc_attr_e( 'Save Settings', 'automateplus-mautic-give' ); ?>" />
							<span class="spinner apm-wp-spinner" style="float: none;margin-bottom: 0.5em;"></span>
						</p>
						<?php wp_nonce_field( 'ampmauticgive', 'amp-mautic-nonce-give' ); ?>

					</div>
				<?php }
			}
		}
} // end of class

$APMautic_Give = APMautic_Give::instance();
endif;