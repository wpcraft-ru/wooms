<?php

namespace WooMS\ProductsHider;

const HOOK_NAME = 'wooms_schedule_clear_old_products_walker';

add_action('init', function () {
  add_action(HOOK_NAME, __NAMESPACE__ . '\\walker');
  add_action('wooms_main_walker_finish', __NAMESPACE__ . '\\add_task_for_hide');
  add_action('wooms_main_walker_started', __NAMESPACE__ . '\\remove_task_for_hide');
  add_action('admin_init', __NAMESPACE__ . '\\add_settings');
  add_action('wooms_tools_sections', __NAMESPACE__ . '\\display_state', 22);
});

function walker($state = [])
{
  if (is_disable()) {
    return;
  }

  do_action(
    'wooms_logger',
    __NAMESPACE__,
    sprintf('Проверка очереди скрытия продуктов: %s', date("Y-m-d H:i:s"))
  );

  $products = get_products_old_session();

  if (empty($products)) {

    do_action('wooms_recount_terms');
    do_action(
      'wooms_logger',
      __NAMESPACE__,
      sprintf('Финишь скрытия продуктов: %s', date("Y-m-d H:i:s"))
    );
    return;
  }

  $ids = [];
  foreach ($products as $product_id) {
    $product = wc_get_product($product_id);
    $ids[] = $product_id;

    if ($product->get_type() == 'variable') {
      // $product->set_manage_stock('yes');
    }

    $product->set_catalog_visibility('hidden');
    $product->save();

    do_action(
      'wooms_logger',
      __NAMESPACE__,
      sprintf('Скрытие продукта: %s', $product_id)
    );
  }

  $state['ids'] = $ids;
  if (empty($state['count'])) {
    $state['count'] = count($products);
  } else {
    $state['count'] += count($products);
  }

  as_schedule_single_action(time(), HOOK_NAME, [$state], 'WooMS');

  do_action('wooms_hide_old_product', $products);

  return true;
}

function get_session()
{
  return \WooMS\Products\get_state('session_id');
}

function add_task_for_hide()
{
  if (is_disable()) {
    return;
  }

  as_schedule_single_action(time(), HOOK_NAME, [], 'WooMS');
}

function remove_task_for_hide()
{
  as_unschedule_all_actions(HOOK_NAME);
}

/**
 * Obtaining products with specific attributes
 *
 * @param int $offset
 */
function get_products_old_session()
{
  $session = get_session();
  if (empty($session)) {
    return false;
  }

  $args = array(
    'post_type' => ['product', 'product_variation'],
    'numberposts' => 30,
    'fields' => 'ids',
    'tax_query' => array(
      array(
        'taxonomy' => 'product_visibility',
        'terms' => array('exclude-from-catalog', 'exclude-from-search'),
        'field' => 'name',
        'operator' => 'NOT IN',
      ),
    ),
    'meta_query' => array(
      array(
        'key' => 'wooms_session_id',
        'value' => $session,
        'compare' => '!=',
      ),
      array(
        'key' => 'wooms_id',
        'compare' => 'EXISTS',
      ),
    ),

  );

  return get_posts($args);
}

/**
 * проверяем надо ли скрывать продукты
 */
function is_disable()
{
  if (get_option('wooms_product_hiding_disable')) {
    return true;
  }

  return false;
}


function display_state()
{

  $strings = [];

  if (is_disable()) {
    $strings[] = 'Обработчик скрытия продуктов отключен в настройках';
  }

  if (as_next_scheduled_action(HOOK_NAME)) {
    $strings[] = 'Статус: <strong>Продукты скрываются в фоне очередями</strong>';

  } else {
    $strings[] = sprintf('Очередь последний раз завершилась: %s', wooms_get_timestamp_last_job_by_hook(HOOK_NAME));
  }

  $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_schedule_clear_old_products_walker&orderby=schedule&order=desc'));

  echo '<h2>Скрытие продуктов</h2>';
  foreach ($strings as $string) {
    printf('<p>%s</p>', $string);
  }
}

function add_settings()
{
  $option_name = 'wooms_product_hiding_disable';
  register_setting('mss-settings', $option_name);
  add_settings_field(
    $id = $option_name,
    $title = 'Отключить скрытие продуктов',
    $callback = function ($args) {
      printf('<input type="checkbox" name="%s" value="1" %s />', $args['name'], checked(1, $args['value'], false));
      printf('<p><small>%s</small></p>', 'Если включить опцию, то обработчик скрытия продуктов из каталога будет отключен. Иногда это бывает полезно.');
    },
    $page = 'mss-settings',
    $section = 'woomss_section_other',
    $args = [
      'name' => $option_name,
      'value' => get_option($option_name),
    ]
  );
}
