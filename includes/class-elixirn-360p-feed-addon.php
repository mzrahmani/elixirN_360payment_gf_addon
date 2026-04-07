<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFForms' ) || ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
	return;
}

GFForms::include_feed_addon_framework();

if ( ! class_exists( 'GFFeedAddOn' ) ) {
	return;
}

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
					array( 'name' => 'default_partner_id', 'label' => 'Default Partner ID', 'type' => 'text' ),
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
					array(
						'name'        => 'field_mappings',
						'label'       => 'Field mappings',
						'type'        => 'generic_map',
						'limit'       => 10,
						'key_field'   => array(
							'title'            => '360Payments field',
							'allow_custom'     => false,
							'allow_duplicates' => false,
							'choices'          => $this->get_payment_field_mapping_choices(),
						),
						'value_field' => array(
							'title'             => 'Form field or custom value',
							'allow_custom'      => true,
							'custom_value_type' => 'text',
							'placeholder'       => 'Select a form field or enter a custom value',
						),
					),
					array( 'name' => 'app_name', 'label' => 'App Name', 'type' => 'text', 'default_value' => $this->get_plugin_setting( 'default_app_name' ) ),
					array( 'name' => 'api_token', 'label' => 'API Token', 'type' => 'text', 'default_value' => $this->get_plugin_setting( 'default_api_token' ) ),
					array( 'name' => 'api_version', 'label' => 'API Version', 'type' => 'text', 'default_value' => $this->get_plugin_setting( 'default_api_version' ) ),
					array( 'name' => 'terminal_id', 'label' => 'terminal ID', 'type' => 'text', 'default_value' => $this->get_plugin_setting( 'default_terminal_id' ) ),
					array( 'name' => 'partner_id', 'label' => 'partner ID', 'type' => 'text', 'default_value' => $this->get_plugin_setting( 'default_partner_id' ) ),
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

	public function feed_list_columns() {
		return array(
			'feedName' => __( 'Feed Name', 'elixirn-gf-360payment' ),
			'amount'   => __( 'Amount', 'elixirn-gf-360payment' ),
		);
	}

	public function get_column_value_amount( $feed ) {
		$mappings     = $this->get_feed_mapping_meta( $feed );
		$amount_value = rgar( $mappings, 'amount', '' );

		if ( '' === (string) $amount_value ) {
			return '&mdash;';
		}

		$form = GFAPI::get_form( rgar( $feed, 'form_id' ) );
		if ( ! empty( $form ) && is_array( $form ) ) {
			$field = GFFormsModel::get_field( $form, $amount_value );
			if ( $field ) {
				return esc_html( $field->get_field_label( false, false ) );
			}
		}

		return esc_html( (string) $amount_value );
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
		$mappings   = $this->get_field_mappings( $feed, $form, $entry );
		$field_keys = array(
			'firstName' => 'firstName',
			'lastName'  => 'lastName',
			'email'     => 'email',
			'phone'     => 'phone',
			'address'   => 'address',
			'address2'  => 'address2',
			'city'      => 'city',
			'state'     => 'state',
			'zip'       => 'zip',
		);
		$payload    = array(
			'amount'        => rgar( $mappings, 'amount', '' ),
			'terminalId'    => rgar( $feed['meta'], 'terminal_id', $this->get_plugin_setting( 'default_terminal_id' ) ),
			'partnerId'     => rgar( $feed['meta'], 'partner_id', $this->get_plugin_setting( 'default_partner_id' ) ),
			'hideSignature' => ! empty( rgar( $feed['meta'], 'hide_signature' ) ),
		);

		foreach ( $field_keys as $api_key => $mapping_key ) {
			$payload[ $api_key ] = rgar( $mappings, $mapping_key, '' );
		}
		return $payload;
	}

	private function get_field_mappings( $feed, $form, $entry ) {
		$mappings = $this->get_generic_map_fields( $feed, 'field_mappings', $form, $entry );
		if ( ! empty( $mappings ) ) {
			return $mappings;
		}

		return array(
			'amount'    => rgar( $entry, rgar( $feed['meta'], 'mapped_amount_field' ) ),
			'firstName' => rgar( $entry, rgar( $feed['meta'], 'first_name_field' ) ),
			'lastName'  => rgar( $entry, rgar( $feed['meta'], 'last_name_field' ) ),
			'email'     => rgar( $entry, rgar( $feed['meta'], 'email_field' ) ),
			'phone'     => rgar( $entry, rgar( $feed['meta'], 'phone_field' ) ),
			'address'   => rgar( $entry, rgar( $feed['meta'], 'address1_field' ) ),
			'address2'  => rgar( $entry, rgar( $feed['meta'], 'address2_field' ) ),
			'city'      => rgar( $entry, rgar( $feed['meta'], 'city_field' ) ),
			'state'     => rgar( $entry, rgar( $feed['meta'], 'state_field' ) ),
			'zip'       => rgar( $entry, rgar( $feed['meta'], 'zip_field' ) ),
		);
	}

	private function get_feed_mapping_meta( $feed ) {
		$mapping_rows = rgar( $feed['meta'], 'field_mappings' );
		if ( ! is_array( $mapping_rows ) ) {
			return array();
		}

		$mappings = array();
		foreach ( $mapping_rows as $mapping_row ) {
			if ( ! is_array( $mapping_row ) ) {
				continue;
			}

			$key = '';
			foreach ( array( 'key', 'custom_key', 'field' ) as $candidate ) {
				if ( isset( $mapping_row[ $candidate ] ) && '' !== (string) $mapping_row[ $candidate ] ) {
					$key = (string) $mapping_row[ $candidate ];
					break;
				}
			}

			if ( '' === $key ) {
				continue;
			}

			$value = '';
			foreach ( array( 'value', 'custom_value' ) as $candidate ) {
				if ( isset( $mapping_row[ $candidate ] ) && '' !== (string) $mapping_row[ $candidate ] ) {
					$value = (string) $mapping_row[ $candidate ];
					break;
				}
			}

			$mappings[ $key ] = $value;
		}

		return $mappings;
	}

	private function get_payment_field_mapping_choices() {
		return array(
			array( 'label' => 'Amount', 'value' => 'amount' ),
			array( 'label' => 'First name', 'value' => 'firstName' ),
			array( 'label' => 'Last name', 'value' => 'lastName' ),
			array( 'label' => 'Email', 'value' => 'email' ),
			array( 'label' => 'Phone', 'value' => 'phone' ),
			array( 'label' => 'Address line 1', 'value' => 'address' ),
			array( 'label' => 'Address line 2', 'value' => 'address2' ),
			array( 'label' => 'City', 'value' => 'city' ),
			array( 'label' => 'State', 'value' => 'state' ),
			array( 'label' => 'Zip', 'value' => 'zip' ),
		);
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
