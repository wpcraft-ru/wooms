<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class WooMS_Import_Product_Images {
	
	/**
	 * WooMS_Import_Product_Images constructor.
	 */
	public function __construct() {
		
		add_action( 'admin_init', array( $this, 'settings_init' ), 100 );
		
		//Use hook do_action('wooms_product_update', $product_id, $value, $data);
		add_action( 'wooms_product_update', array( $this, 'load_data' ), 10, 3 );
		
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( 'init', array( $this, 'add_cron_hook' ) );
		
		add_action( 'wooms_cron_image_downloads', array( $this, 'download_images_from_metafield' ) );
		
		add_action( 'woomss_tool_actions_btns', array( $this, 'ui_for_manual_start' ), 15 );
		add_action( 'woomss_tool_actions_wooms_products_images_manual_start', array( $this, 'ui_action' ) );
		
	}
	
	/**
	 * Method load data
	 *
	 * @param $product_id
	 * @param $value
	 * @param $data
	 */
	public function load_data( $product_id, $value, $data ) {
		
		if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
			return;
		}
		
		//Check image
		if ( empty( $value['image']['meta']['href'] ) ) {
			return;
		} else {
			$url = $value['image']['meta']['href'];
		}
		
		//check current thumbnail. if isset - break, or add url for next downloading
		if ( $id = get_post_thumbnail_id( $product_id ) ) {
			return;
		} else {
			update_post_meta( $product_id, 'wooms_url_for_get_thumbnail', $url );
			update_post_meta( $product_id, 'wooms_image_data', $value['image'] );
		}
	}
	
	/**
	 * Setup cron
	 *
	 * @param $schedules
	 *
	 * @return mixed
	 */
	public function add_schedule( $schedules ) {
		
		$schedules['wooms_cron_worker_images'] = array(
			'interval' => 60,
			'display'  => 'WooMS Cron Load Images 60 sec',
		);
		
		return $schedules;
	}
	
	/**
	 * Init Cron
	 */
	public function add_cron_hook() {
		
		if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
			return;
		}
		
		if ( ! wp_next_scheduled( 'wooms_cron_image_downloads' ) ) {
			wp_schedule_event( time(), 'wooms_cron_worker_images', 'wooms_cron_image_downloads' );
		}
	}
	
	
	/**
	 * Action for UI
	 */
	public function ui_action() {
		
		$data = $this->download_images_from_metafield();
		
		echo '<hr>';
		
		if ( empty( $data ) ) {
			echo '<p>Нет картинок для загрузки</p>';
		} else {
			echo "<p>Загружены миниатюры для продуктов:</p>";
			foreach ( $data as $key => $value ) {
				printf( '<p><a href="%s">ID %s</a></p>', get_edit_post_link( $value ), $value );
			}
			echo "<p>Чтобы повторить загрузку - обновите страницу</p>";
			
		}
	}
	
	
	/**
	 *
	 * @return array|bool|void
	 */
	public function download_images_from_metafield() {
		
		if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
			return;
		}
		
		$list = get_posts( 'post_type=product&meta_key=wooms_url_for_get_thumbnail&meta_compare=EXISTS' );
		
		if ( empty( $list ) ) {
			return false;
		}
		
		$result = [];
		
		foreach ( $list as $key => $value ) {
			$url        = get_post_meta( $value->ID, 'wooms_url_for_get_thumbnail', true );
			$image_data = get_post_meta( $value->ID, 'wooms_image_data', true );
			
			$image_name = $image_data['filename'];
			
			$check_id = $this->download_img( $url, $image_name, $value->ID );
			
			if ( ! empty( $check_id ) ) {
				
				set_post_thumbnail( $value->ID, $check_id );
				
				delete_post_meta( $value->ID, 'wooms_url_for_get_thumbnail' );
				delete_post_meta( $value->ID, 'wooms_image_data' );
				
				$result[] = $value->ID;
			}
			
		}
		
		if ( empty( $result ) ) {
			return false;
		} else {
			return $result;
		}
		
	}
	
	/**
	 * Download Image by URL and retrun att id or false or WP_Error
	 */
	public function download_img( $url_api, $file_name, $post_id ) {
		
		if ( $check_id = $this->check_exist_image_by_url( $url_api ) ) {
			return $check_id;
		}
		
		$header_array = [
			'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
		];
		
		$headers = array();
		foreach ( $header_array as $name => $value ) {
			$headers[] = "{$name}: $value";
		}
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url_api );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		
		$output = curl_exec( $ch );
		$info   = curl_getinfo( $ch ); // Получим информацию об операции
		curl_close( $ch );
		
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}
		
		$tmpfname = wp_tempnam( $file_name );
		$fh       = fopen( $tmpfname, 'w' );
		
		if ( $url_api == $info['url'] ) {//если редиректа нет записываем файл
			fwrite( $fh, $output );
		} else {
			$file = file_get_contents( $info['url'] );//если редирект есть то скачиваем файл по ссылке
			fwrite( $fh, $file );
		}
		
		fclose( $fh );
		
		$filetype = wp_check_filetype( $file_name );
		
		// Array based on $_FILE as seen in PHP file uploads.
		$file_args = array(
			'name'     => $file_name, // ex: wp-header-logo.png
			'type'     => $filetype['type'], //todo do right
			'tmp_name' => $tmpfname,
			'error'    => 0,
			'size'     => filesize( $tmpfname ),
		);
		
		$overrides = array(
			'test_form'   => false,
			'test_size'   => false,
			'test_upload' => false,
		);
		
		$file_data = wp_handle_sideload( $file_args, $overrides );
		
		// If error storing permanently, unlink.
		if ( is_wp_error( $file_data ) ) {
			@unlink( $tmpfname );
			
			return false;
		}
		
		$url     = $file_data['url'];
		$type    = $file_data['type'];
		$file    = $file_data['file'];
		$title   = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
		$content = '';
		
		// Use image exif/iptc data for title and caption defaults if possible.
		if ( $image_meta = @wp_read_image_metadata( $file ) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}
			if ( trim( $image_meta['caption'] ) ) {
				$content = $image_meta['caption'];
			}
		}
		
		if ( isset( $desc ) ) {
			$title = $desc;
		}
		
		// Construct the attachment array.
		$attachment = array(
			'post_mime_type' => $type,
			'guid'           => $url,
			'post_parent'    => $post_id,
			'post_title'     => $title,
			'post_content'   => $content,
		);
		
		// This should never be set as it would then overwrite an existing attachment.
		unset( $attachment['ID'] );
		
		// Save the attachment metadata
		$id = wp_insert_attachment( $attachment, $file, $post_id );
		if ( ! is_wp_error( $id ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		} else {
			return false;
		}
		
		@unlink( $tmpfname );
		
		update_post_meta( $id, 'wooms_url', $url_api );
		
		return $id;
		
	}
	
	/**
	 * Check exist image by URL
	 */
	public function check_exist_image_by_url( $url_api ) {
		
		$posts = get_posts( 'post_type=attachment&meta_key=wooms_url&meta_value=' . $url_api );
		
		if ( empty( $posts ) ) {
			return false;
		} else {
			return $posts[0]->ID;
		}
	}
	
	/**
	 * Manual start images download
	 */
	public function ui_for_manual_start() {
		
		if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
			return;
		}
		
		?>
		<h2>Загрузка картинок</h2><p>Ручная загрузка картинок по 5 штук за раз.</p>
		<a href="<?php echo add_query_arg( 'a', 'wooms_products_images_manual_start', admin_url( 'tools.php?page=moysklad' ) ) ?>" class="button">Выполнить</a>
		<?php
		
	}
	
	/**
	 * Settings UI
	 */
	public function settings_init() {
		
		register_setting( 'mss-settings', 'woomss_images_sync_enabled' );
		add_settings_field( $id = 'woomss_images_sync_enabled', $title = 'Включить синхронизацию картинок', $callback = array(
			$this,
			'setting_images_sync_enabled',
		), $page = 'mss-settings', $section = 'woomss_section_other' );
		
	}
	
	//Display field
	public function setting_images_sync_enabled() {
		
		$option = 'woomss_images_sync_enabled';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
	}
	
}

new WooMS_Import_Product_Images;
