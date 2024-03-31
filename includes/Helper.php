<?php

namespace WooMS;

class Helper {

	public static function get_session_id() {
		return \WooMS\Products\get_session_id();
	}

	/**
	 * we have to use this method, instead lagacy
	 */
	public static function get_product_id_by_uuid( $uuid ) {

		if ( strpos( $uuid, 'http' ) !== false ) {
			$uuid = str_replace( 'https://online.moysklad.ru/api/remap/1.1/entity/product/', '', $uuid );
			$uuid = str_replace( 'https://online.moysklad.ru/api/remap/1.2/entity/product/', '', $uuid );
			$uuid = str_replace( 'https://api.moysklad.ru/api/remap/1.2/entity/product/', '', $uuid );
		}

		$posts = get_posts( [
			'post_type' => [ 'product', 'product_variation' ],
			'meta_key' => 'wooms_id_' . $uuid,
		] );

		if ( isset( $posts[0]->ID ) ) {
			return $posts[0]->ID;
		}

		$posts = get_posts( [
			'post_type' => [ 'product', 'product_variation' ],
			'meta_key' => 'wooms_id',
			'meta_value' => $uuid
		] );

		if ( empty( $posts[0]->ID ) ) {
			return false;
		} else {
			return $posts[0]->ID;
		}
	}

	public static function log( string $message, $class = 'WooMS', array $data = [] ) {
		if ( ! Logger::is_enable() ) {
			return;
		}

		if ( ! empty( $data ) ) {

			if ( is_array( $data ) ) {
				$data = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			} else {
				$data = wc_print_r( $data, true );
			}

			$data = wp_trim_words( $data, 300 );
			$message .= PHP_EOL . '-' . PHP_EOL . $data;
		}

		$source = str_replace( '\\', '-', $class );

		$logger = wc_get_logger();
		$context = array( 'source' => $source );
		$logger->info( $message, $context );
	}

	public static function log_error( string $message, $class = 'WooMS', array $data = [] ) {

		if ( ! Logger::is_enable() ) {
			return;
		}

		if ( ! empty( $data ) ) {

			if ( is_array( $data ) ) {
				$data = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			} else {
				$data = wc_print_r( $data, true );
			}

			$data = wp_trim_words( $data, 300 );
			$message .= PHP_EOL . '-' . PHP_EOL . $data;
		}

		$source = str_replace( '\\', '-', $class );

		$logger = wc_get_logger();
		$context = array( 'source' => $source );
		$logger->error( $message, $context );

	}


	public static function get_timestamp_last_job_by_hook($hook){
		$store = \ActionScheduler::store();
		$data = $store->query_actions([
		  'hook' => $hook,
		  'orderby' => 'date',
		  'order' => 'DESC',
		]);

		if(empty($data[0])){
		  return null;
		}

		$date = $store->get_date($data[0]);
		$date->setTimezone(new \DateTimeZone(wp_timezone_string()));
		return $date->format('Y-m-d H:i:s');
	  }
}
