<?php

namespace WooMS\Products;

defined('ABSPATH') || exit;

const HOOK_NAME = 'wooms_products_walker';

add_action(HOOK_NAME, __NAMESPACE__ . '\\walker');

add_action('admin_init', __NAMESPACE__ . '\\add_settings', 50);
// add_action('init', __NAMESPACE__ . '\\add_schedule_hook');

add_action('wooms_product_data_item', __NAMESPACE__ . '\\load_product');
add_filter('wooms_product_save', __NAMESPACE__ . '\\update_product', 9, 3);

add_action('wooms_tools_sections', __NAMESPACE__ . '\\render_ui', 9);
add_action('woomss_tool_actions_wooms_products_start_import', __NAMESPACE__ . '\\start_manually');
add_action('woomss_tool_actions_wooms_products_stop_import', __NAMESPACE__ . '\\stop_manually');

add_action('add_meta_boxes', function () {
  add_meta_box('wooms_product', 'МойСклад', __NAMESPACE__ . '\\display_metabox_for_product', 'product', 'side', 'low');
});


/**
 * main walker for start sync
 */
function walker($args = [])
{
  if (empty($args)) {
    walker_started();
    $state = get_state();
  } else {
    $state = $args;
  }

  $query_arg = $state['query_arg'];

  /**
   * issue https://github.com/wpcraft-ru/wooms/issues/296
   */
  $url = 'https://online.moysklad.ru/api/remap/1.2/entity/product';

  $url = add_query_arg($query_arg, $url);

  $url = apply_filters('wooms_url_get_products', $url);

  $filters = [
    // 'pathName~=Диваны',
  ];

  $filters = apply_filters('wooms_url_get_products_filters', $filters);

  if (!empty($filters)) {
    $filters = implode(';', $filters);
    $url = add_query_arg('filter', $filters, $url);
  }

  try {

    $data = wooms_request($url);

    if (isset($data['errors'])) {
      throw new \Exception(print_r($data['errors'], true));
    }

    do_action('wooms_logger', __NAMESPACE__, sprintf('Отправлен запрос %s', $url));

    //If no rows, that send 'end' and stop walker
    if (isset($data['rows']) && empty($data['rows'])) {
      walker_finish();
      return ['result' => 'finish'];
    }

    do_action('wooms_walker_start_iteration', $data);

    process_rows($data['rows']);

    $state['count'] += count($data['rows']);
    $state['query_arg']['offset'] += count($data['rows']);

    set_state($state);
    // error_log(print_r($state, true));

    as_schedule_single_action(time(), HOOK_NAME, [$state], 'WooMS');

    do_action('wooms_products_batch_end');
    return ['result' => 'restart'];
  } catch (\Exception $e) {

    /**
     * need to protect the site
     * from incorrectly hidden products
     */
    set_state('session_id', null);

    do_action('wooms_logger_error', __NAMESPACE__, 'Главный обработчик завершился с ошибкой... ' . $e->getMessage());
    return ['result' => 'error'];
  }
}

function process_rows($rows = [])
{
  if (empty($rows)) {
    return false;
  }

  foreach ($rows as $key => $value) {

    if (apply_filters('wooms_skip_product_import', false, $value)) {
      continue;
    }

    /**
     * в выдаче могут быть не только товары, но и вариации и мб что-то еще
     * птм нужна проверка что это точно продукт
     */
    if ('variant' == $value["meta"]["type"]) {
      continue;
    }


    do_action('wooms_product_data_item', $value);

  }

  return true;

}

/**
 * Start manually actions
 */
function start_manually()
{
  set_state([]);

  do_action('wooms_products_sync_manual_start');

  as_schedule_single_action(time(), HOOK_NAME, [], 'WooMS');

  wp_redirect(admin_url('admin.php?page=moysklad'));
}

/**
 * Stop manually actions
 */
function stop_manually()
{

  as_unschedule_all_actions(HOOK_NAME);
  set_state('stop_manual', 1);
  set_state('timestamp', 0);
  walker_finish();

  /**
   * issue https://github.com/wpcraft-ru/wooms/issues/305
   */
  set_state('session_id', null);

  wp_redirect(admin_url('admin.php?page=moysklad'));
  exit;
}

function get_session_id()
{
  return get_state('session_id');
}

/**
 * Update product from source data
 */
function update_product($product, $data_api, $data = 'deprecated')
{
  $data_of_source = $data_api;

  //Set session id for product
  if ($session_id = get_state('session_id')) {
    $product->update_meta_data('wooms_session_id', $session_id);
  }

  $product->update_meta_data('wooms_updated_timestamp', date("Y-m-d H:i:s"));

  $product->update_meta_data('wooms_id', $data_api['id']);

  $product->update_meta_data('wooms_updated_from_api', $data_api['updated']);

  //update title
  if (isset($data_api['name']) and $data_api['name'] != $product->get_title()) {
    if (!empty(get_option('wooms_replace_title'))) {
      $product->set_name($data_api['name']);
    }
  }

  $product_description = isset($data_of_source['description']) ? $data_of_source['description'] : '';
  //update description
  if (apply_filters('wooms_added_description', true, $product_description)) {

    if ($product_description && !empty(get_option('wooms_replace_description'))) {

      if (get_option('wooms_short_description')) {
        $product->set_short_description($product_description);
      } else {
        $product->set_description($product_description);
      }
    } else {

      if (empty($product->get_description())) {

        if (get_option('wooms_short_description')) {
          $product->set_short_description($product_description);
        } else {
          $product->set_description($product_description);
        }
      }
    }
  }

  //Price Retail 'salePrices'
  if (isset($data_of_source['salePrices'][0]['value'])) {

    $price_source = floatval($data_of_source['salePrices'][0]['value']);

    $price = floatval($price_source) / 100;

    $product->set_price($price);
    $product->set_regular_price($price);
  }

  // issue https://github.com/wpcraft-ru/wooms/issues/302
  $product->set_catalog_visibility('visible');

  if (apply_filters('wooms_reset_state_products', true)) {
    $product->set_stock_status('instock');
    $product->set_manage_stock('no');
    $product->set_status('publish');
  }

  return $product;
}


function add_product($data_source)
{

  if (!apply_filters('wooms_add_product', true, $data_source)) {
    return false;
  }

  $product = new \WC_Product_Simple();

  $product->set_name(wp_filter_post_kses($data_source['name']));

  $product_id = $product->save();

  if (empty($product_id)) {
    return false;
  }

  $product->update_meta_data('wooms_id', $data_source['id']);

  $product->update_meta_data('wooms_meta', $data_source['meta']);

  $product->update_meta_data('wooms_updated', $data_source['updated']);

  if (isset($data_source['article'])) {
    $product->set_sku($data_source['article']);
  }

  $product_id = $product->save();

  return $product_id;
}


/**
 * Load data and set product type simple
 */
function load_product($value)
{

  /**
   * Определение способов связи
   */
  $product_id = 0;

  $product_id = get_product_id_by_uuid($value['id']);

  if (!empty($value['archived'])) {
    if($product_id){
      wp_delete_post($product_id);
    }
    return false;
  }

  if (empty($product_id) && !empty($value['article'])) {
    $product_id = wc_get_product_id_by_sku($value['article']);
  }

  //попытка получить id по другим параметрам
  if (empty($product_id)) {
    $product_id = apply_filters('wooms_get_product_id', $product_id, $value);
  }

  //создаем продукт, если не нашли
  if (empty(intval($product_id))) {
    $product_id = add_product($value);
  }


  if (empty(intval($product_id))) {
    do_action(
      'wooms_logger_error',
      __NAMESPACE__,
      'Ошибка определения и добавления ИД продукта',
      $value
    );
    return false;
  }

  $product = wc_get_product($product_id);

  /**
   * rename vars
   */
  $data_api = $value;

  $product->update_meta_data('wooms_id_' . $data_api['id'], 1);

  /**
   * Хук позволяет работать с методами WC_Product
   * Сохраняет в БД все изменения за 1 раз
   * Снижает нагрузку на БД
   */
  $product = apply_filters('wooms_product_save', $product, $data_api, $product_id);

  //save data of source
  if (apply_filters('wooms_logger_enable', false)) {
    $product->update_meta_data('wooms_data_api', json_encode($data_api, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  } else {
    $product->delete_meta_data('wooms_data_api');
  }

  $product_id = $product->save();

  do_action(
    'wooms_logger',
    __NAMESPACE__,
    sprintf('Продукт: %s (%s) сохранен', $product->get_title(), $product_id)
  );

  return $product_id;
}


function walker_finish()
{
  set_state('finish', date("Y-m-d H:i:s"));

  set_state('end_timestamp', time());

  do_action('wooms_main_walker_finish');

  do_action('wooms_recount_terms');

  as_unschedule_all_actions(HOOK_NAME);

  do_action(
    'wooms_logger',
    __NAMESPACE__,
    sprintf('Основной обработчик продуктов завершил работу: %s', date("Y-m-d H:i:s"))
  );

  return true;
}


function add_settings()
{

  $option_name = 'wooms_batch_size';
  register_setting('mss-settings', $option_name);
  add_settings_field(
    $id = $option_name,
    $title = 'Количество элементов в пачке',
    $callback = function ($args) {

      printf(
        '<input type="number" name="%s" value="%s"  />',
        $args['key'],
        $args['value']
      );
      printf(
        '<p>%s</p>',
        'Опция позволяет ускорять обмен данными, но может приводить к перегрузке сервера и связанным с этим ошибкам'
      );
      printf(
        '<p>%s</p>',
        'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/295">https://github.com/wpcraft-ru/wooms/issues/295</a>'
      );
    },
    $page = 'mss-settings',
    $section = 'woomss_section_other',
    $args = [
      'key' => $option_name,
      'value' => get_option($option_name, 20),
    ]
  );

  $option_name = 'wooms_short_description';
  register_setting('mss-settings', $option_name);
  add_settings_field(
    $id = $option_name,
    $title = 'Использовать краткое описание продуктов вместо полного',
    $callback = function ($args) {

      printf(
        '<input type="checkbox" name="%s" value="1" %s />',
        $args['key'],
        checked(1, $args['value'], false)
      );

      printf(
        '<p>%s</p>',
        'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/347">https://github.com/wpcraft-ru/wooms/issues/347</a>'
      );
    },
    $page = 'mss-settings',
    $section = 'woomss_section_other',
    $args = [
      'key' => $option_name,
      'value' => get_option($option_name, 20),
    ]
  );

  do_action('wooms_add_settings');
}


/**
 * get_product_id_by_uuid
 */
function get_product_id_by_uuid($uuid)
{
  $args = [
    'post_type' => ['product'],
    'post_status' => 'any',
    'meta_key' => 'wooms_id',
    'meta_value' => $uuid,
  ];
  $posts = get_posts($args);

  if (empty($posts[0]->ID)) {
    return false;
  } else {
    return $posts[0]->ID;
  }
}

function walker_started()
{

  $batch_size = get_option('wooms_batch_size', 20);
  $query_arg_default = [
    'offset' => 0,
    'limit' => apply_filters('wooms_iteration_size', $batch_size),
  ];
  // set_state('query_arg', );

  $now = date("YmdHis");
  $state = [
    'count' => 0,
    'session_id' => $now,
    'timestamp' => $now,
    'query_arg' => $query_arg_default,
    'end_timestamp' => 0,
  ];

  set_state($state);

  do_action('wooms_main_walker_started');

  do_action('wooms_logger', __NAMESPACE__, 'Старт основного волкера: ' . $now);
}

function add_schedule_hook($force = false)
{
  $need_new_job = false;
  if (is_wait()) {
    return;
  }

  if (as_next_scheduled_action(HOOK_NAME) && !$force) {
    return;
  }

  as_schedule_single_action(time(), HOOK_NAME, [get_state()], 'WooMS');
}

/**
 * Проверяем стоит ли обработчик на паузе?
 */
function is_wait()
{
  if (get_state('finish')) {
    return true;
  }

  //if no password
  if (empty(get_option('woomss_pass'))) {
    return true;
  }

  return false;
}

function render_ui()
{
  printf('<h2>%s</h2>', 'Каталог');

  $strings = [];
  if (as_next_scheduled_action(HOOK_NAME)) {
    printf('<a href="%s" class="button button-secondary">Остановить синхронизацию</a>', add_query_arg('a', 'wooms_products_stop_import', admin_url('admin.php?page=moysklad')));
    $strings[] = sprintf('Статус: <strong>%s</strong>', 'синхронизация в процессе');
    $strings[] = do_shortcode('[wooms_loader_icon]');

  } else {
    $strings[] = sprintf('Статус: %s', 'Завершено');
    $strings[] = sprintf('Время последнего завершения: %s', wooms_get_timestamp_last_job_by_hook(HOOK_NAME));
    printf(
      '<a href="%s" class="button button-primary">Запустить синхронизацию продуктов вручную</a>',
      add_query_arg('a', 'wooms_products_start_import', admin_url('admin.php?page=moysklad'))
    );

  }
  $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_products_walker&orderby=schedule&order=desc'));

  foreach ($strings as $string) {
    printf('<p>%s</p>', $string);
  }

  do_action('wooms_products_display_state');
}


function get_state($key = '')
{
  $option_key = HOOK_NAME . '_state';
  $value = get_option($option_key, []);
  if (!is_array($value)) {
    $value = [];
  }
  if (empty($key)) {
    return $value ?? [];
  }

  return $value[$key] ?? null;
}

function set_state($key, $value = null)
{
  $option_key = HOOK_NAME . '_state';
  if (empty($value) && is_array($key)) {
    return update_option($option_key, $key);
  }

  $state = get_option($option_key, []);
  if (!is_array($state)) {
    $state = [];
  }
  $state[$key] = $value;
  return update_option($option_key, $state);
}

/**
 * Meta box in product
 */
function display_metabox_for_product()
{
  $post = get_post();
  $box_data = '';
  $data_id = get_post_meta($post->ID, 'wooms_id', true);
  $data_meta = get_post_meta($post->ID, 'wooms_meta', true);
  $data_updated = get_post_meta($post->ID, 'wooms_updated', true);
  if ($data_id) {
    printf('<div>ID товара в МойСклад: <div><strong>%s</strong></div></div>', $data_id);
  } else {
    echo '<p>Товар еще не синхронизирован с МойСклад.</p> <p>Ссылка на товар отсутствует</p>';
  }

  if ($data_meta) {
    printf('<p><a href="%s" target="_blank">Посмотреть товар в МойСклад</a></p>', $data_meta['uuidHref']);
  }

  if ($data_updated) {
    printf('<div>Дата последнего обновления товара в МойСклад: <strong>%s</strong></div>', $data_updated);
  }

  do_action('wooms_display_product_metabox', $post->ID);
}
