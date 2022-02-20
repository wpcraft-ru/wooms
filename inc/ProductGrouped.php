<?php

namespace WooMS;

/**
 * Products Bundle Managment
 * 
 * issue https://github.com/wpcraft-ru/wooms/issues/313
 */
class ProductGrouped extends AbstractWalker
{

  /**
   * Save state in DB
   * 
   * @var string
   */
  public static $state_transient_key = 'wooms_bundle_walker_state';

  /**
   * Hookd and key for ActionSheduler
   *
   * @var string
   */
  public static $walker_hook_name = 'wooms_bundle_walker_batch';

  /**
   * The init
   */
  public static function init()
  {

    // add_action('init', function () {
    //   if (!isset($_GET['dd'])) {
    //     return;
    //   }

    //   self::set_state('timestamp_start', 0);
    //   self::batch_handler();

    //   dd(0);
    // });

    add_action('wooms_bundle_walker_batch', [__CLASS__, 'batch_handler']);

    add_filter('wooms_product_save', array(__CLASS__, 'update_product'), 40, 2);

    add_action('wooms_main_walker_finish', array(__CLASS__, 'reset_after_main_walker_finish'));
    add_action('wooms_wakler_variations_finish', array(__CLASS__, 'reset_after_main_walker_finish'));

    add_action('init', array(__CLASS__, 'add_schedule_hook'));
    add_action('wooms_tools_sections', array(__CLASS__, 'display_state'));
    add_action('admin_init', array(__CLASS__, 'add_settings'), 150);
  }

  /**
   * Walker for data variant product from MoySklad
   */
  public static function batch_handler($args = [])
  {
    $state = self::get_state();

    if (!empty($state['lock'])) {
      // return; // блокировка состояни гонки
    }


    self::set_state('lock', 1);

    //reset state if new session
    if (empty($state['timestamp_start'])) {

      self::set_state('timestamp_start', time());
      self::set_state('count', 0);

      $query_arg_default = [
        'offset' => 0,
        'limit'  => 30
      ];

      self::set_state('query_arg', $query_arg_default);
    }


    $query_arg = self::get_state('query_arg');

    $url = 'https://online.moysklad.ru/api/remap/1.2/entity/bundle';

    $url = add_query_arg($query_arg, $url);

    $filters = [];

    $filters = apply_filters('wooms_url_get_bundle_filter', $filters);

    $url = add_query_arg('filter', implode(';', $filters), $url);

    $url = apply_filters('wooms_url_get_bundles', $url);

    try {

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Комплекты. Отправлен запрос: %s', $url),
        $state
      );

      $data = wooms_request($url);

      //Check for errors and send message to UI
      if (isset($data['errors'][0]["error"])) {
        throw new \Exception($data['errors'][0]["error"]);
      }

      //If no rows, that send 'end' and stop walker
      if (isset($data['rows']) && empty($data['rows'])) {


        self::set_state('lock', 0);
        self::walker_finish();
        return true;
      }

      $i = 0;
      foreach ($data['rows'] as $key => $item) {

        $i++;

        do_action('wooms_product_data_item', $item);
      }

      //update count
      self::set_state('count', self::get_state('count') + $i);

      //update offset 
      $query_arg['offset'] = $query_arg['offset'] + count($data['rows']);

      self::set_state('query_arg', $query_arg);

      self::set_state('lock', 0);

      self::add_schedule_hook(true);

      do_action('wooms_variations_batch_end');

      return true;
    } catch (\Exception $e) {
      self::set_state('lock', 0);
      do_action(
        'wooms_logger_error',
        __CLASS__,
        $e->getMessage()
      );
      return false;
    }
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
      $strings[] = sprintf('<strong>Статус:</strong> %s', 'Ждет задач в очереди');
    }

    if ($end_timestamp = self::get_state('end_timestamp')) {
      $strings[] = sprintf('Последняя успешная синхронизация (отметка времени UTC): %s', $end_timestamp);
    }

    $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_bundle_walker_batch&orderby=schedule&order=desc'));

    if (defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER) {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductGrouped'));
    } else {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
    }

?>
    <h2>Сгруппированные продукты (бандлы, комплекты)</h2>

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

  /**
   * Resetting state after completing the main walker
   * And restart schedules for sync variations
   */
  public static function reset_after_main_walker_finish()
  {
    self::set_state('count', 0);
    self::set_state('lock', 0);
    self::set_state('end_timestamp', 0);
    self::set_state('timestamp_start', 0);
    self::add_schedule_hook();
  }

  /**
   * Finish walker
   */
  public static function walker_finish()
  {
    self::set_state('end_timestamp', date("Y-m-d H:i:s"));

    do_action('wooms_recount_terms');

    as_unschedule_all_actions(self::$walker_hook_name);

    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('Обработчик групповых продуктов завершил работу: %s', date("Y-m-d H:i:s"))
    );

    return true;
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
   * Проверяем стоит ли обработчик на паузе?
   */
  public static function is_wait()
  {
    if (self::get_state('end_timestamp')) {
      return true;
    }

    return false;
  }

  /**
   * Update product
   */
  public static function update_product($product, $value)
  {
    if (!self::is_enable()) {
      return $product;
    }

    if (empty($value["components"]["meta"]["href"])) {
      return $product;
    }

    $url_api = $value["components"]["meta"]["href"];

    $data_components = wooms_request($url_api);

    // dd($data_components);

    if (empty($data_components["rows"])) {
      return $product;
    }

    if('grouped' != $product->get_type()){
      $product = new \WC_Product_Grouped($product);
    }

    $product->delete_meta_data('wooms_assortment_sync');

    if (!$product->is_type('grouped')) {
      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Продукт выбран как групповой %s (%s)', $product->get_id(), $product->get_name())
      );
    }

    $subproducts_ids = array();
    foreach ($data_components["rows"] as $row_component) {
      $product_uuid = str_replace('https://online.moysklad.ru/api/remap/1.2/entity/product/', '', $row_component["assortment"]["meta"]["href"]);
      $subproduct_id = self::get_product_id_by_uuid($product_uuid);

      if (empty($subproduct_id)) {
        continue;
      }

      $subproducts_ids[] = $subproduct_id;
    }

    $product->set_children($subproducts_ids);

    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('Сгруппированный продукт %s (%s). Выбор компонентов...', $product->get_id(), $product->get_name()),
      $subproducts_ids
    );

    return $product;
  }

  /**
   * get_product_id_by_uuid
   */
  public static function get_product_id_by_uuid($uuid = '')
  {
    if (empty($uuid)) {
      return false;
    }

    $posts = get_posts('post_type=product&meta_key=wooms_id&meta_value=' . $uuid);
    if (empty($posts[0]->ID)) {
      return false;
    } else {
      return $posts[0]->ID;
    }
  }

  /**
   * settings_ui
   */
  public static function add_settings()
  {
    $option_id = 'wooms_products_bundle_enable';
    register_setting('mss-settings', $option_id);
    add_settings_field(
      $id = $option_id,
      $title = 'Включить работу с групповыми продуктами (комплекты МойСклад)',
      $callback = function ($args) {
        printf('<input type="checkbox" name="%s" value="1" %s />', $args['name'], checked(1, $args['value'], false));
        printf('<p><strong>%s</strong></p>', 'Тестовый режим. Не включайте эту функцию на реальном сайте, пока не проверите ее на тестовой копии сайта.');
      },
      $page = 'mss-settings',
      $section = 'woomss_section_other',
      $args = [
        'name' => $option_id,
        'value' => @get_option($option_id),
      ]
    );
  }

  /**
   * is_enable
   */
  public static function is_enable()
  {
    if (get_option('wooms_products_bundle_enable')) {
      return true;
    }

    return false;
  }
}

ProductGrouped::init();
