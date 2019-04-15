<?php

namespace WooMS\Products;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Product Import Walker
 * do_action('wooms_product_import_row', $value, $key, $data);
 */
class Walker {

  /**
   * The Init
   */
  public static function init()
  {

    //Main Walker
    add_action( 'wooms_cron_walker', array( __CLASS__, 'walker_cron_starter' ) );
    add_action( 'init', array( __CLASS__, 'cron_init' ) );
    add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );

    //Product data
    add_action( 'wooms_product_import_row', array( __CLASS__, 'load_product' ), 10, 3 );
    add_filter( 'wooms_product_save', array( __CLASS__, 'update_product' ), 9, 3 );

    //UI and actions manually
    add_action( 'woomss_tool_actions_btns', array( __CLASS__, 'display_wrapper' ) );
    add_action( 'woomss_tool_actions_wooms_products_start_import', array( __CLASS__, 'start_manually' ) );
    add_action( 'woomss_tool_actions_wooms_products_stop_import', array( __CLASS__, 'stop_manually' ) );

    //Notices
    add_action( 'wooms_products_display_state', array( __CLASS__, 'display_state' ) );

    //Other
    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes_post_type' ) );

    /**
     * Удаляем крон задание при деактивации
     */
    add_action( 'wooms_deactivate', function(){
        wp_unschedule_event( wp_next_scheduled( 'wooms_cron_walker' ), 'wooms_cron_walker' );
    });
  }

  /**
   * Load data and set product type simple
   *
   * @param $value
   * @param $key
   * @param $data
   */
  public static function load_product( $value, $key, $data )
  {
    if ( ! empty( $value['archived'] ) ) {
      return;
    }

    /**
     * Если отключена опция связи по UUID и пустой артикул, то пропуск записи
     */
    if ( empty(get_option('wooms_use_uuid')) and empty($value['article']) ) {
      return;
    }

    //попытка получить id по артикулу
    if ( ! empty( $value['article'] ) ) {
      $product_id = wc_get_product_id_by_sku( $value['article'] );
    } else {
      $product_id = null;
    }

    //попытка получить id по uuid
    if ( empty(intval( $product_id )) ) {
      $product_id = self::get_product_id_by_uuid( $value['id']);
    }

    //создаем продукт, если не нашли
    if ( empty(intval( $product_id )) ) {
      $product_id = self::add_product( $value );
    }

    if ( empty(intval( $product_id )) ) {
      do_action('wooms_logger_error', __CLASS__,
        'Ошибка определения и добавления ИД продукта', $value
      );
      return;
    }

    $product = wc_get_product($product_id);

    /**
     * rename vars
     */
    $data_api = $value;
    /**
     * Хук позволяет работать с методами WC_Product
     * Сохраняет в БД все изменения за 1 раз
     * Снижает нагрузку на БД
     */
    $product = apply_filters('wooms_product_save', $product, $data_api, $product_id);
    $product_id = $product->save();

    do_action('wooms_logger', __CLASS__,
      sprintf('Продукт: %s (%s) сохранен', $product->get_title(), $product_id)
    );

  }

  /**
   * Update product from source data
   */
  public static function update_product( $product, $data_api, $data = 'deprecated' )
  {

    $data_of_source = $data_api;
    $product_id = $product->get_id();

    //save data of source
    $product->update_meta_data( 'wooms_data_of_source', print_r( $data_of_source, true ) );

    //Set session id for product
    if ( $session_id = get_option( 'wooms_session_id' ) ) {
      $product->update_meta_data( 'wooms_session_id', $session_id );
    }

    $product->update_meta_data( 'wooms_updated_timestamp', date( "Y-m-d H:i:s" ) );

    $product->update_meta_data( 'wooms_id', $data_of_source['id'] );

    $product->update_meta_data( 'wooms_updated', $data_of_source['updated'] );

    //update title
    if ( isset( $data_of_source['name'] ) and $data_of_source['name'] != $product->get_title() ) {
      if ( ! empty( get_option( 'wooms_replace_title' ) ) ) {
        $product->set_name( $data_of_source['name'] );
      }
    }

    $product_description = isset($data_of_source['description']) ? $data_of_source['description'] : '';
    //update description
    if ( apply_filters( 'wooms_added_description', true, $product_description) ) {

      if ( $product_description && ! empty( get_option( 'wooms_replace_description' ) ) ) {

        $product->set_description( $product_description );

      } else {

        if ( empty( $product->get_description() ) ) {

          $product->set_description( $product_description);
        }
      }
    }

    //Price Retail 'salePrices'
    if ( isset( $data_of_source['salePrices'][0]['value'] ) ) {
      $price_source = floatval( $data_of_source['salePrices'][0]['value'] );
      $price        = apply_filters( 'wooms_product_price', $price_source, $data_api, $product_id );

      $price = $price / 100;

      $product->set_price( $price );
      $product->set_regular_price( $price );

    }

    $product->set_catalog_visibility('visible');
    $product->set_stock_status( 'instock' );
    $product->set_manage_stock( 'no' );

    $product->set_status( 'publish' );

    return $product;

  }

  /**
   * Add metaboxes
   */
  public static function add_meta_boxes_post_type()
  {
    add_meta_box( 'wooms_product', 'МойСклад', array( __CLASS__, 'display_metabox_for_product' ), 'product', 'side', 'low' );
  }

  /**
   * Meta box in product
   */
  public static function display_metabox_for_product() {
    $post = get_post();
    $box_data = '';
    $data_id   = get_post_meta( $post->ID, 'wooms_id', true );
    $data_meta = get_post_meta( $post->ID, 'wooms_meta', true );
    $data_updated = get_post_meta( $post->ID, 'wooms_updated', true );
    if ( $data_id ) {
      printf( '<div>ID товара в МойСклад: <div><strong>%s</strong></div></div>', $data_id );
    } else {
      echo '<p>Товар еще не синхронизирован с МойСклад.</p> <p>Ссылка на товар отсутствует</p>';
    }

    if ( $data_meta ) {
      printf( '<p><a href="%s" target="_blank">Посмотреть товар в МойСклад</a></p>', $data_meta['uuidHref'] );
    }

    if ( $data_updated ) {
      printf( '<div>Дата последнего обновления товара в МойСклад: <strong>%s</strong></div>', $data_updated );
    }

    do_action('wooms_display_product_metabox', $post->ID);
    // echo $box_data;
  }

  /**
   * get_product_id_by_uuid
   */
  public static function get_product_id_by_uuid( $uuid ) {

    $posts = get_posts( 'post_type=product&meta_key=wooms_id&meta_value=' . $uuid );

    if ( empty( $posts[0]->ID ) ) {
      return false;
    } else {
      return $posts[0]->ID;
    }
  }

  /**
   * Add product from source data
   *
   * @param $data_source
   *
   */
  public static function add_product( $data_source ) {

    if ( ! apply_filters( 'wooms_add_product', true, $data_source ) ) {
      return false;
    }

    $product = new \WC_Product_Simple();

    $product->set_name(wp_filter_post_kses( $data_source['name'] ));

    $product_id = $product->save();

    if ( empty( $product_id ) ) {
      return false;
    }

    $product->update_meta_data( 'wooms_id', $data_source['id'] );

    $product->update_meta_data( 'wooms_meta', $data_source['meta'] );

    $product->update_meta_data( 'wooms_updated', $data_source['updated'] );

    if ( isset( $data_source['article'] ) ) {
      $product->set_sku( $data_source['article'] );
    }

    $product_id = $product->save();

    return $product_id;
  }

  /**
   * Cron shedule setup for 1 minute interval
   */
  public static function add_schedule( $schedules ) {
    $schedules['wooms_cron_walker_shedule'] = array(
      'interval' => apply_filters('wooms_cron_interval', 60),
      'display'  => 'WooMS Cron Walker 60 sec',
    );

    return $schedules;
  }

  /**
   * Cron task restart
   */
  public static function cron_init() {
    if ( ! wp_next_scheduled( 'wooms_cron_walker' ) ) {
      wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_walker' );
    }
  }

  /**
   * Starter walker by cron if option enabled
   */
  public static function walker_cron_starter() {

    if ( self::can_cron_start() ) {
      self::walker();
    }
  }

  /**
   * Can cron start? true or false
   */
  public static function can_cron_start() {

    //Если стоит отметка о ручном запуске - крон может стартовать
    if ( ! empty( get_transient( 'wooms_manual_sync' ) ) ) {
      return true;
    }

    //Если работа по расписанию отключена - не запускаем
    if ( empty( get_option( 'woomss_walker_cron_enabled' ) ) ) {
      return false;
    }
    if ( $end_stamp = get_transient( 'wooms_end_timestamp' ) ) {

      $interval_hours = get_option( 'woomss_walker_cron_timer' );
      $interval_hours = (int) $interval_hours;
      if ( empty( $interval_hours ) ) {
        return false;
      }
      $now       = new \DateTime();
      $end_stamp = new \DateTime( $end_stamp );
      $end_stamp = $now->diff( $end_stamp );
      $diff_hours = $end_stamp->format( '%h' );
      if ( $diff_hours > $interval_hours ) {
        return true;
      } else {
        return false;
      }
    } else {
      return true;
    }
  }

  /**
   * Walker for data from MoySklad
   */
  public static function walker() {
    //Check stop tag and break the walker
    if ( self::check_stop_manual() ) {
      return;
    }

    $count = apply_filters( 'wooms_iteration_size', 20 );
    if ( ! $offset = get_transient( 'wooms_offset' ) ) {
      $offset = 0;
      self::walker_started();
      set_transient( 'wooms_offset', $offset );

    }

    $ms_api_args = array(
      'offset' => $offset,
      'limit'  => $count,
      'scope'  => 'product',
    );

    $url = 'https://online.moysklad.ru/api/remap/1.1/entity/assortment';

    $url = add_query_arg( $ms_api_args, $url );

    $url = apply_filters('wooms_url_get_products', $url);

    try {

      delete_transient( 'wooms_end_timestamp' );
      set_transient( 'wooms_start_timestamp', time() );
      $data = wooms_request( $url );

      do_action('wooms_logger', __CLASS__, sprintf('Отправлен запрос %s', $url) );

      //Check for errors and send message to UI
      if ( isset( $data['errors'] ) ) {
        $error_code = $data['errors'][0]["code"];
        if ( $error_code == 1056 ) {
          $msg = sprintf( 'Ошибка проверки имени и пароля. Код %s, исправьте в <a href="%s">настройках</a>', $error_code, admin_url( 'admin.php?page=mss-settings' ) );
          throw new \Exception( $msg );
        } else {
          throw new \Exception( $error_code . ': ' . $data['errors'][0]["error"] );
        }
      }

      //If no rows, that send 'end' and stop walker
      if ( isset($data['rows']) && empty( $data['rows'] ) ) {
        self::walker_finish();
        return true;
      }

      if(empty( $data['rows'] )){

        do_action('wooms_logger_error', __CLASS__,
          'Ошибка - пустой data row',
          print_r($data, true)
        );

        return false;
      }

      do_action( 'wooms_walker_start_iteration', $data );

      /**
       * @TODO: deprecated. remove after tests
       */
      do_action( 'wooms_walker_start' );

      $i = 0;
      foreach ( $data['rows'] as $key => $value ) {
        $i++;

        if( apply_filters('wooms_skip_product_import', false, $value) ){
          continue;
        }

        /**
         * в выдаче могут быть не только товары, но и вариации и мб что-то еще
         * птм нужна проверка что это точно продукт
         */
        if('product' != $value["meta"]["type"]){
          continue;
        }

        do_action( 'wooms_product_data_item', $value );

        /**
         * deprecated - for remove
         */
        do_action( 'wooms_product_import_row', $value, $key, $data );
      }

      if ( $count_saved = get_transient( 'wooms_count_stat' ) ) {
        set_transient( 'wooms_count_stat', $i + $count_saved );
      } else {
        set_transient( 'wooms_count_stat', $i );
      }

      set_transient( 'wooms_offset', $offset + $i );

      return;
    } catch ( \Exception $e ) {
      delete_transient( 'wooms_start_timestamp' );
      set_transient( 'wooms_end_timestamp', date( "Y-m-d H:i:s" ), $timer );

      do_action('wooms_logger_error', __CLASS__, 'Главный обработчик завершился с ошибкой' . $e->getMessage() );
    }
  }

  /**
   * walker_started
   */
  public static function walker_started() {
    $timestamp = date( "YmdHis" );
    update_option( 'wooms_session_id', $timestamp, 'no' ); //set id session sync
    delete_transient( 'wooms_count_stat' );

    do_action('wooms_main_walker_started');
    do_action('wooms_logger', __CLASS__, 'Старт основного волкера: ' . $timestamp );
  }

  /**
   * Finish walker
   */
  public static function walker_finish() {
    delete_transient( 'wooms_start_timestamp' );
    delete_transient( 'wooms_offset' );
    delete_transient( 'wooms_manual_sync' );

    //Отключаем обработчик или ставим на паузу
    if ( empty( get_option( 'woomss_walker_cron_enabled' ) ) ) {
      $timer = 0;
    } else {
      $timer = 60 * 60 * intval( get_option( 'woomss_walker_cron_timer', 24 ) );
    }

    set_transient( 'wooms_end_timestamp', date( "Y-m-d H:i:s" ), $timer );

    do_action( 'wooms_walker_finish' );

    do_action( 'wooms_main_walker_finish' );

    do_action('wooms_recount_terms');
    return true;
  }


  /**
   * Check and stop walker manual
   */
  public static function check_stop_manual() {
    if ( get_transient( 'wooms_walker_stop' ) ) {
      delete_transient( 'wooms_start_timestamp' );
      delete_transient( 'wooms_offset' );
      delete_transient( 'wooms_walker_stop' );

      return true;
    }

    return false;
  }

  /**
   * Start manually actions
   */
  public static function start_manually() {
    delete_transient( 'wooms_start_timestamp' );
    delete_transient( 'wooms_offset' );
    delete_transient( 'wooms_end_timestamp' );
    delete_transient( 'wooms_walker_stop' );
    set_transient( 'wooms_manual_sync', 1 );
    self::walker();
    wp_redirect( admin_url( 'admin.php?page=moysklad' ) );
  }

  /**
   * Stop manually actions
   */
  public static function stop_manually() {
    set_transient( 'wooms_walker_stop', 1, 60 * 60 );
    delete_transient( 'wooms_start_timestamp' );
    delete_transient( 'wooms_offset' );
    // delete_transient( 'wooms_end_timestamp' );
    delete_transient( 'wooms_manual_sync' );

    self::walker_finish();
    wp_redirect( admin_url( 'admin.php?page=moysklad' ) );
  }

  /**
   * Description
   */
  public static function display_state(){
    $state = '<strong>Выполняется пакетная обработка данных в фоне очередями раз в минуту.</strong>';
    $start_timestamp = get_transient( 'wooms_start_timestamp' );


    $end_timestamp = get_transient('wooms_end_timestamp');

    $diff_sec = false;
    if( ! empty($start_timestamp) ){
      $diff_sec    = time() - $start_timestamp;
      $time_string = date( 'Y-m-d H:i:s', $start_timestamp );

    }

    $session = get_option( 'wooms_session_id' );
    if(empty($session)){
      $session = 'отсутствует';
    }

    $end_timestamp = get_transient( 'wooms_end_timestamp' );
    if(empty($end_timestamp)){
      $end_timestamp = 'отметка времени будет проставлена после завершения текущей сессии синхронизации';
    } else {
      $state = 'Синхронизация завершена и находится в ожидании старта';
    }

    ?>
    <div class="wrap">
      <div id="message" class="notice notice-warning">
        <p>Статус: <?= $state ?></p>
        <p>Сессия (номер/дата): <?= $session ?></p>
        <p>Последняя успешная синхронизация (отметка времени): <?= $end_timestamp ?></p>
        <p>Количество обработанных записей: <?php echo get_transient( 'wooms_count_stat' ); ?></p>
        <?php do_action('wooms_products_state_before'); ?>
        <?php if( ! empty($time_string) ): ?>
          <p>Отметка времени о последней итерации: <?php echo $time_string ?></p>
          <p>Секунд прошло: <?= $diff_sec ?>.<br/> Следующая серия данных должна отправиться примерно через
          минуту. Можно обновить страницу для проверки результатов работы.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  /**
   * User interface for manually actions
   */
  public static function display_wrapper() {
    echo '<h2>Продукты (Товары)</h2>';

    do_action('wooms_products_display_state');

    if ( empty( get_transient( 'wooms_start_timestamp' ) ) ) {
      echo "<p>Нажмите на кнопку ниже, чтобы запустить синхронизацию данных о продуктах вручную</p>";
      printf( '<a href="%s" class="button button-primary">Выполнить</a>', add_query_arg( 'a', 'wooms_products_start_import', admin_url( 'admin.php?page=moysklad' ) ) );
    } else {
      printf( '<a href="%s" class="button button-secondary">Остановить</a>', add_query_arg( 'a', 'wooms_products_stop_import', admin_url( 'admin.php?page=moysklad' ) ) );
    }
  }
}

Walker::init();
