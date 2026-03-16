<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElixirN_360p_Logger {
	const SOURCE = 'elixirN_360payment';

	public static function log( $level, $message, $context = array(), $force = false ) {
		$addon  = function_exists( 'elixirN_360p_feed_addon' ) ? elixirN_360p_feed_addon() : null;
		$debug  = $addon ? (bool) $addon->get_plugin_setting( 'debug_enabled' ) : false;
		$levels = array( 'critical', 'error' );
		if ( ! $debug && ! $force && ! in_array( strtolower( $level ), $levels, true ) ) {
			return;
		}

		$context = self::mask( apply_filters( 'elixirN_360payment_log_context', $context ) );
		$line    = wp_json_encode(
			array(
				'level'   => $level,
				'message' => $message,
				'context' => $context,
			)
		);

		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'log_debug' ) ) {
			GFCommon::log_debug( self::SOURCE . ' ' . $line );
			return;
		}

		error_log( self::SOURCE . ' ' . $line );
	}

	public static function mask( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$masked_keys = array( 'api-token', 'api_token', 'token', 'authorization' );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::mask( $value );
				continue;
			}
			if ( in_array( strtolower( (string) $key ), $masked_keys, true ) ) {
				$data[ $key ] = '***masked***';
			}
		}
		return $data;
	}
}
