<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * issue https://github.com/wpcraft-ru/wooms/issues/296
 */

add_action('admin_init', function () {

    // delete_option('wooms_db_version');
    $version = get_option('wooms_db_version', 0);

    $wooms_file = ABSPATH . PLUGINDIR . '/wooms/wooms.php';
    $data = get_plugin_data($wooms_file);

    if ('7.2' != $data['Version']) {
        return;
    }

    if (version_compare('7.2', $version, '<=')) {
        return;
    }

    if($select_cat = get_option('woomss_include_categories_sync')){
        $data = wooms_request($select_cat);
        $pathName = $data['pathName'] . '/' . $data['name'];
        update_option('wooms_set_folders', $pathName);
    }

    update_option('wooms_db_version', '7.2', true);
    update_option('wooms_db_version_check_7_2', 1);

    // dd($data);

});

add_action( 'admin_notices', function(){

    if( ! get_option('wooms_db_version_check_7_2')){
        return;
    }
    $wooms_file = ABSPATH . PLUGINDIR . '/wooms-extra/wooms-extra.php';
    $data = get_plugin_data($wooms_file);

    if(empty($data['Version'])){
        return;
    }

    $version = apply_filters('wooms_xt_version', $data['Version']);

    if (version_compare('7.2', $version, '<=')) {
        delete_option('wooms_db_version_check_7_2');
        return;
    }

    $class = 'notice notice-error';
    $message = sprintf('<p><strong>%s</strong></p>', "Внимание! Плагин WooMS для синхронизации с МойСклад обновлен до версии 7.2");
    $message .= sprintf('<p><strong>%s</strong></p>', "Требуется отключить плагин и включить его только после обновления обоих плагинов до версии 7.2");
    $message .= sprintf('<p>%s <a href="https://github.com/wpcraft-ru/wooms/issues/296" target="_blank">%s</a></strong></p>', "Подробнее", "тут");
 
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message ); 
} );

