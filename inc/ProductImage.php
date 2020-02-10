<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Import Product Image
 */
class ProductImage
{

    use MSImages;

    /**
     * WooMS_Import_Product_Images constructor.
     */
    public static function init()
    {

        // add_action('init', function () {
        //     if (!isset($_GET['ee'])) {
        //         return;
        //     }

        //     self::download_images_from_metafield();


        //     die('end');
        // });

        /**
         * Обновление данных о продукте
         */
        add_filter('wooms_product_save', [__CLASS__, 'update_product'], 35, 3);

        add_action('admin_init', [__CLASS__, 'settings_init'], 50);

        add_action('init', [__CLASS__, 'add_schedule_hook']);

        add_action('main_image_download_schedule', [__CLASS__, 'download_images_from_metafield']);

        add_action('woomss_tool_actions_btns', [__CLASS__, 'ui_for_manual_start'], 15);
        add_action('woomss_tool_actions_wooms_products_images_manual_start', [__CLASS__, 'ui_action']);
    }

    /**
     * update_product
     */
    public static function update_product($product, $value, $data)
    {
        if (empty(get_option('woomss_images_sync_enabled'))) {
            return $product;
        }
        $product_id = $product->get_id();

        //Check image
        if (empty($value['image']['meta']['href'])) {
            return $product;
        } else {
            $url = $value['image']['meta']['href'];
        }

        //check current thumbnail. if isset - break, or add url for next downloading
        if ($id = get_post_thumbnail_id($product_id) && empty(get_option('woomss_images_replace_to_sync'))) {
            return $product;
        } else {
            $product->update_meta_data('wooms_url_for_get_thumbnail', $url);
            $product->update_meta_data('wooms_image_data', $value['image']);
        }

        return $product;
    }

    /**
     * Init Scheduler
     */
    public static function add_schedule_hook()
    {
        if (empty(get_option('woomss_images_sync_enabled'))) {
            return;
        }

        if (self::check_schedule_needed()) {
            // Adding schedule hook
            as_schedule_recurring_action(
                time(),
                60,
                'main_image_download_schedule',
                [],
                'ProductImage'
            );
        }

        if (get_transient('main_images_downloaded') && empty(get_transient('wooms_start_timestamp'))) {

            as_unschedule_all_actions('main_image_download_schedule', [], 'ProductImage');
            set_transient('main_images_downloaded', false);
        }
    }

    /**
     * Checking if schedule can be created or not
     *
     * @return void
     */
    public static function check_schedule_needed()
    {

        // If next schedule is not this one and the sync is active and the all gallery images is downloaded
        if (as_next_scheduled_action('main_image_download_schedule', [], 'ProductImage')) {
            return false;
        }

        // Checking if there is any of this type pending schedules
        $future_schedules = as_get_scheduled_actions(
            [
                'hook' => 'main_image_download_schedule',
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'group' => 'ProductImage'
            ]
        );

        if (!empty($future_schedules)) {
            return false;
        }

        if (empty(get_transient('wooms_start_timestamp'))) {
            return false;
        }

        if (get_transient('main_images_downloaded')) {
            return false;
        }

        return true;
    }


    /**
     * Action for UI
     */
    public static function ui_action()
    {
        $data = self::download_images_from_metafield();

        echo '<hr>';

        if (empty($data)) {
            echo '<p>Нет картинок для загрузки</p>';
        } else {
            echo "<p>Загружены миниатюры для продуктов:</p>";
            foreach ($data as $key => $value) {
                printf('<p><a href="%s">ID %s</a></p>', get_edit_post_link($value), $value);
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
    public static function download_images_from_metafield()
    {
        if (empty(get_option('woomss_images_sync_enabled'))) {
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

        $list = get_posts($args);

        if (empty($list)) {

            // Adding the option that all images downloaded
            set_transient('main_images_downloaded', true);

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Main images is downloaded')
            );

            return false;
        }

        $result = [];

        foreach ($list as $key => $value) {
            $url        = get_post_meta($value->ID, 'wooms_url_for_get_thumbnail', true);
            $image_data = get_post_meta($value->ID, 'wooms_image_data', true);

            $image_name = $image_data['filename'];

            //$check_id = self::download_img($url, $image_name, $value->ID);
            $check_id = self::uploadRemoteImageAndAttach($url, $value->ID, $image_name);

            if (!empty($check_id)) {

                set_post_thumbnail($value->ID, $check_id);
                delete_post_meta($value->ID, 'wooms_url_for_get_thumbnail');
                delete_post_meta($value->ID, 'wooms_image_data');
                $result[] = $value->ID;

                do_action(
                    'wooms_logger',
                    __CLASS__,
                    sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $value->ID, $check_id, $image_name)
                );
            } else {
                do_action(
                    'wooms_logger_error',
                    __CLASS__,
                    sprintf('Ошибка назначения картинки для продукта %s (url %s, filename: %s)', $value->ID, $url, $image_name)
                );
            }
        }

        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    /**
     * Check exist image by URL
     */
    public static function check_exist_image_by_url($url_api)
    {
        $posts = get_posts('post_type=attachment&meta_key=wooms_url&meta_value=' . $url_api);
        if (empty($posts)) {
            return false;
        } else {
            return $posts[0]->ID;
        }
    }


    /**
     * Manual start images download
     */
    public static function ui_for_manual_start()
    {
        if (empty(get_option('woomss_images_sync_enabled'))) {
            return;
        } ?>

        <h2>Изображения</h2>
        <p>Ручная загрузка изображений по 5 штук за раз.</p>

<?php printf('<a href="%s" class="button button-primary">Выполнить</a>', add_query_arg('a', 'wooms_products_images_manual_start', admin_url('admin.php?page=moysklad')));
    }

    /**
     * Settings UI
     */
    public static function settings_init()
    {
        add_settings_section('woomss_section_images', 'Изображения', null, 'mss-settings');

        register_setting('mss-settings', 'woomss_images_sync_enabled');
        add_settings_field(
            $id = 'woomss_images_sync_enabled',
            $title = 'Включить синхронизацию картинок',
            $callback = array(__CLASS__, 'setting_images_sync_enabled'),
            $page = 'mss-settings',
            $section = 'woomss_section_images'
        );

        register_setting('mss-settings', 'woomss_images_replace_to_sync');
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
    public static function setting_images_replace_to_sync()
    {
        $option = 'woomss_images_replace_to_sync';
        $desc = '<small>Если включить опцию, то плагин будет обновлять изображения, если они изменились в МойСклад.</small><p><small><strong>Внимание!</strong> Для корректной перезаписи изображений, необходимо провести повторную синхронизацию товаров. Если синхронизация товаров происходит по крону, то дождаться окончания очередной сессии синхронизации товаров</small></p>';
        printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
    }

    /**
     * setting_images_sync_enabled
     */
    public static function setting_images_sync_enabled()
    {
        $option = 'woomss_images_sync_enabled';
        $desc = '<small>Если включить опцию, то плагин будет загружать изображения из МойСклад.</small>';
        printf('<input type="checkbox" name="%s" value="1" %s /> %s', $option, checked(1, get_option($option), false), $desc);
    }
}

ProductImage::init();
