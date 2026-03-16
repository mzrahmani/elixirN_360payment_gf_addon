<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElixirN_360p_Shortcode {
	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'elixirN_360payment_iframe', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'entry_id' => 0,
			),
			$atts,
			'elixirN_360payment_iframe'
		);

		$entry_id = absint( $atts['entry_id'] );
		if ( ! $entry_id ) {
			return '';
		}

		$status = ElixirN_360p_Utils::get_meta( $entry_id, 'status', 'not_started' );
		if ( in_array( $status, array( 'approved', 'refunded', 'voided' ), true ) ) {
			return '';
		}

		$url = ElixirN_360p_Utils::get_meta( $entry_id, 'url' );
		if ( ! $url ) {
			return '';
		}

		$process_id = ElixirN_360p_Utils::get_meta( $entry_id, 'process_id' );
		$output     = sprintf(
			'<div class="elixirN-360payment-wrapper"><p>%s %s</p><iframe src="%s" width="100%%" height="600" loading="lazy"></iframe></div>',
			esc_html__( 'Please complete payment below.', 'elixirn-gf-360payment' ),
			esc_html( $process_id ? sprintf( __( '(Reference: %s)', 'elixirn-gf-360payment' ), $process_id ) : '' ),
			esc_url( $url )
		);

		return apply_filters( 'elixirN_360payment_confirmation_shortcode_output', $output, $entry_id, $status );
	}
}
