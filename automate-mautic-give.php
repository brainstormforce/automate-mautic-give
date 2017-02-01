<?php
/**
 * Plugin Name: AutomatePlus - Mautic for Give
 * Plugin URI: http://www.brainstormforce.com/
 * Description: Integrate Mautic with your Give donation forms. Add donors to Mautic segment when they donate to your Cause.
 * Version: 1.0.0
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com/
 * Text Domain: automateplus-mautic-give
 *
 * @package automateplus-mautic-give
 * @author Brainstorm Force
 */

// exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'APMautic_Give' ) ) :

	/**
	 * Initiator
	 * Create class APMautic_Give
	 */
	class APMautic_Give {

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

				self::$instance = new APMautic_Give();
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

			if ( class_exists( 'Give' ) && class_exists( 'AutomatePlus_Mautic' ) ) {

				self::set_constants();
				self::includes();

				add_action( 'admin_enqueue_scripts', array( $this, 'apm_give_styles_scripts' ) );

				// order complete actions.
				add_action( 'give_payment_receipt_after', array( $this, 'give_mautic_config' ), 11, 1 );

				// if proactive abandoned tracking.
				add_action( 'give_donation_form_bottom', array( $this, 'give_abandoned_tracking' ) );

				// payment status change.
				add_action( 'give_update_payment_status', array( $this, 'apm_give_status_change' ), 10, 2 );

				// Add tab in base plugin.
				add_action( 'amp_new_options_tab', array( $this, 'render_give_tab' ) );
				add_action( 'amp_options_tab_content', array( $this, 'render_give_tab_content' ) );
				add_action( 'amp_update_tab_content', array( $this, 'update_give_tab_content' ) );

				add_filter( 'update_footer', array( $this, 'send_customers' ), 199 );
			} else {
				add_action( 'admin_notices', array( $this, 'apm_give_notices' ), 1000 );
				add_action( 'network_admin_notices', array( $this, 'apm_give_notices' ), 1000 );
			}
		}

		/**
		 * Set Constants
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function set_constants() {

			define( 'AUTOMATEPLUS_MAUTIC_GIVE_DIR', plugin_dir_path( __FILE__ ) );
			define( 'AUTOMATEPLUS_MAUTIC_GIVE_URL', plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Include give ajax file
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function includes() {

			require_once AUTOMATEPLUS_MAUTIC_GIVE_DIR . 'classes/class-apm-give-ajax.php';
		}

		/**
		 * Call mautic config on status change
		 *
		 * @since 1.0.0
		 * @param int    $payment_id Payment ID.
		 * @param string $status Payment status.
		 * @return void
		 */
		public function apm_give_status_change( $payment_id, $status ) {

			$payment = new stdClass();
			$payment->ID = $payment_id;
			$payment->post_status = $status;

			self::give_mautic_config( $payment );
		}

		/**
		 * Display notices if required plugin is not active
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function apm_give_notices() {

			if ( ! class_exists( 'AutomatePlus_Mautic' ) ) {

				$url = network_admin_url() . 'plugin-install.php?s=AutomatePlus+-+Mautic+for+WordPress&tab=search';
				printf( __( '<div class="update-nag bsf-update-nag">Please install <i><a href="%s">AutomatePlus - Mautic for WordPress</a></i> plugin in order to use Automate Mautic Give.</div>', 'automateplus-mautic-give' ), $url );
			}

			if ( ! class_exists( 'Give' ) ) {

				$url = network_admin_url() . 'plugin-install.php?s=give&tab=search';
				printf( __( '<div class="update-nag bsf-update-nag">Please install <i><a href="%s">Give - WordPress Donation Plugin</a></i> plugin in order to use Automate Mautic Give.</div>', 'automateplus-mautic-give' ), $url );
			}
		}

		/**
		 * Enqueue admin js file
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function apm_give_styles_scripts() {

			if ( ( isset( $_REQUEST['page'] ) && 'automate-mautic' == esc_attr( $_REQUEST['page'] ) ) ) {

				wp_enqueue_script( 'apm-give-admin-script', AUTOMATEPLUS_MAUTIC_GIVE_URL . 'assets/js/give-admin.js' , array( 'jquery', 'jquery-ui-sortable', 'wp-util' ) );
			}
		}

		/**
		 * Enqueue abandoned tracking admin js file
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function give_abandoned_tracking() {

			$enable_proact_tracking	= false;

			if ( 1 == apm_get_option( 'amp_give_proactive_abandoned' ) ) {

				$enable_proact_tracking = true;
			} else {

				$enable_proact_tracking = false;
			}

			if ( $enable_proact_tracking ) {

				$adminajax = admin_url( 'admin-ajax.php' );
				$select_params = array(
					'ajax_url'	=> $adminajax,
				);
				wp_enqueue_script( 'give-proactive-ab' , AUTOMATEPLUS_MAUTIC_GIVE_URL . 'assets/js/give-proactive-ab.js', __FILE__ , array( 'jquery' ) );
				wp_localize_script( 'give-proactive-ab', 'amp_loc', $select_params );
			}
		}

		/**
		 * Enqueue abandoned tracking admin js file
		 *
		 * @since 1.0.0
		 * @param string $select selected value.
		 * @return void
		 */
		public function select_all_forms( $select = null ) {

			$args = array( 'post_type' => 'give_forms', 'posts_per_page' => -1, 'post_status' => 'publish' );
			$give_forms = get_posts( $args );

			$all_forms = '<select id="amp-give-forms" class="amp-give-forms form-control" name="sub_give_forms">';
			$all_forms .= '<option>' . __( 'Select Form', 'automateplus-mautic-give' ) . '</option>';

			foreach ( $give_forms as $form ) : setup_postdata( $form );

				$all_forms .= APM_RulePanel::make_option( $form->ID, $form->post_title, $select );

				endforeach;

			$all_forms .= '</select>';
			wp_reset_postdata();
			echo $all_forms;
		}

		/**
		 * Enqueue abandoned tracking admin js file
		 *
		 * @since 1.0.0
		 * @param string $footer_text footer default string.
		 * @return string
		 */
		public function send_customers( $footer_text ) {

			$screen = get_current_screen();

			if ( 'settings_page_automate-mautic' == $screen->id ) {

				$refresh_text = '<a type="button" name="refresh-mautic" id="send-give-donors" class="refresh-mautic-data"> ';
				$refresh_text .= __( 'Send Give donors to Mautic', 'automateplus-mautic-give' );
				$refresh_text .= '</a>';
				$footer_text  = $refresh_text . ' | ' . $footer_text;
			}

			return $footer_text;
		}

		/**
		 * Add purchasers to Mautic
		 *
		 * @since 1.0.0
	  	 * @param object $payment payment details.
		 * @return void
		 */
		public function give_mautic_config( $payment ) {

			$payment_id = $payment->ID;
			$status = $payment->post_status;

			$m_tags = array();

			$remove_from_all_segment	= false;

			$give_gateway = apm_get_option( 'amp_give_gateway' );

			$give_payment = apm_get_option( 'amp_give_payment' );

			$give_form_tag	= apm_get_option( 'amp_give_form_tag' );

			$seg_action_id = apm_get_option( 'config_give_segment' );

			$seg_action_failed = apm_get_option( 'config_give_segment_failed' );

			$seg_action_refund = apm_get_option( 'config_give_segment_refund' );

			$seg_action_cancel = apm_get_option( 'config_give_segment_cancel' );

			$seg_action_pending = apm_get_option( 'config_give_segment_pending' );

			$seg_action_revoked = apm_get_option( 'config_give_segment_revoked' );

			$seg_action_ab = apm_get_option( 'config_give_segment_ab' );

			$seg_action_form = apm_get_option( 'config_give_segment_form' );

			$give_form = apm_get_option( 'config_give_form' );

			if ( 1 == apm_get_option( 'apm_give_remove_segment' ) ) {

				$remove_from_all_segment = true;

			} else {
				$remove_from_all_segment = false;
			}

			if ( 1 == apm_get_option( 'remove_segment_ap' ) ) {

				$remove_from_segment_purchase = true;
			} else {
				$remove_from_segment_purchase = false;
			}

			// General global config conditions.
			$all_customer = $customer_failed = $customer_revoked = $customer_abandoned = $customer_pending = $customer_refund = $customer_cancel = $default = array(

				'add_segment' => array(),
				'remove_segment' => array(),
			);

			$curr_form = get_post_meta( $payment_id, '_give_payment_form_id', true );

			// Check if selected form is used.
			if ( $give_form === $curr_form ) {

				array_push( $default['add_segment'], $seg_action_form );
			}

			// all customers to Mautic.
			$all_customer['add_segment'][0] = $seg_action_id;

			// customers with status failed.
			$customer_failed['add_segment'][0] = $seg_action_failed;

			// customers with status revoked.
			$customer_revoked['add_segment'][0] = $seg_action_revoked;

			// customers with status hold.
			$customer_abandoned['add_segment'][0] = $seg_action_ab;

			// customers with status pending.
			$customer_pending['add_segment'][0] = $seg_action_pending;

			// customers with status refund.
			$customer_refund['add_segment'][0] = $seg_action_refund;

			// customers with status cancel.
			$customer_cancel['add_segment'][0] = $seg_action_cancel;

			// remove user from pending if payment is completed.
			$all_customer['remove_segment'][0] = $seg_action_pending;

			if ( ! empty( $default['add_segment'] ) ) {

				$all_customer['add_segment'] = array_merge( $default['add_segment'], $all_customer['add_segment'] );

				$customer_failed['add_segment'] = array_merge( $default['add_segment'], $customer_failed['add_segment'] );

				$customer_revoked['add_segment'] = array_merge( $default['add_segment'], $customer_revoked['add_segment'] );

				$customer_abandoned['add_segment'] = array_merge( $default['add_segment'], $customer_abandoned['add_segment'] );

				$customer_pending['add_segment'] = array_merge( $default['add_segment'], $customer_pending['add_segment'] );

				$customer_refund['add_segment'] = array_merge( $default['add_segment'], $customer_refund['add_segment'] );

				$customer_abandoned['add_segment'] = array_merge( $default['add_segment'], $customer_abandoned['add_segment'] );
			}

			// get the payment meta.
			$payment_meta = get_post_meta( $payment_id, '_give_payment_meta', true );

			// unserialize the payment meta.
			$user_info = maybe_unserialize( $payment_meta['user_info'] );

			// get donor's email.
			$email = get_post_meta( $payment_id, '_give_payment_user_email', true );

			$api_data = AP_Mautic_Api::get_api_method_url( $email );
			$url = $api_data['url'];
			$method = $api_data['method'];

			$body = array(
				'firstname'	=> $user_info['first_name'],
				'lastname'	=> $user_info['last_name'],
				'email'		=> $email,
				'address1'	=> $user_info['address'],
			);

			$m_tags = self::get_tags_by_payment( $payment_id );

			if ( ( ! empty( $m_tags ) ) ) {

				$m_tags = rtrim( $m_tags , ',' );

				$body['tags'] = $m_tags;
			}

			switch ( $status ) {

				case 'publish':

					if ( $remove_from_segment_purchase ) {

						self::remove_from_all_segment( $email );
					}

					if ( ! empty( $all_customer['add_segment'] ) && 'Select Segment' != $all_customer['add_segment'][0] ) {
						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $all_customer );
					}
					break;

				case 'failed':

					if ( ! empty( $customer_failed['add_segment'] ) && 'Select Segment' != $customer_failed['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_failed );
					}
					break;

				case 'refunded':

					if ( $remove_from_all_segment ) {

						self::remove_from_all_segment( $email );
					}

					if ( ! empty( $customer_refund['add_segment'] ) && 'Select Segment' != $customer_refund['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_refund );
					}
					break;

				case 'pending':

					if ( ! empty( $customer_pending['add_segment'] ) && 'Select Segment' != $customer_pending['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_pending );
					}
					break;

				case 'revoked':

					if ( ! empty( $customer_revoked['add_segment'] ) && 'Select Segment' != $customer_revoked['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_revoked );
					}
					break;

				case 'cancelled':

					if ( ! empty( $customer_cancel['add_segment'] ) && 'Select Segment' != $customer_cancel['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_cancel );
					}
					break;

				case 'abandoned':

					if ( ! empty( $customer_abandoned['add_segment'] ) && 'Select Segment' != $customer_abandoned['add_segment'][0] ) {

						AP_Mautic_Api::ampw_mautic_api_call( $url, $method, $body, $customer_abandoned );
					}
					break;
			}
		}

		/**
		 * Add new tab in base plugin
		 *
		 * @since 1.0.0
	  	 * @param string $active_tab active tab.
		 * @return void
		 */
		public function render_give_tab( $active_tab ) {
		?>

			<a href="<?php APM_AdminSettings::render_page_url( '&tab=give_mautic' ); ?>" class="nav-tab <?php echo 'give_mautic' == $active_tab ? 'nav-tab-active' : ''; ?>"> <?php _e( 'GIVE', 'automateplus-mautic-give' ); ?> </a>
		<?php
		}

		/**
		 * Update tab content
		 *
		 * @since 1.0.0
		 * @static
		 * @return void
		 */
		public static function update_give_tab_content() {

			if ( isset( $_POST['amp-mautic-nonce-give'] ) && wp_verify_nonce( $_POST['amp-mautic-nonce-give'], 'ampmauticgive' ) ) {

				$give_options = AMPW_Mautic_Init::get_amp_options();
				$give_options['amp_give_gateway'] = $give_options['amp_give_payment'] = $give_options['amp_give_form_tag'] = $give_options['amp_give_proactive_abandoned'] = $give_options['apm_give_remove_segment'] = $give_options['remove_segment_ap'] = false;

				if ( isset( $_POST['amp_give_gateway'] ) ) {

					$give_options['amp_give_gateway'] = true;
				}

				if ( isset( $_POST['amp_give_payment'] ) ) {

					$give_options['amp_give_payment'] = true;
				}
				if ( isset( $_POST['amp_give_form_tag'] ) ) {

					$give_options['amp_give_form_tag'] = true;
				}
				if ( isset( $_POST['amp_give_proactive_abandoned'] ) ) {

					$give_options['amp_give_proactive_abandoned'] = true;
				}
				if ( isset( $_POST['apm_give_remove_segment'] ) ) {

					$give_options['apm_give_remove_segment'] = true;
				}
				if ( isset( $_POST['remove_segment_ap'] ) ) {

					$give_options['remove_segment_ap'] = true;
				}

				if ( isset( $_POST['ss_seg_action'][0] ) ) {

					$give_options['config_give_segment'] = sanitize_text_field( $_POST['ss_seg_action'][0] );
				}
				if ( isset( $_POST['ss_seg_action'][1] ) ) {

					$give_options['config_give_segment_failed'] = sanitize_text_field( $_POST['ss_seg_action'][1] );
				}
				if ( isset( $_POST['ss_seg_action'][2] ) ) {

					$give_options['config_give_segment_revoked'] = sanitize_text_field( $_POST['ss_seg_action'][2] );
				}
				if ( isset( $_POST['ss_seg_action'][3] ) ) {

					$give_options['config_give_segment_ab'] = sanitize_text_field( $_POST['ss_seg_action'][3] );
				}
				if ( isset( $_POST['ss_seg_action'][4] ) ) {

					$give_options['config_give_segment_pending'] = sanitize_text_field( $_POST['ss_seg_action'][4] );
				}
				if ( isset( $_POST['ss_seg_action'][5] ) ) {

					$give_options['config_give_segment_refund'] = sanitize_text_field( $_POST['ss_seg_action'][5] );
				}
				if ( isset( $_POST['ss_seg_action'][6] ) ) {

					$give_options['config_give_segment_cancel'] = sanitize_text_field( $_POST['ss_seg_action'][6] );
				}
				if ( isset( $_POST['ss_seg_action'][7] ) ) {

					$give_options['config_give_segment_form'] = sanitize_text_field( $_POST['ss_seg_action'][7] );
				}

				if ( isset( $_POST['sub_give_forms'] ) ) {

					$give_options['config_give_form'] = sanitize_text_field( $_POST['sub_give_forms'] );
				}

				update_option( 'ampw_mautic_config', $give_options );

				$redirect = APM_AdminSettings::get_render_page_url( '&tab=give_mautic' );
				wp_redirect( $redirect );
			}
		}

		/**
		 * Get all tags.
		 *
		 * @since 1.0.0
	  	 * @param int $payment_id Payment ID.
		 * @return string
		 */
		public function get_tags_by_payment( $payment_id ) {

			$give_form	= apm_get_option( 'amp_give_form_tag' );

			$give_gateway = apm_get_option( 'amp_give_gateway' );

			$give_payment	= apm_get_option( 'amp_give_payment' );

			// get form name.
			if ( $give_form ) {

				$form_title = get_post_meta( $payment_id, '_give_payment_form_title', true );
				$m_tags .= $form_title . ',';
			}

			// get payment gateway.
			if ( $give_gateway ) {

				$payment_gateway = get_post_meta( $payment_id, '_give_payment_gateway', true );
				$m_tags .= $payment_gateway . ',';
			}

			// get payment mode.
			if ( $give_payment ) {

				$payment_mode = get_post_meta( $payment_id, '_give_payment_mode', true );
				$m_tags .= $payment_mode . ',';
			}

			return $m_tags;
		}

		/**
		 * Remove contact from all segments
		 *
		 * @since 1.0.0
		 * @static
	  	 * @param string $email contact email.
		 * @return void
		 */
		public static function remove_from_all_segment( $email ) {

			$contact_id = self::get_mautic_contact_id( $email );

			if ( isset( $contact_id ) ) {

				$url = '/api/contacts/' . $contact_id . '/segments';
				$method = 'GET';

				$segments = AP_Mautic_Api::ampw_mautic_api_call( $url, $method );

				$credentials = AMPW_Mautic_Init::get_mautic_credentials();

				if ( empty( $segments ) ) {

						return;
				}

				foreach ( $segments->lists as $list ) {

					$segment_id = $list->id;

					$action = 'remove';
					AP_Mautic_Api::mautic_contact_to_segment( $segment_id, $contact_id, $credentials, $action );
				}
			}
		}

		/**
		 * Get Mautic contact ID by email
		 *
		 * @since 1.0.0
		 *
		 * @static
		 * @param string $email contact email.
		 * @return int
		 */
		public static function get_mautic_contact_id( $email ) {

			$credentials = AMPW_Mautic_Init::get_mautic_credentials();

			if ( isset( $_COOKIE['mtc_id'] ) ) {

				$contact_id = esc_attr( $_COOKIE['mtc_id'] );
				$email_cid = AP_Mautic_Api::mautic_get_contact_by_email( $email, $credentials );
				if ( isset( $email_cid ) ) {

					$contact_id = $email_cid;
				}
			} else {
				$contact_id = AP_Mautic_Api::mautic_get_contact_by_email( $email, $credentials );

			}

			return $contact_id;
		}

		/**
		 * Render tab content
		 *
		 * @since 1.0.0
		 * @param string $active tab name.
		 * @return void
		 */
		public function render_give_tab_content( $active ) {

			if ( 'give_mautic' == $active ) {

				$give_gateway	= apm_get_option( 'amp_give_gateway' );

				$give_payment = apm_get_option( 'amp_give_payment' );

				$give_form_tag	= apm_get_option( 'amp_give_form_tag' );

				$proactive_tracking = apm_get_option( 'amp_give_proactive_abandoned' );

				$apm_give_remove_segment = apm_get_option( 'apm_give_remove_segment' );

				$remove_segment_ap = apm_get_option( 'remove_segment_ap' );

				$ss_seg_action = apm_get_option( 'config_give_segment' );

				$ss_seg_action_failed = apm_get_option( 'config_give_segment_failed' );

				$ss_seg_action_refund = apm_get_option( 'config_give_segment_refund' );

				$ss_seg_action_cancel = apm_get_option( 'config_give_segment_cancel' );

				$ss_seg_action_pending = apm_get_option( 'config_give_segment_pending' );

				$ss_seg_action_revoked = apm_get_option( 'config_give_segment_revoked' );

				$ss_seg_action_hold = apm_get_option( 'config_give_segment_ab' );

				$seg_action_form = apm_get_option( 'config_give_segment_form' );

				$give_form = apm_get_option( 'config_give_form' );
				?>

				<br class="clear" />
				<table class="form-table widefat">
					<thead>
					<tr>
						<td class="row-title"><b><?php esc_attr_e( 'Condition', 'automateplus-mautic-give' ); ?></b></td>
						<td><b><?php esc_attr_e( 'Add to Segment', 'automateplus-mautic-give' ); ?></b></td>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td class="row"><?php _e( 'After complete donation, add users to:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action ); ?><input type="checkbox" style="margin-left: 2%;" class="amp-enabled-panels" name="remove_segment_ap" value="" <?php checked( 1, $remove_segment_ap ); ?> ><?php _e( 'Remove users from all segments', 'automateplus-mautic-give' ); ?></td>
					</tr>
					<tr>
						<td class="row"><label for="tablecell"><?php _e( 'Add customer with failed order to:', 'automateplus-mautic-give' ); ?></label></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_failed ); ?></td>
					</tr>
					<tr>
						<td class="row"><?php _e( 'Add customer with revoked order to:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_revoked ); ?></td>
					</tr>
					<tr>
						<td class="row"><?php _e( 'Add customer with abandoned order to:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_hold ); ?><input type="checkbox" style="margin-left: 2%;" class="amp-enabled-panels" name="amp_give_proactive_abandoned" value="" <?php checked( 1, $proactive_tracking ); ?> ><?php _e( 'Enable Proactive Abandonment Tracking', 'automateplus-mautic-give' ); ?></td>
					</tr>
					<tr>
						<td class="row"><?php _e( 'Add customer with pending order to:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_pending ); ?></td>
					</tr>
					<tr>
						<td class="row"><?php _e( 'Add refunded users in:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_refund ); ?><input type="checkbox" style="margin-left: 2%;" class="amp-enabled-panels" name="apm_give_remove_segment" value="" <?php checked( 1, $apm_give_remove_segment ); ?> ><?php _e( 'Remove users from all segments', 'automateplus-mautic-give' ); ?></td>
					</tr>
					<tr>
						<td class="row"><?php _e( 'Add cancelled donors in:', 'automateplus-mautic-give' ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $ss_seg_action_cancel ); ?></td>
					</tr>
					</tbody>
				</table>

				<br class="clear" />
				<table class="form-table widefat">
					<thead>
						<tr>
							<td class="row-title" style="width: 40%;"><b><?php esc_attr_e( 'Condition', 'automateplus-mautic-give' ); ?></b></td>
							<td class="row-title" style="width: 30%;"><b><?php esc_attr_e( 'Give Form', 'automateplus-mautic-give' ); ?></b></td>
							<td style="width: 30%;"><b><?php esc_attr_e( 'Add to Segment', 'automateplus-mautic-give' ); ?></b></td>
						</tr>
					</thead>
					<tbody>
					<tr>
						<td class="row"><?php _e( 'Add users who donate with specific form', 'automateplus-mautic-give' ); ?></td>
						<td class="row"><?php APM_RulePanel::select_all_segments( $seg_action_form ); ?></td>
						<td><?php APM_RulePanel::select_all_segments( $seg_action_form ); ?></td>
					</tr>
					</tbody>
				</table>

				<br class="clear" />

				<table class="form-table widefat">
					<thead>
						<tr>
							<td class="row-title"><b><?php esc_attr_e( 'Add Mautic Tags', 'automateplus-mautic-give' ); ?></b></td>
						</tr>
					</thead>
					<tbody>
					<tr>
						<td class="row"><input type="checkbox" class="amp-enabled-panels" name="amp_give_form_tag" value="" <?php checked( 1, $give_form_tag ); ?> ><?php _e( 'Automatically add Give form title as a tag in Mautic', 'automateplus-mautic-give' ); ?></td>
					</tr>
					<tr>
						<td class="row"><input type="checkbox" class="amp-enabled-panels" name="amp_give_gateway" value="" <?php checked( 1, $give_gateway ); ?> ><?php _e( 'Automatically add Give payment gateway as a tag in Mautic', 'automateplus-mautic-give' ); ?></td>
					</tr>
					<tr>
						<td class="row"><input type="checkbox" class="amp-enabled-panels" name="amp_give_payment" value="" <?php checked( 1, $give_payment ); ?> ><?php _e( 'Automatically add Give payment mode as a tag in Mautic
', 'automateplus-mautic-give' ); ?></td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="save-give-tab" id="save-amp-settings" class="button-primary button button-large" value="<?php esc_attr_e( 'Save Settings', 'automateplus-mautic-give' ); ?>" />
					<span class="spinner apm-wp-spinner" style="float: none;margin-bottom: 0.5em;"></span>
				</p>
				<?php wp_nonce_field( 'ampmauticgive', 'amp-mautic-nonce-give' );
			}
		}

	} // end of class
	add_action( 'plugins_loaded', 'APMautic_Give::instance' );
endif;

