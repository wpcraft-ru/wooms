<?php

namespace WooMS\Products;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class Images {

    /**
     * WooMS_Import_Product_Images constructor.
     */
    public static function init() {

      /**
       * Обновление данных о продукте
       */
      add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 35, 3);

      add_action( 'admin_init', array( __CLASS__, 'settings_init' ), 50 );

      add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
      add_action( 'init', array( __CLASS__, 'add_cron_hook' ) );

      add_action( 'wooms_cron_image_downloads', array( __CLASS__, 'download_images_from_metafield' ) );

      add_action( 'woomss_tool_actions_btns', array( __CLASS__, 'ui_for_manual_start' ), 15 );
      add_action( 'woomss_tool_actions_wooms_products_images_manual_start', array( __CLASS__, 'ui_action' ) );

    }

  /**
   * update_product
   */
  public static function update_product($product, $value, $data){

    if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
            return $product;
        }
    $product_id = $product->get_id();


      //Check image
      if ( empty( $value['image']['meta']['href'] ) ) {
          return $product;
      } else {
          $url = $value['image']['meta']['href'];
      }

      //check current thumbnail. if isset - break, or add url for next downloading
      if ( $id = get_post_thumbnail_id( $product_id ) && empty( get_option( 'woomss_images_replace_to_sync' ) ) ) {
          return $product;
      } else {
      $product->update_meta_data('wooms_url_for_get_thumbnail', $url);
      $product->update_meta_data('wooms_image_data', $value['image']);
      }

    return $product;
  }


    /**
     * Setup cron
     *
     * @param $schedules
     *
     * @return mixed
     */
    public static function add_schedule( $schedules ) {

        $schedules['wooms_cron_worker_images'] = array(
            'interval' => 60,
            'display'  => 'WooMS Cron Load Images 60 sec',
        );

        return $schedules;
    }

    /**
     * Init Cron
     */
    public static function add_cron_hook() {

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
    public static function ui_action() {

        $data = self::download_images_from_metafield();

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
     * Download images from meta
     *
     * @TODO - переписать на методы CRUD WooCommerce
     *
     * @return array|bool|void
     */
    public static function download_images_from_metafield() {

        if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
            return;
        }

        $args = array(
            'post_type'              => 'product',
            'meta_query'             => array(
                array(
                    'key'     => 'wooms_url_for_get_thumbnail',
                    'compare' => 'EXISTS',
                ),
            ),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'cache_results'          => false,
        );

        $list = get_posts( $args );

        if ( empty( $list ) ) {
            return false;
        }

        $result = [];

        foreach ( $list as $key => $value ) {
            $url        = get_post_meta( $value->ID, 'wooms_url_for_get_thumbnail', true );
            $image_data = get_post_meta( $value->ID, 'wooms_image_data', true );

            $image_name = $image_data['filename'];

            $check_id = self::download_img( $url, $image_name, $value->ID );

            if ( ! empty( $check_id ) ) {

                set_post_thumbnail( $value->ID, $check_id );
                delete_post_meta( $value->ID, 'wooms_url_for_get_thumbnail' );
                delete_post_meta( $value->ID, 'wooms_image_data' );
                $result[] = $value->ID;

                do_action('wooms_logger', __CLASS__,
                  sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $value->ID, $check_id, $image_name )
                );
            } else {
              do_action('wooms_logger_error', __CLASS__,
                sprintf('Ошибка назначения картинки для продукта %s (url %s, filename: %s)', $value->ID, $url, $image_name )
              );
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
    public static function download_img( $url_api, $file_name, $post_id ) {

        if ( $check_id = self::check_exist_image_by_url( $url_api ) ) {
            return $check_id;
        }

        if( ! function_exists('wp_read_image_metadata')){
          require_once ABSPATH . '/wp-admin/includes/image.php';
        }

        $header_array = [
            'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) ),
        ];

        $headers = array();
        foreach ( $header_array as $name => $value ) {
            $headers[] = "{$name}: $value";
        }

        $ch = \curl_init();
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

        $file_name = sanitize_file_name($file_name);
        $tmpfname = wp_tempnam( $file_name );
        $fh       = fopen( $tmpfname, 'w' );

        if ( $url_api == $info['url'] ) {//если редиректа нет записываем файл
            fwrite( $fh, $output );
        } else {
            $file = file_get_contents( $info['url'] );//если редирект есть то скачиваем файл по ссылке

            if( ! $file ){
              do_action('wooms_logger_error',
                __CLASS__,
                'Загрузка картинки - не удалось закачать файл',
                sprintf('Данные %s', PHP_EOL . print_r($info['url'], true))
              );
              return false;
            }

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
            do_action('wooms_logger_error',
              __CLASS__,
              'Загрузка картинки - не удалось получить файл',
              sprintf('Данные %s', PHP_EOL . print_r($file_data, true))
            );

            return false;
        }

        if(empty($file_data['url'])){
          do_action('wooms_logger_error',
            __CLASS__,
            'Загрузка картинки - не удалось получить URL',
            sprintf('Данные %s', PHP_EOL . print_r($file_data, true))
          );
          @unlink( $tmpfname );

          return false;
        }

        $url     = $file_data['url'];
        $type    = $file_data['type'];
        $file    = $file_data['file'];
        $title   = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
        $content = '';

        // Use image exif/iptc data for title and caption defaults if possible.
        if ( $image_meta = \wp_read_image_metadata( $file ) ) {
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
    public static function check_exist_image_by_url( $url_api ) {

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
    public static function ui_for_manual_start() {

        if ( empty( get_option( 'woomss_images_sync_enabled' ) ) ) {
            return;
        }

        ?>
        <h2>Изображения</h2>
        <p>Ручная загрузка изображений по 5 штук за раз.</p>
        <?php
    printf( '<a href="%s" class="button button-primary">Выполнить</a>', add_query_arg( 'a', 'wooms_products_images_manual_start', admin_url( 'admin.php?page=moysklad' ) ) );

    }

    /**
     * Settings UI
     */
    public static function settings_init() {
        add_settings_section( 'woomss_section_images', 'Изображения', null, 'mss-settings' );

        register_setting( 'mss-settings', 'woomss_images_sync_enabled' );
        add_settings_field(
      $id = 'woomss_images_sync_enabled',
      $title = 'Включить синхронизацию картинок',
      $callback = array(__CLASS__, 'setting_images_sync_enabled'),
      $page = 'mss-settings',
      $section = 'woomss_section_images'
    );

        register_setting( 'mss-settings', 'woomss_images_replace_to_sync' );
        add_settings_field(
      'woomss_images_replace_to_sync',
      'Замена изображении при синхронизации',
      array(__CLASS__, 'setting_images_replace_to_sync'),
      $page = 'mss-settings',
      $section = 'woomss_section_images'
    );
    }

  /**
   * setting_images_replace_to_sync
   */
    public static function setting_images_replace_to_sync() {

        $option = 'woomss_images_replace_to_sync';
        $desc = '<small>Если включить опцию, то плагин будет обновлять изображения, если они изменились в МойСклад.</small><p><small><strong>Внимание!</strong> Для корректной перезаписи изображений, необходимо провести повторную синхронизацию товаров. Если синхронизация товаров происходит по крону, то дождаться окончания очередной сессии синхронизации товаров</small></p>';
        printf( '<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked( 1, get_option( $option ), false ), $desc );
    }
  /**
   * setting_images_sync_enabled
   */
    public static function setting_images_sync_enabled() {
        $option = 'woomss_images_sync_enabled';
        $desc = '<small>Если включить опцию, то плагин будет загружать изображения из МойСклад.</small>';
        printf( '<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked( 1, get_option( $option ), false ), $desc );
    }
}

Images::init();
