<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElixirN_360p_Admin {
	private static $instance;
	private $api;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->api = new ElixirN_360p_Payment_API();
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'meta_box' ), 10, 3 );
		add_filter( 'gform_entries_column_filter', array( $this, 'entries_column' ), 10, 5 );
		add_filter( 'gform_entry_list_columns', array( $this, 'add_entries_column' ), 10, 2 );
		add_filter( 'gform_entry_list_actions', array( $this, 'row_actions' ), 10, 3 );
		add_action( 'admin_post_elixirN_360p_refund', array( $this, 'handle_refund' ) );
		add_action( 'admin_post_elixirN_360p_void', array( $this, 'handle_void' ) );
	}

	public function meta_box( $meta_boxes, $entry, $form ) {
		$meta_boxes['elixirN_360payment'] = array(
			'title'         => __( '360Payment Status', 'elixirn-gf-360payment' ),
			'callback'      => array( $this, 'render_meta_box' ),
			'context'       => 'side',
			'callback_args' => array(
				'entry' => $entry,
				'form'  => $form,
			),
		);
		return $meta_boxes;
	}

	public function render_meta_box( $args ) {
		$entry = rgar( $args, 'entry' );
		if ( empty( $entry['id'] ) ) {
			return;
		}

		echo '<ul>';
		foreach ( ElixirN_360p_Utils::META_KEYS as $k => $key ) {
			printf( '<li><strong>%s:</strong> %s</li>', esc_html( $k ), esc_html( (string) gform_get_meta( $entry['id'], $key ) ) );
		}
		echo '</ul>';
	}

	public function add_entries_column( $columns ) {
		$columns['elixirN_360payment_summary'] = __( 'Payment Summary', 'elixirn-gf-360payment' );
		return $columns;
	}

	public function entries_column( $value, $form_id, $field_id, $entry, $query_string ) {
		if ( 'elixirN_360payment_summary' !== $field_id ) {
			return $value;
		}
		$status  = ElixirN_360p_Utils::get_meta( $entry['id'], 'status', 'not_started' );
		$process = ElixirN_360p_Utils::get_meta( $entry['id'], 'process_id', '-' );
		return sprintf( '%s<br/>%s', esc_html( strtoupper( $status ) ), esc_html( $process ) );
	}

	public function row_actions( $actions, $entry, $form_id ) {
		$status = ElixirN_360p_Utils::get_meta( $entry['id'], 'status', 'not_started' );
		if ( 'approved' === $status && 'refunded' !== ElixirN_360p_Utils::get_meta( $entry['id'], 'refund_status' ) ) {
			$actions['elixirN_360p_refund'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elixirN_360p_refund&entry_id=' . (int) $entry['id'] ), 'elixirN_360p_refund' ) ) . '">Refund</a>';
		}
		if ( in_array( $status, array( 'request_created', 'pending' ), true ) ) {
			$actions['elixirN_360p_void'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elixirN_360p_void&entry_id=' . (int) $entry['id'] ), 'elixirN_360p_void' ) ) . '">Void</a>';
		}
		return $actions;
	}

	public function handle_refund() {
		$this->handle_action( 'elixirN_360p_refund', 'refund' );
	}

	public function handle_void() {
		$this->handle_action( 'elixirN_360p_void', 'void' );
	}

	private function handle_action( $nonce_action, $action_type ) {
		if ( ! current_user_can( 'gravityforms_edit_entries' ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'Unauthorized action.', 'elixirn-gf-360payment' ) );
		}
		$entry_id = absint( rgget( 'entry_id' ) );
		$entry    = GFAPI::get_entry( $entry_id );
		$feed_id  = (int) ElixirN_360p_Utils::get_meta( $entry_id, 'feed_id' );
		$feed     = elixirN_360p_feed_addon()->get_feed( $feed_id );

		$result = 'refund' === $action_type ? $this->api->refund( $entry, $feed, ElixirN_360p_Utils::get_meta( $entry_id, 'amount', 0 ) ) : $this->api->void( $entry, $feed );
		if ( is_wp_error( $result ) ) {
			ElixirN_360p_Logger::log( 'critical', 'admin action failed', array( 'type' => $action_type, 'entry_id' => $entry_id, 'error' => $result->get_error_message() ), true );
		} else {
			ElixirN_360p_Utils::update_meta( $entry_id, 'refund_status', 'refund' === $action_type ? 'requested' : ElixirN_360p_Utils::get_meta( $entry_id, 'refund_status' ) );
			ElixirN_360p_Utils::update_meta( $entry_id, 'void_status', 'void' === $action_type ? 'requested' : ElixirN_360p_Utils::get_meta( $entry_id, 'void_status' ) );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
}
