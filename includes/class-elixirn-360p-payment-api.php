<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElixirN_360p_Payment_API {
	public function create_session( $entry, $form, $feed, $payload ) {
		$headers  = $this->build_headers( $feed );
		$endpoint = elixirN_360p_feed_addon()->get_plugin_setting( 'payment_url_generator' );

		$args = array(
			'headers' => apply_filters( 'elixirN_360payment_request_headers', $headers, $entry, $form, $feed, $payload ),
			'body'    => wp_json_encode( apply_filters( 'elixirN_360payment_request_args', $payload, $entry, $form, $feed ) ),
		);

		ElixirN_360p_Logger::log( 'info', 'create_session request', array( 'entry_id' => rgar( $entry, 'id' ), 'feed_id' => rgar( $feed, 'id' ), 'endpoint' => $endpoint, 'headers' => $args['headers'], 'payload' => $payload ) );

		$response = wp_remote_post( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			ElixirN_360p_Logger::log( 'critical', 'create_session wp_error', array( 'error' => $response->get_error_message() ), true );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		do_action( 'elixirN_360payment_request_result', $body, $entry, $form, $feed, $payload );
		return $body;
	}

	public function refund( $entry, $feed, $amount ) {
		$endpoint = elixirN_360p_feed_addon()->get_plugin_setting( 'refund_endpoint' );
		$args     = array(
			'refId'  => ElixirN_360p_Utils::get_meta( $entry['id'], 'ref_id' ),
			'amount' => $amount,
		);
		$args     = apply_filters( 'elixirN_360payment_refund_request_args', $args, $entry, null, $feed );
		return $this->post_action( $endpoint, $args, $feed, 'refund' );
	}

	public function void( $entry, $feed ) {
		$endpoint = elixirN_360p_feed_addon()->get_plugin_setting( 'void_endpoint' );
		$args     = array(
			'refId' => ElixirN_360p_Utils::get_meta( $entry['id'], 'ref_id' ),
		);
		$args     = apply_filters( 'elixirN_360payment_void_request_args', $args, $entry, null, $feed );
		return $this->post_action( $endpoint, $args, $feed, 'void' );
	}

	public function process_webhook( WP_REST_Request $request ) {
		$payload = apply_filters( 'elixirN_360payment_webhook_payload', $request->get_json_params() );
		ElixirN_360p_Logger::log( 'info', 'webhook receipt', array( 'payload' => $payload ) );

		$payment = is_array( $payload ) && isset( $payload['payment'] ) ? $payload['payment'] : $payload;
		if ( empty( $payment ) || ! is_array( $payment ) ) {
			ElixirN_360p_Logger::log( 'critical', 'malformed webhook payload', array( 'payload' => $payload ), true );
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Malformed payload' ), 400 );
		}

		$entry_id = $this->match_entry_id( $payment );
		if ( ! $entry_id ) {
			ElixirN_360p_Logger::log( 'error', 'unmatched webhook payload', $payment, true );
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'Unmatched callback' ), 404 );
		}

		$entry  = GFAPI::get_entry( $entry_id );
		$status = ElixirN_360p_Utils::map_gateway_status( rgar( $payment, 'status' ) );

		$current = ElixirN_360p_Utils::get_meta( $entry_id, 'status', 'not_started' );
		if ( in_array( $current, array( 'approved', 'refunded', 'voided' ), true ) && $status === $current ) {
			return new WP_REST_Response( array( 'ok' => true, 'message' => 'Duplicate ignored' ), 200 );
		}

		ElixirN_360p_Utils::update_meta( $entry_id, 'webhook_payload', wp_json_encode( $payload ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'last_webhook_at', ElixirN_360p_Utils::now_mysql() );
		ElixirN_360p_Utils::update_meta( $entry_id, 'gateway_message', rgar( $payment, 'message', '' ) );
		ElixirN_360p_Utils::update_meta( $entry_id, 'gateway_response', rgar( $payment, 'code', '' ) );
		ElixirN_360p_Utils::transition_status( $entry, $status, array( 'source' => 'webhook' ) );

		if ( 'approved' === $status ) {
			ElixirN_360p_Utils::update_meta( $entry_id, 'confirmed_at', ElixirN_360p_Utils::now_mysql() );
			GFAPI::add_note( $entry_id, 0, '360payments', 'Payment approved by webhook.' );
		} elseif ( in_array( $status, array( 'declined', 'failed' ), true ) ) {
			ElixirN_360p_Utils::update_meta( $entry_id, 'declined_at', ElixirN_360p_Utils::now_mysql() );
			GFAPI::add_note( $entry_id, 0, '360payments', 'Payment not approved by webhook.' );
		} elseif ( 'refunded' === $status ) {
			ElixirN_360p_Utils::update_meta( $entry_id, 'refund_status', 'refunded' );
		} elseif ( 'voided' === $status ) {
			ElixirN_360p_Utils::update_meta( $entry_id, 'void_status', 'voided' );
		}

		return new WP_REST_Response( array( 'ok' => true, 'entry_id' => $entry_id, 'status' => $status ), 200 );
	}

	private function post_action( $endpoint, $payload, $feed, $type ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $this->build_headers( $feed ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			ElixirN_360p_Logger::log( 'critical', "{$type} request failed", array( 'error' => $response->get_error_message() ), true );
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	private function build_headers( $feed ) {
		return array(
			'Content-Type' => 'application/json',
			'Api-Name'     => rgar( $feed['meta'], 'app_name' ),
			'Api-Token'    => rgar( $feed['meta'], 'api_token' ),
			'Api-Version'  => rgar( $feed['meta'], 'api_version' ),
		);
	}

	private function match_entry_id( $payment ) {
		$ref       = rgar( $payment, 'refId' );
		$process   = rgar( $payment, 'processId' );
		$entry_id  = 0;
		$meta_keys = ElixirN_360p_Utils::META_KEYS;

		if ( $ref ) {
			$entry_id = ElixirN_360p_Utils::find_entry_id_by_meta( $meta_keys['ref_id'], $ref );
		}
		if ( ! $entry_id && $process ) {
			$entry_id = ElixirN_360p_Utils::find_entry_id_by_meta( $meta_keys['process_id'], $process );
		}

		return (int) $entry_id;
	}
}
