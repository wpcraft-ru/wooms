<?php

namespace WooMS;

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Synchronization the stock of goods from MoySklad
 */
class ProductStocks
{

  /**
   * Используется для создания хука, расписания и как мета ключ очереди задач в мета полях продуктов
   */
  static public $walker_hook_name = 'wooms_assortment_sync';

  /**
   * Save state in DB
   * 
   * @var string
   */
  public static $state_transient_key = 'wooms_assortmen_state';

  /**
   * The init
   */
  public static function init()
  {

    // add_action('init', function () {
    //   if (!isset($_GET['dd'])) {
    //     return;
    //   }

    //   // $p = wc_get_product();
    //   // self::add_schedule_hook();
    //   // self::batch_handler();
    //   // self::add_warehouse_name_to_log_data();

    //   dd(0);
    // });

    add_action('wooms_assortment_sync', [__CLASS__, 'batch_handler']);

    add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 30, 3);
    add_filter('wooms_save_variation', array(__CLASS__, 'update_variation'), 30, 3);

    add_filter('wooms_assortment_sync_filters', array(__CLASS__, 'assortment_add_filter_by_warehouse_id'), 10);
    add_filter('wooms_stock_log_data', array(__CLASS__, 'add_warehouse_name_to_log_data'), 10);

    add_action('wooms_variations_batch_end', [__CLASS__, 'restart_after_batch']);
    add_action('wooms_products_batch_end', [__CLASS__, 'restart_after_batch']);
    add_action('wooms_main_walker_started', [__CLASS__, 'restart']);

    add_action('init', array(__CLASS__, 'add_schedule_hook'));

    add_action('admin_init', array(__CLASS__, 'add_settings'), 30);
    add_action('woomss_tool_actions_btns', array(__CLASS__, 'display_state'), 17);

    add_filter('wooms_stock_type', array(__CLASS__, 'select_type_stock'));

    //need for disable reset state for base plugin
    add_filter('wooms_reset_state_products', function ($reset) {
      return false;
    });
  }


  /**
   * batch_handler
   */
  public static function batch_handler()
  {
    $state = self::get_state();

    $args = array(
      'post_type'              => ['product', 'product_variation'],
      'numberposts'            => 20,
      'meta_query'             => array(
        array(
          'key'     => self::$walker_hook_name,
          'compare' => 'EXISTS',
        ),
      ),
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
      'cache_results'          => false,
    );

    if (!$products = get_posts($args)) {
      self::set_state('finish_timestamp', time());
      return;
    }

    $filters = [];
    foreach ($products as $product) {
      $filters[] = 'id=' . get_post_meta($product->ID, 'wooms_id', true);
    }

    $url = 'https://online.moysklad.ru/api/remap/1.2/entity/assortment';

    $filters = apply_filters('wooms_assortment_sync_filters', $filters);

    $filters = implode(';', $filters);

    $url = add_query_arg('filter', $filters, $url);

    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('Запрос на остатки %s', $url)
    );

    $data_api = wooms_request($url);

    if (empty($data_api['rows'])) {
      return;
    }

    $counts = [
      'all' => 0,
      'save' => 0,
    ];

    foreach ($data_api['rows'] as $row) {

      $counts['all']++;
      if (!$product_id = self::get_product_id_by_uuid($row['id'])) {
        continue;
      }

      if (!$product = wc_get_product($product_id)) {
        continue;
      }

      $product = self::update_stock($product, $row);

      $product->update_meta_data('wooms_assortment_data', self::get_stock_data_log($row, $product_id));

      if ($product) {
        $product->delete_meta_data(self::$walker_hook_name);
        $counts['save']++;
      }

      /**
       * manage stock save
       * 
       * issue https://github.com/wpcraft-ru/wooms/issues/287
       */
      $product = apply_filters('wooms_stock_product_save', $product, $row);

      $product->save();
    }

    self::set_state('count_all', self::get_state('count_all') + $counts['all']);
    self::set_state('count_save', self::get_state('count_save') + $counts['save']);

    self::add_schedule_hook(true);
  }

  /**
   * get_stock_data_log
   * for save log data to product meta
   */
  public static function get_stock_data_log($row = [], $product_id = 0)
  {
    $data = [
      "stock" => $row['stock'],
      "reserve" => $row['reserve'],
      "inTransit" => $row['inTransit'],
      "quantity" => $row['quantity'],
    ];

    $data = apply_filters('wooms_stock_log_data', $data, $product_id, $row);

    $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $data;
  }

  /**
   * update_stock
   */
  public static function update_stock($product, $data_api)
  {
    $product = wc_get_product($product);

    $product_id = $product->get_id();

    /**
     * Поле по которому берем остаток?
     * quantity = это доступные остатки за вычетом резервов
     * stock = это все остатки без уета резерва
     */
    $stock_type = apply_filters('wooms_stock_type', 'quantity');

    $stock = 0;

    if (empty($data_api[$stock_type])) {
      $stock = 0;
    } else {
      $stock = (int) $data_api[$stock_type];
    }

    if (get_option('wooms_stock_empty_backorder')) {
      $product->set_backorders('notify');
    } else {
      $product->set_backorders('no');
    }

    if (empty(get_option('wooms_warehouse_count'))) {
      $product->set_manage_stock('no');
    } else {
      if ($product->is_type('variable')) {

        //для вариативных товаров доступность определяется наличием вариаций
        $product->set_manage_stock('no');
      } else {
        $product->set_manage_stock('yes');
      }
    }

    if ($stock <= 0) {
      if (!$product->is_type('variable')) {
        $product->set_stock_quantity(0);
        $product->set_stock_status('outofstock');
      }
    } else {
      $product->set_stock_quantity($stock);
      $product->set_stock_status('instock');
    }

    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('Остатки для продукта "%s" = %s (ИД %s)', $product->get_name(), $stock, $product_id),
      sprintf('stock %s, quantity %s', $data_api['stock'], $data_api['quantity'])
    );

    return $product;
  }



  /**
   * restart walker after added tast to queue
   */
  public static function restart()
  {
    self::set_state('finish_timestamp', 0);
    self::set_state('count_all', 0);
    self::set_state('count_save', 0);
  }


  public static function restart_after_batch()
  {
    self::set_state('finish_timestamp', 0);
  }




  /**
   * check is wait
   */
  public static function is_wait()
  {
    if (self::get_state('finish_timestamp')) {
      return true;
    }

    return false;
  }


  /**
   * get state data
   */
  public static function get_state($key = '')
  {
    if (!$state = get_transient(self::$state_transient_key)) {
      $state = [];
      set_transient(self::$state_transient_key, $state);
    }

    if (empty($key)) {
      return $state;
    }

    if (empty($state[$key])) {
      return null;
    }

    return $state[$key];
  }




  /**
   * set state data
   */
  public static function set_state($key, $value)
  {

    if (!$state = get_transient(self::$state_transient_key)) {
      $state = [];
    }

    if (is_array($state)) {
      $state[$key] = $value;
    } else {
      $state = [];
      $state[$key] = $value;
    }

    set_transient(self::$state_transient_key, $state);
  }


  /**
   * Add schedule hook
   */
  public static function add_schedule_hook($force = false)
  {
    if (!self::is_enable()) {
      return;
    }

    if (self::is_wait()) {
      return;
    }

    if (as_next_scheduled_action(self::$walker_hook_name) && !$force) {
      return;
    }

    if ($force) {
      self::set_state('force', 1);
    } else {
      self::set_state('force', 0);
    }

    // Adding schedule hook
    as_schedule_single_action(time() + 5, self::$walker_hook_name, self::get_state(), 'WooMS');
  }


  /**
   * Get product variant ID
   * 
   * XXX move to trait
   *
   * @param $uuid
   */
  public static function get_product_id_by_uuid($uuid)
  {
    if (strpos($uuid, 'http') !== false) {
      $uuid = str_replace('https://online.moysklad.ru/api/remap/1.1/entity/product/', '', $uuid);
      $uuid = str_replace('https://online.moysklad.ru/api/remap/1.2/entity/product/', '', $uuid);
    }

    $args = array(
      'post_type'              => ['product', 'product_variation'],
      'numberposts'            => 1,
      'meta_query'             => array(
        array(
          'key'     => 'wooms_id',
          'value' => $uuid,
        ),
      ),
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
      'update_post_meta_cache' => false,
      'cache_results'          => false,
    );

    $posts = get_posts($args);
    if (empty($posts[0]->ID)) {
      return false;
    }

    return $posts[0]->ID;
  }

  /**
   * add_warehouse_name_to_log_data
   */
  public static function add_warehouse_name_to_log_data($data_log = [])
  {
    if (!$warehouse_id = get_option('woomss_warehouse_id')) {
      return $data_log;
    }

    if (!$wh_name = get_transient('wooms_warehouse_name')) {
      $url = sprintf('https://online.moysklad.ru/api/remap/1.2/entity/store/%s', $warehouse_id);
      $data = wooms_request($url);
      if (isset($data["name"])) {
        $wh_name = $data["name"];
        set_transient('wooms_warehouse_name', $wh_name, HOUR_IN_SECONDS);
      }
    }

    $data_log['name_wh'] = $wh_name;

    return $data_log;
  }

  /**
   * add_filter_by_warehouse_id
   */
  public static function assortment_add_filter_by_warehouse_id($filter)
  {
    if (!$warehouse_id = get_option('woomss_warehouse_id')) {
      return $filter;
    }

    $filter[] = 'stockStore=' . sprintf('https://online.moysklad.ru/api/remap/1.2/entity/store/%s', $warehouse_id);

    return $filter;
  }

  /**
   * Select type stock
   */
  public static function select_type_stock($type_stock)
  {
    if (get_option('wooms_stocks_without_reserve')) {
      $type_stock = 'stock';
    }

    return $type_stock;
  }

  /**
   * Update stock for variation
   */
  public static function update_variation($variation, $data_api, $product_id)
  {
    if (empty(get_option('woomss_stock_sync_enabled'))) {

      $variation->set_catalog_visibility('visible');
      $variation->set_stock_status('instock');
      $variation->set_manage_stock('no');
      $variation->set_status('publish');

      return $variation;
    }

    $variation->update_meta_data(self::$walker_hook_name, 1);

    return $variation;
  }

  /**
   * Update product
   */
  public static function update_product($product, $data_api, $data)
  {
    if (empty(get_option('woomss_stock_sync_enabled'))) {
      $product->set_catalog_visibility('visible');
      $product->set_stock_status('instock');
      $product->set_manage_stock('no');
      $product->set_status('publish');

      return $product;
    }

    $product->update_meta_data(self::$walker_hook_name, 1);

    return $product;
  }

  /**
   * Settings UI
   */
  public static function add_settings()
  {

    add_settings_section(
      'woomss_section_warehouses',
      'Склад и остатки',
      $callback = array(__CLASS__, 'display_woomss_section_warehouses'),
      'mss-settings'
    );

    register_setting('mss-settings', 'woomss_stock_sync_enabled');
    add_settings_field(
      $id = 'woomss_stock_sync_enabled',
      $title = 'Включить работу с остатками',
      $callback = array(__CLASS__, 'woomss_stock_sync_enabled_display'),
      $page = 'mss-settings',
      $section = 'woomss_section_warehouses'
    );

    register_setting('mss-settings', 'wooms_stocks_without_reserve');
    add_settings_field(
      $id = 'wooms_stocks_without_reserve',
      $title = 'Остатки без резерва',
      $callback = array(__CLASS__, 'display_field_wooms_stocks_without_reserve'),
      $page = 'mss-settings',
      $section = 'woomss_section_warehouses'
    );

    register_setting('mss-settings', 'wooms_warehouse_count');
    add_settings_field(
      $id = 'wooms_warehouse_count',
      $title = 'Управление запасами на уровне товаров',
      $callback = array(__CLASS__, 'display_wooms_warehouse_count'),
      $page = 'mss-settings',
      $section = 'woomss_section_warehouses'
    );

    register_setting('mss-settings', 'wooms_stock_empty_backorder');
    add_settings_field(
      $id = 'wooms_stock_empty_backorder',
      $title = 'Разрешать предазказ при 0 остатке',
      $callback = array(__CLASS__, 'display_wooms_stock_empty_backorder'),
      $page = 'mss-settings',
      $section = 'woomss_section_warehouses'
    );

    self::add_setting_warehouse_id();
  }


  /**
   * Display field: select warehouse
   */
  public static function add_setting_warehouse_id()
  {
    $option = 'woomss_warehouse_id';
    register_setting('mss-settings', $option);
    add_settings_field(
      $id = $option,
      $title = 'Учитывать остатки по складу',
      $callback = function ($args) {

        $url  = 'https://online.moysklad.ru/api/remap/1.2/entity/store';
        $data = wooms_request($url);
        if (empty($data['rows'])) {
          echo 'Система не смогла получить список складов из МойСклад';
          return;
        }
        $selected_wh = $args['value']; ?>

      <select class="wooms_select_warehouse" name="woomss_warehouse_id">
        <option value="">По всем складам</option>
        <?php
        foreach ($data['rows'] as $row) :
          printf('<option value="%s" %s>%s</option>', $row['id'], selected($row['id'], $selected_wh, false), $row['name']);
        endforeach;
        ?>
      </select>
    <?php
      },
      $page = 'mss-settings',
      $section = 'woomss_section_warehouses',
      $args = [
        'key' => $option,
        'value' => get_option($option),
      ]
    );
  }

  /**
   *
   */
  public static function display_woomss_section_warehouses()
  {
    ?>
    <p>Данные опции позволяют настроить обмен данным по остаткам между складом и сайтом.</p>
    <ol>
      <li>Функционал обязательно нужно проверять на тестовом сайте. Он еще проходит обкатку. В случае проблем
        сообщайте в техподдержку
      </li>
      <li>После изменения этих опций, следует обязательно <a href="<?php echo admin_url('admin.php?page=moysklad') ?>" target="_blank">запускать обмен данными
          вручную</a>, чтобы статусы наличия продуктов обновились
      </li>
      <li>Перед включением опций, нужно настроить магазина на работу с <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=products&section=inventory') ?>" target="_blank">Запасами</a></li>
    </ol>
  <?php
  }


  /**
   * Display field
   */
  public static function woomss_stock_sync_enabled_display()
  {
    $option = 'woomss_stock_sync_enabled';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));
    echo '<p>При включении опции товары будут помечаться как в наличии или отсутствующие в зависимиости от числа остатков на складе</p>';
  }

  /**
   * Display field
   */
  public static function display_wooms_stock_empty_backorder()
  {
    $option = 'wooms_stock_empty_backorder';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));
    echo '<p><small>Если включить опцию то система будет разрешать предзаказ при 0 остатках</small></p>';
  }

  /**
   * display_field_wooms_stocks_without_reserve
   */
  public static function display_field_wooms_stocks_without_reserve()
  {
    $option = 'wooms_stocks_without_reserve';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));
    echo '<p><small>Если включить опцию то на сайте будут учитываться остатки без учета резерва</small></p>';
  }

  /**
   * Display field
   */
  public static function display_wooms_warehouse_count()
  {
    $option = 'wooms_warehouse_count';
    printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));
    printf('<p><strong>Перед включением опции, убедитесь что верно настроено управление запасами в WooCommerce (на <a href="%s" target="_blank">странице настроек</a>).</strong></p>', admin_url('admin.php?page=wc-settings&tab=products&section=inventory'));
    echo "<p><small>Если включена, то будет показан остаток в количестве единиц продукта на складе. Если снять галочку - только наличие.</small></p>";
  }

  /**
   * is_enable
   */
  public static function is_enable()
  {
    if (get_option('woomss_stock_sync_enabled')) {
      return true;
    }

    return false;
  }

  /**
   * display_state
   */
  public static function display_state()
  {

    if (!self::is_enable()) {
      return;
    }

    $strings = [];

    if (as_next_scheduled_action(self::$walker_hook_name)) {
      $strings[] = sprintf('<strong>Статус:</strong> %s', 'Выполняется очередями в фоне');
    } else {
      $strings[] = sprintf('<strong>Статус:</strong> %s', 'в ожидании задач');
    }

    if ($end_timestamp = self::get_state('finish_timestamp')) {
      $end_timestamp = date('Y-m-d H:i:s', $end_timestamp);
      $strings[] = sprintf('Последняя успешная синхронизация (отметка времени UTC): %s', $end_timestamp);
    }

    $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_assortment_sync&orderby=schedule&order=desc'));


    if (defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER) {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductStocks'));
    } else {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
    }

    $strings[] = sprintf('Количество обработанных записей: %s', empty(self::get_state('count_all')) ? 0 : self::get_state('count_all'));
    $strings[] = sprintf('Количество сохраненных записей: %s', empty(self::get_state('count_save')) ? 0 : self::get_state('count_save'));
  ?>
    <h2>Остатки</h2>
    <div class="wrap">
      <div id="message" class="notice notice-warning">
        <?php
        foreach ($strings as $string) {
          printf('<p>%s</p>', $string);
        }
        ?>
      </div>
    </div>

<?php

  }
}

ProductStocks::init();
