<?php

namespace WooMS\ProductsImage;

const HOOK_NAME = 'wooms_product_image_sync';

add_action('plugins_loaded', function () {
    add_action('wooms_product_image_sync', __NAMESPACE__ . '\\walker');
    add_filter('wooms_product_save', __NAMESPACE__ . '\\add_image_task_to_product', 35, 2);
    add_action('woomss_tool_actions_btns', __NAMESPACE__ . '\\render_ui', 15);
    add_action('admin_init', __NAMESPACE__ . '\\add_settings', 50);
    add_action('init', __NAMESPACE__ . '\\add_schedule_hook');
    add_action('wooms_main_walker_finish', __NAMESPACE__ . '\\restart');
});

function walker()
{
    if (!is_enable()) {
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

    $items = get_posts($args);

    if (empty($items)) {
        set_state('finish', date("Y-m-d H:i:s"));

        do_action(
            'wooms_logger',
            __NAMESPACE__,
            sprintf('Главные изображения продуктов загружены')
        );

        return false;
    }

    set_state('last_count', count($items));

    $result = [];

    foreach ($items as $key => $value) {
        if (product_image_download($value->ID)) {
            $result[] = $value->ID;
        }


        delete_post_meta($value->ID, 'wooms_url_for_get_thumbnail');
    }

    set_state('last_result', $result);

    add_schedule_hook(true);
}


function add_settings()
{
    add_settings_section('woomss_section_images', 'Изображения', null, 'mss-settings');

    register_setting('mss-settings', 'woomss_images_sync_enabled');
    add_settings_field(
        $id = 'woomss_images_sync_enabled',
        $title = 'Включить синхронизацию картинок',
        $callback = function ($args) {
            $option = 'woomss_images_sync_enabled';
            $desc = '<small>Если включить опцию, то плагин будет загружать изображения из МойСклад.</small>';
            printf('<input type="checkbox" name="%s" value="1" %s /> %s', $args['key'], $args['value'], $desc);
        },
        $page = 'mss-settings',
        $section = 'woomss_section_images',
        $args = [
            'key' => 'woomss_images_sync_enabled',
            'value' => checked(1, get_option('woomss_images_sync_enabled'), false),
        ],
    );

    register_setting('mss-settings', 'woomss_images_replace_to_sync');
    add_settings_field(
        'woomss_images_replace_to_sync',
        'Замена изображении при синхронизации',
        $callback = function ($args) {
            $option = 'woomss_images_sync_enabled';
            $desc = '<small>Если включить опцию, то плагин будет загружать изображения из МойСклад.</small>';
            printf('<input type="checkbox" name="%s" value="1" %s /> %s', $args['key'], $args['value'], $desc);
        },
        $page = 'mss-settings',
        $section = 'woomss_section_images',
        $args = [
            'key' => 'woomss_images_replace_to_sync',
            'value' => checked(1, get_option('woomss_images_replace_to_sync'), false),
        ],
    );
}


function restart()
{
    set_state('finish', null);
}

/**
 * Init Scheduler
 */
function add_schedule_hook($force = false)
{
    if (!is_enable()) {
        return;
    }

    if (is_wait()) {
        return;
    }

    if (as_next_scheduled_action(HOOK_NAME) && !$force) {
        return;
    }

    if(!$state = get_state()){
        $state = ['started' => date("Y-m-d H:i:s")];
    }

    as_schedule_single_action(time() + 11, HOOK_NAME, $state, 'WooMS');
}

function uploadRemoteImageAndAttach($image_url, $product_id, $filename = 'image.jpg')
{
    if ($check_id = check_exist_image_by_url($image_url)) {
        return $check_id;
    }

    $uploads_dir = wp_upload_dir();
    $post_name = get_post_field('post_name', $product_id);
    $filename_data = wp_check_filetype($filename);
    $filename = $post_name . '.' . $filename_data['ext'];
    $filename = sanitize_file_name($filename);
    $filename = wp_unique_filename($uploads_dir['path'], $filename);

    $header_array = [
        'Authorization' => 'Basic ' . base64_encode(get_option('woomss_login') . ':' . get_option('woomss_pass')),
    ];

    $args = [
        'headers'  => $header_array,
    ];

    $get = wp_remote_get($image_url, $args);

    if (is_wp_error($get)) {
        do_action(
            'wooms_logger_error',
            __NAMESPACE__,
            sprintf('Ошибка загрузки картинки: %s', $get->get_error_message()),
            $get->get_error_code()
        );

        return false;
    }

    if (empty($get['response']['code'])) {
        return false;
    }

    if (403 == $get['response']['code']) {
        $http_response = $get['http_response'];

        if ($http_response->get_status() == 403) {
            $response = $http_response->get_response_object();
            $url_image = $http_response->get_response_object()->url;

            $get2 = wp_remote_get($url_image);
            $mirror = wp_upload_bits($filename, '', wp_remote_retrieve_body($get2));
        }
    } else {

        $mirror = wp_upload_bits($filename, '', wp_remote_retrieve_body($get));
    }

    $type = $filename_data['type'];

    if (!$type)
        return false;


    $attachment = array(
        'post_title' => $filename,
        'post_mime_type' => $type
    );

    $attach_id = wp_insert_attachment($attachment, $mirror['file'], $product_id);

    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attach_data = wp_generate_attachment_metadata($attach_id, $mirror['file']);

    update_post_meta($attach_id, 'wooms_url', $image_url);

    wp_update_attachment_metadata($attach_id, $attach_data);

    do_action(
        'wooms_logger',
        __NAMESPACE__,
        sprintf('Image is downloaded %s (ИД %s, filename: %s)', $product_id, $attach_id, $filename)
    );

    return $attach_id;
}


/**
 * Check exist image by URL
 */
function check_exist_image_by_url($url_api)
{
    $posts = get_posts('post_type=attachment&meta_key=wooms_url&meta_value=' . $url_api);
    if (empty($posts)) {
        return false;
    } else {

        do_action(
            'wooms_logger',
            __NAMESPACE__,
            sprintf('We have such image (%s) already', $posts[0]->ID)
        );

        return $posts[0]->ID;
    }
}

function product_image_download($product_id, $meta_key = 'wooms_url_for_get_thumbnail')
{
    if (!$url = get_post_meta($product_id, $meta_key, true)) {
        return false;
    }

    $images_data = wooms_request($url);


    if (empty($images_data['rows'][0]['filename'])) {
        do_action(
            'wooms_logger_error',
            __NAMESPACE__,
            sprintf('Ошибка получения картинки для продукта %s (url %s)', $product_id, $url),
            $images_data
        );
        return false;
    }

    $image_name = $images_data['rows'][0]['filename'];
    $url = $images_data['rows'][0]['meta']['downloadHref'];

    $check_id = uploadRemoteImageAndAttach($url, $product_id, $image_name);

    if (!empty($check_id)) {

        set_post_thumbnail($product_id, $check_id);

        do_action(
            'wooms_logger',
            __NAMESPACE__,
            sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $product_id, $check_id, $image_name)
        );
        return true;
    } else {
        do_action(
            'wooms_logger_error',
            __NAMESPACE__,
            sprintf('Ошибка назначения картинки для продукта %s (url %s, filename: %s)', $product_id, $url, $image_name)
        );
        return false;
    }
}


/**
 * checking the pause state
 */
function is_wait()
{
    if (get_state('finish')) {
        return true;
    }

    return false;
}

function get_state($key = '')
{
    $option_key = HOOK_NAME . '_state';
    $value = get_option($option_key, []);
    if(!is_array($value)){
        $value = [];
    }
    if (empty($key)) {
        return $value ?? [];
    }

    return $value[$key] ?? null;
}

function set_state($key, $value)
{
    $option_key = HOOK_NAME . '_state';
    $state = get_option($option_key, []);
    if(!is_array($state)){
        $state = [];
    }
    $state[$key] = $value;
    return update_option($option_key, $state);
}

/**
 * Manual start images download
 */
function render_ui()
{
    if (!is_enable()) {
        return;
    }

    $strings = [];

    if (as_next_scheduled_action(HOOK_NAME)) {
        $strings[] = sprintf('Статус: <strong>%s</strong>', 'Выполняется очередями в фоне');
    } else {
        $strings[] = sprintf('Статус: %s', 'в ожидании новых задач');
    }

    $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_product_image_sync&orderby=schedule&order=desc'));

    // if (defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER) {
    //     $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductImage'));
    // } else {
    //     $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
    // }

    echo '<h2>Изображения</h2>';
    echo '<p>Загрузка изображений по 5 штук за раз.</p>';
    foreach ($strings as $string) {
        printf('<p>%s</p>', $string);
    }

    do_action('wooms_product_images_info');


}


/**
 * add image to metafield for download
 */
function add_image_task_to_product($product, $value)
{
    if (!is_enable()) {
        return $product;
    }

    $product_id = $product->get_id();

    //Check image
    if (empty($value['images']['meta']['size'])) {
        return $product;
    } else {
        $url = $value['images']['meta']['href'];
    }
    $product->update_meta_data('test_wooms_url_for_get_thumbnail', $url);
    //check current thumbnail. if isset - break, or add url for next downloading
    if ($id = get_post_thumbnail_id($product_id) && empty(get_option('woomss_images_replace_to_sync'))) {
        return $product;
    } else {
        $product->update_meta_data('wooms_url_for_get_thumbnail', $url);
    }

    return $product;
}

function is_enable()
{
    if (empty(get_option('woomss_images_sync_enabled'))) {
        return false;
    }

    return true;
}

function get_config()
{
    return [
        'walker_hook_name' => 'wooms_product_image_sync',
    ];
}
