<?php

namespace WooMS\ProductsScheduler;

use function WooMS\get_config as get_config;
// use function WooMS\Products\get_state as get_state;
use const WooMS\OPTIONS_PAGE as OPTIONS_PAGE;
use const WooMS\OPTION_KEY as OPTION_KEY;


add_action('wooms_monitoring', __NAMESPACE__ . '\\check_schedule');

add_action('init', function () {
  if (!as_next_scheduled_action('wooms_monitoring')) {
    as_schedule_recurring_action(time(), 60, 'wooms_monitoring', [], 'WooMS');
  }
});

add_action('admin_init', __NAMESPACE__ . '\\add_settings', 20);


function check_schedule(){

  if(empty(get_config('walker_cron_enabled'))){
    return false;
  }

  if ( as_next_scheduled_action( \WooMS\Products\HOOK_NAME ) ) {
    return false;
  }

  $end_timestamp = \WooMS\Products\get_state('end_timestamp') ?? 0;

  $timer = 60 * 60 * intval(get_config('walker_cron_timer') ?? 12);
  $time_has_passed = time() - $end_timestamp;

  if ($time_has_passed < $timer) {
    return false;
  }

  return as_schedule_single_action(time(), \WooMS\Products\HOOK_NAME, [], 'WooMS');
}


function add_settings()
{
  $section = 'wooms_section_cron';
  add_settings_section($section, 'Расписание синхронизации', __return_empty_string(), OPTIONS_PAGE);

  add_settings_field(
    $id = 'walker_cron_enabled',
    $title = 'Включить синхронизацию продуктов по расписанию',
    $callback = function($args){
      printf('<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked(1, $args['value'], false));
    },
    $page = 'mss-settings',
    $section,
    $args = [
      'key' => OPTION_KEY . '[walker_cron_enabled]',
      'value' => get_config('walker_cron_enabled'),
    ]
  );

  add_settings_field(
    $id = 'walker_cron_timer',
    $title = 'Перерыв синхронизации в часах',
    $callback = function($args){
      printf('<input type="number" name="%s" value="%s"  />', $args['key'], $args['value']);
    },
    $page = 'mss-settings',
    $section,
    $args = [
      'key' => OPTION_KEY . '[walker_cron_timer]',
      'value' => get_config('walker_cron_timer') ?? 12,
    ]
  );
}
