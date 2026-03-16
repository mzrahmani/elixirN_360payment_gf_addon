<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElixirN_360p_Utils {
	const META_KEYS = array(
		'status'           => 'elixirN_360payment_status',
		'process_id'       => 'elixirN_360payment_process_id',
		'ref_id'           => 'elixirN_360payment_ref_id',
		'url'              => 'elixirN_360payment_url',
		'amount'           => 'elixirN_360payment_amount',
		'requested_at'     => 'elixirN_360payment_requested_at',
		'confirmed_at'     => 'elixirN_360payment_confirmed_at',
		'declined_at'      => 'elixirN_360payment_declined_at',
		'last_error'       => 'elixirN_360payment_last_error',
		'last_webhook_at'  => 'elixirN_360payment_last_webhook_at',
		'webhook_mode'     => 'elixirN_360payment_webhook_mode',
		'webhook_payload'  => 'elixirN_360payment_webhook_payload',
		'feed_id'          => 'elixirN_360payment_feed_id',
		'request_payload'  => 'elixirN_360payment_request_payload',
		'refund_status'    => 'elixirN_360payment_refund_status',
		'void_status'      => 'elixirN_360payment_void_status',
		'gateway_response' => 'elixirN_360payment_gateway_response',
		'gateway_message'  => 'elixirN_360payment_gateway_message',
	);

	const STATUSES = array(
		'not_started',
		'request_created',
		'pending',
		'approved',
		'declined',
		'failed',
		'refunded',
		'voided',
		'webhook_error',
	);

	public static function now_mysql() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function get_meta( $entry_id, $logical_key, $default = '' ) {
		$key = self::META_KEYS[ $logical_key ] ?? '';
		if ( ! $key ) {
			return $default;
		}

		$value = gform_get_meta( $entry_id, $key );
		return '' === $value || null === $value ? $default : $value;
	}

	public static function update_meta( $entry_id, $logical_key, $value ) {
		$key = self::META_KEYS[ $logical_key ] ?? '';
		if ( ! $key ) {
			return false;
		}

		$entry_meta = apply_filters(
			'elixirN_360payment_entry_meta',
			array(
				'entry_id' => (int) $entry_id,
				'key'      => $key,
				'value'    => $value,
			)
		);

		return gform_update_meta( (int) $entry_meta['entry_id'], $entry_meta['key'], $entry_meta['value'] );
	}

	public static function sanitize_webhook_mode( $mode ) {
		return in_array( $mode, array( 'shared_existing_webhook', 'addon_webhook_endpoint' ), true ) ? $mode : 'shared_existing_webhook';
	}

	public static function map_gateway_status( $status ) {
		$clean = strtolower( (string) $status );
		$map   = array(
			'approved' => 'approved',
			'declined' => 'declined',
			'failed'   => 'failed',
			'voided'   => 'voided',
			'refunded' => 'refunded',
			'pending'  => 'pending',
		);

		return $map[ $clean ] ?? 'webhook_error';
	}

	public static function transition_status( $entry, $new_status, $context = array() ) {
		$entry_id    = (int) rgar( $entry, 'id' );
		$old_status  = self::get_meta( $entry_id, 'status', 'not_started' );
		$new_status  = in_array( $new_status, self::STATUSES, true ) ? $new_status : 'webhook_error';
		$transition  = compact( 'old_status', 'new_status' );
		$transition += $context;

		do_action( 'elixirN_360payment_status_transition', $entry, $transition );
		self::update_meta( $entry_id, 'status', $new_status );
	}

	public static function find_entry_id_by_meta( $meta_key, $meta_value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gf_entry_meta';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT entry_id FROM {$table} WHERE meta_key=%s AND meta_value=%s LIMIT 1", $meta_key, $meta_value ) );
	}

	public static function maybe_json_encode( $value ) {
		return is_string( $value ) ? $value : wp_json_encode( $value );
	}
}
