<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_feed_addon_framework();

class ElixirN_360p_Feed_AddOn extends GFFeedAddOn {
	protected $_version = ELIXIRN_360P_VERSION;
	protected $_min_gravityforms_version = '2.7';
	protected $_slug = 'elixirn-360payment';
	protected $_path = 'elixirnotions-360payments-gravityforms-addon/elixirnotions-360payments-gravityforms-addon.php';
	protected $_full_path = ELIXIRN_360P_PLUGIN_FILE;
	protected $_title = 'ElixirNotions 360Payments Gravity Forms add-on';
	protected $_short_title = '360Payments';
	private static $_instance = null;
	private $api;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function init() {
		parent::init();
		$this->api = new ElixirN_360p_Payment_API();
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		add_filter( 'gform_confirmation', array( $this, 'append_shortcode_to_confirmation' ), 20, 4 );
		add_filter( 'gform_entry_meta', array( $this, 'register_entry_meta' ), 10, 2 );
	}

	public function init_admin() {
		parent::init_admin();
		add_action( 'admin_footer', array( $this, 'render_feed_settings_script' ) );
	}

	public function register_webhook_route() {
		register_rest_route(
			ELIXIRN_360P_REST_NAMESPACE,
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->api, 'process_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function render_feed_settings_script() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'gravityforms' ) ) {
			return;
		}

		?>
		<script>
			(function($) {
				if (!$) {
					return;
				}

				function getField(fieldName) {
					return $('#_gaddon_setting_' + fieldName + ', [name="_gaddon_setting_' + fieldName + '"], [name$="[' + fieldName + ']"]').first();
				}

				function getSettingRow(fieldName) {
					var field = getField(fieldName);
					if (!field.length) {
						return $();
					}

					return field.closest('tr, li, .gform-settings__field, .gaddon-setting-row, .gforms_form_settings');
				}

				function toggleAmountFields() {
					var modeField = getField('amount_mode');
					if (!modeField.length) {
						return;
					}

					var mode = modeField.val();
					var fixedRow = getSettingRow('fixed_amount');
					var mappedRow = getSettingRow('mapped_amount_field');

					if (fixedRow.length) {
						fixedRow.toggle(mode === 'fixed');
					}

					if (mappedRow.length) {
						mappedRow.toggle(mode === 'mapped');
					}
				}

				$(document).on('change', '#_gaddon_setting_amount_mode, [name="_gaddon_setting_amount_mode"], [name$="[amount_mode]"]', toggleAmountFields);
				$(window).on('load', toggleAmountFields);
				$(toggleAmountFields);

				if (window.MutationObserver) {
					new MutationObserver(toggleAmountFields).observe(document.body, {
						childList: true,
						subtree: true
					});
				}
			})(window.jQuery);
		</script>
		<?php
	}


	public function register_entry_meta( $entry_meta, $form_id ) {
		foreach ( ElixirN_360p_Utils::META_KEYS as $logical => $key ) {
			$entry_meta[ $key ] = array(
				'label' => ucwords( str_replace( '_', ' ', $logical ) ),
				'is_numeric' => false,
				'is_default_column' => false,
			);
		}
		return $entry_meta;
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => __( '360Payment Global Settings', 'elixirn-gf-360payment' ),
				'fields' => array(
					array( 'name' => 'payment_url_generator', 'label' => '360payment URL generator endpoint', 'type' => 'text' ),
					array( 'name' => 'refund_endpoint', 'label' => '360payment refund endpoint', 'type' => 'text' ),
					array( 'name' => 'void_endpoint', 'label' => '360payment void endpoint', 'type' => 'text' ),
					array( 'name' => 'webhook_mode_default', 'label' => 'Webhook mode default', 'type' => 'select', 'choices' => array( array( 'label' => 'shared_existing_webhook', 'value' => 'shared_existing_webhook' ), array( 'label' => 'addon_webhook_endpoint', 'value' => 'addon_webhook_endpoint' ) ) ),
					array( 'name' => 'existing_shared_webhook', 'label' => 'Existing shared webhook URL', 'type' => 'text' ),
					array( 'name' => 'addon_webhook_display', 'label' => 'Dedicated add-on webhook URL', 'type' => 'text', 'readonly' => true, 'default_value' => rest_url( ELIXIRN_360P_REST_NAMESPACE . '/webhook' ) ),
					array( 'name' => 'debug_enabled', 'label' => 'Enable debug logging', 'type' => 'checkbox', 'choices' => array( array( 'name' => 'debug_enabled', 'label' => 'Enable' ) ) ),
					array( 'name' => 'logging_verbosity', 'label' => 'Logging verbosity', 'type' => 'select', 'choices' => array( array( 'label' => 'normal', 'value' => 'normal' ), array( 'label' => 'verbose', 'value' => 'verbose' ) ) ),
					array( 'name' => 'default_terminal_id', 'label' => 'Default Terminal ID', 'type' => 'text' ),
					array( 'name' => 'default_app_name', 'label' => 'Default App name', 'type' => 'text' ),
					array( 'name' => 'default_api_token', 'label' => 'Default API Token', 'type' => 'text' ),
					array( 'name' => 'default_api_version', 'label' => 'Default API Version', 'type' => 'text' ),
				),
			),
		);
	}

	public function feed_settings_fields() {
		return array(
			array(
				'title'  => __( 'Feed Settings', 'elixirn-gf-360payment' ),
				'fields' => array(
					array( 'name' => 'feedName', 'label' => 'Feed Name', 'type' => 'text', 'required' => true ),
					array( 'name' => 'isActive', 'label' => 'Feed Active', 'type' => 'toggle' ),
					array( 'name' => 'amount_mode', 'label' => 'Payment amount mode', 'type' => 'select', 'choices' => array( array( 'label' => 'fixed amount', 'value' => 'fixed' ), array( 'label' => 'mapped field amount', 'value' => 'mapped' ) ) ),
					array( 'name' => 'fixed_amount', 'label' => 'Fixed amount', 'type' => 'text' ),
					array( 'name' => 'mapped_amount_field', 'label' => 'Mapped amount field', 'type' => 'field_select' ),
					array( 'name' => 'mapped_amount_custom', 'label' => 'Mapped amount custom value', 'type' => 'text' ),
					array( 'name' => 'first_name_field', 'label' => 'first name', 'type' => 'field_select' ),
					array( 'name' => 'first_name_custom', 'label' => 'first name custom value', 'type' => 'text' ),
					array( 'name' => 'last_name_field', 'label' => 'last name', 'type' => 'field_select' ),
					array( 'name' => 'last_name_custom', 'label' => 'last name custom value', 'type' => 'text' ),
					array( 'name' => 'email_field', 'label' => 'email', 'type' => 'field_select' ),
					array( 'name' => 'email_custom', 'label' => 'email custom value', 'type' => 'text' ),
					array( 'name' => 'phone_field', 'label' => 'phone', 'type' => 'field_select' ),
					array( 'name' => 'phone_custom', 'label' => 'phone custom value', 'type' => 'text' ),
					array( 'name' => 'address1_field', 'label' => 'address line 1', 'type' => 'field_select' ),
					array( 'name' => 'address1_custom', 'label' => 'address line 1 custom value', 'type' => 'text' ),
					array( 'name' => 'address2_field', 'label' => 'address line 2', 'type' => 'field_select' ),
					array( 'name' => 'address2_custom', 'label' => 'address line 2 custom value', 'type' => 'text' ),
					array( 'name' => 'city_field', 'label' => 'city', 'type' => 'field_select' ),
					array( 'name' => 'city_custom', 'label' => 'city custom value', 'type' => 'text' ),
					array( 'name' => 'state_field', 'label' => 'state', 'type' => 'field_select' ),
					array( 'name' => 'state_custom', 'label' => 'state custom value', 'type' => 'text' ),
					array( 'name' => 'zip_field', 'label' => 'zip', 'type' => 'field_select' ),
					array( 'name' => 'zip_custom', 'label' => 'zip custom value', 'type' => 'text' ),
					array( 'name' => 'app_name', 'label' => 'App Name', 'type' => 'text' ),
					array( 'name' => 'api_token', 'label' => 'API Token', 'type' => 'text' ),
					array( 'name' => 'api_version', 'label' => 'API Version', 'type' => 'text' ),
					array( 'name' => 'terminal_id', 'label' => 'terminal ID', 'type' => 'text' ),
					array( 'name' => 'partner_id', 'label' => 'partner ID', 'type' => 'text' ),
					array( 'name' => 'hide_signature', 'label' => 'hide signature', 'type' => 'checkbox', 'choices' => array( array( 'name' => 'hide_signature', 'label' => 'Hide signature' ) ) ),
					array( 'name' => 'append_shortcode', 'label' => 'enable automatic shortcode append', 'type' => 'checkbox', 'choices' => array( array( 'name' => 'append_shortcode', 'label' => 'Enable' ) ) ),
					array( 'name' => 'button_label', 'label' => 'button label', 'type' => 'text' ),
					array( 'name' => 'iframe_height', 'label' => 'iframe height', 'type' => 'text', 'default_value' => '600' ),
					array( 'name' => 'iframe_max_width', 'label' => 'iframe max width', 'type' => 'text', 'default_value' => '100%' ),
					array( 'name' => 'wrapper_css_class', 'label' => 'wrapper CSS class', 'type' => 'text' ),
					array( 'name' => 'webhook_mode_override', 'label' => 'Webhook mode override', 'type' => 'select', 'choices' => array( array( 'label' => 'use existing shared webhook', 'value' => 'shared_existing_webhook' ), array( 'label' => 'use add-on webhook endpoint', 'value' => 'addon_webhook_endpoint' ) ) ),
					array( 'name' => 'allow_refund_void', 'label' => 'Refund/void admin support', 'type' => 'checkbox', 'choices' => array( array( 'name' => 'allow_refund_void', 'label' => 'Enable' ) ) ),
				),
			),
		);
	}

	public function process_feed( $feed, $entry, $form ) {
		if ( empty( rgar( $feed['meta'], 'isActive' ) ) ) {
			ElixirN_360p_Logger::log( 'info', 'feed execution skip', array( 'reason' => 'inactive', 'entry_id' => $entry['id'], 'feed_id' => $feed['id'] ) );
			return;
		}
		if ( $this->is_feed_condition_met( $feed, $form, $entry ) === false ) {
			ElixirN_360p_Logger::log( 'info', 'feed execution skip', array( 'reason' => 'conditional_logic', 'entry_id' => $entry['id'], 'feed_id' => $feed['id'] ) );
			return;
		}

		$payload = $this->build_payload( $feed, $entry, $form );
		ElixirN_360p_Logger::log( 'info', 'payload assembly', array( 'entry_id' => $entry['id'], 'feed_id' => $feed['id'], 'payload' => $payload ) );

		$result = $this->api->create_session( $entry, $form, $feed, $payload );
		if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
			ElixirN_360p_Utils::transition_status( $entry, 'failed', array( 'source' => 'request' ) );
			ElixirN_360p_Utils::update_meta( $entry['id'], 'last_error', is_wp_error( $result ) ? $result->get_error_message() : 'Missing URL in response' );
			return;
		}

		$this->persist_request_result( $entry['id'], $feed, $payload, $result );
	}

	public function append_shortcode_to_confirmation( $confirmation, $form, $entry, $is_ajax ) {
		$feeds = $this->get_feeds( $form['id'] );
		if ( empty( $feeds ) ) {
			return $confirmation;
		}
		$feed = $feeds[0];
		if ( empty( rgar( $feed['meta'], 'append_shortcode' ) ) ) {
			return $confirmation;
		}

		$status = ElixirN_360p_Utils::get_meta( $entry['id'], 'status', 'not_started' );
		if ( ! in_array( $status, array( 'request_created', 'pending' ), true ) ) {
			$fallback = '<p>' . esc_html__( 'We could not create your payment session. Please contact support.', 'elixirn-gf-360payment' ) . '</p>';
			ElixirN_360p_Logger::log( 'error', 'shortcode append fallback', array( 'entry_id' => $entry['id'], 'status' => $status ) );
			return is_array( $confirmation ) ? $confirmation : $confirmation . $fallback;
		}

		$shortcode = sprintf( '[elixirN_360payment_iframe entry_id="%d"]', (int) $entry['id'] );
		ElixirN_360p_Logger::log( 'info', 'shortcode append', array( 'entry_id' => $entry['id'] ) );
		return is_array( $confirmation ) ? $confirmation : $confirmation . do_shortcode( $shortcode );
	}

	private function build_payload( $feed, $entry, $form ) {
		$meta       = $feed['meta'];
		$amount     = 'mapped' === rgar( $meta, 'amount_mode' ) ? $this->resolve_feed_value( $meta, $entry, 'mapped_amount_field', 'mapped_amount_custom' ) : rgar( $meta, 'fixed_amount' );
		$field_keys = array(
			'firstName' => array(
				'field'  => 'first_name_field',
				'custom' => 'first_name_custom',
			),
			'lastName'  => array(
				'field'  => 'last_name_field',
				'custom' => 'last_name_custom',
			),
			'email'     => array(
				'field'  => 'email_field',
				'custom' => 'email_custom',
			),
			'phone'     => array(
				'field'  => 'phone_field',
				'custom' => 'phone_custom',
			),
			'address'   => array(
				'field'  => 'address1_field',
				'custom' => 'address1_custom',
			),
			'address2'  => array(
				'field'  => 'address2_field',
				'custom' => 'address2_custom',
			),
			'city'      => array(
				'field'  => 'city_field',
				'custom' => 'city_custom',
			),
			'state'     => array(
				'field'  => 'state_field',
				'custom' => 'state_custom',
			),
			'zip'       => array(
				'field'  => 'zip_field',
				'custom' => 'zip_custom',
			),
		);
		$payload    = array(
			'amount'        => $amount,
			'terminalId'    => rgar( $meta, 'terminal_id', $this->get_plugin_setting( 'default_terminal_id' ) ),
			'partnerId'     => rgar( $meta, 'partner_id' ),
			'hideSignature' => ! empty( rgar( $meta, 'hide_signature' ) ),
		);

		foreach ( $field_keys as $api_key => $field_config ) {
			$payload[ $api_key ] = $this->resolve_feed_value( $meta, $entry, $field_config['field'], $field_config['custom'] );
		}
		return $payload;
	}

	private function resolve_feed_value( $meta, $entry, $field_key, $custom_key ) {
		$custom_value = rgar( $meta, $custom_key );
		if ( '' !== trim( (string) $custom_value ) ) {
			return $custom_value;
		}

		$field_id = rgar( $meta, $field_key );
		return rgar( $entry, $field_id );
	}

	private function persist_request_result( $entry_id, $feed, $payload, $result ) {
		ElixirN_360p_Utils::update_meta( $entry_id, 'feed_id', $feed['id'] );
		ElixirN_360p_Utils::update_meta( $entry_id, 'request_payload', wp_json_encode( $payload ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'process_id', rgar( $result, 'processId', '' ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'ref_id', rgar( $result, 'refId', '' ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'url', rgar( $result, 'url', '' ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'amount', rgar( $payload, 'amount' ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'requested_at', ElixirN_360p_Utils::now_mysql() );
		ElixirN_360p_Utils::update_meta( $entry_id, 'webhook_mode', ElixirN_360p_Utils::sanitize_webhook_mode( rgar( $feed['meta'], 'webhook_mode_override', $this->get_plugin_setting( 'webhook_mode_default' ) ) ) );
		ElixirN_360p_Utils::transition_status( array( 'id' => $entry_id ), 'request_created', array( 'source' => 'request' ) );
	}
}
