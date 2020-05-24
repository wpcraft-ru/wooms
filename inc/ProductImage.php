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

    public static $walker_hook_name = 'wooms_product_image_sync';

    /**
     * WooMS_Import_Product_Images constructor.
     */
    public static function init()
    {


        // add_action('init', function(){
        //   if(!isset($_GET['dd'])){
        //     return;
        //   }

        // //   dd(get_transient('wooms_end_timestamp'));
        //   // self::set_state('timestamp', 0);
        //   self::product_image_download(11774);
        //   // self::batch_handler();

        //   dd(0);
        // });


        add_action('wooms_product_image_sync', [__CLASS__, 'batch_handler']);
        add_action('init', [__CLASS__, 'add_schedule_hook']);
        add_action('wooms_main_walker_finish', [__CLASS__, 'restart']);

        /**
         * Обновление данных о продукте
         */
        add_filter('wooms_product_save', [__CLASS__, 'update_product'], 35, 3);

        add_action('admin_init', [__CLASS__, 'settings_init'], 50);


        add_action('woomss_tool_actions_btns', [__CLASS__, 'ui_for_manual_start'], 15);
        add_action('woomss_tool_actions_wooms_products_images_manual_start', [__CLASS__, 'ui_action']);
    }


    public static function restart()
    {
        delete_transient('wooms_product_image_sync_finish');
    }


    /**
     * add image to metafield for download
     */
    public static function update_product($product, $value, $data)
    {
        if (!self::is_enable()) {
            return $product;
        }

        $product_id = $product->get_id();

        //Check image
        if (empty($value['images']['meta']['size'])) {
            return $product;
        } else {
            $url = $value['images']['meta']['href'];
        }

        //check current thumbnail. if isset - break, or add url for next downloading
        if ($id = get_post_thumbnail_id($product_id) && empty(get_option('woomss_images_replace_to_sync'))) {
            return $product;
        } else {
            $product->update_meta_data('wooms_url_for_get_thumbnail', $url);
        }

        return $product;
    }

    /**
     * Init Scheduler
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

        as_schedule_single_action(time() + 11, self::$walker_hook_name, [], 'WooMS');
    }

    /**
     * Action for UI
     */
    public static function ui_action()
    {
        $data = self::batch_handler();

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
     * checking the option activation
     */
    public static function is_enable()
    {
        if (empty(get_option('woomss_images_sync_enabled'))) {
            return false;
        }

        return true;
    }


    /**
     * checking the pause state
     */
    public static function is_wait()
    {
        if (get_transient('wooms_product_image_sync_finish')) {
            return true;
        }

        return false;
    }


    /**
     * Download images from meta
     */
    public static function batch_handler()
    {
        if (!self::is_enable()) {
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

            set_transient('wooms_product_image_sync_finish', time());

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Главные изображения продуктов загружены')
            );

            return false;
        }

        $result = [];

        foreach ($list as $key => $value) {
            if (self::product_image_download($value->ID)) {
                $result[] = $value->ID;
            }

            delete_post_meta($value->ID, 'wooms_url_for_get_thumbnail');
        }

        self::add_schedule_hook(true);

        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    public static function product_image_download($product_id)
    {
        if (!$url = get_post_meta($product_id, 'wooms_url_for_get_thumbnail', true)) {
            return false;
        }

        $images_data = wooms_request($url);

        if (empty($images_data['rows'][0]['filename'])) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка получения картинки для продукта %s (url %s)', $product_id, $url ),
                $images_data
            );
            return false;
        }

        $image_name = $images_data['rows'][0]['filename'];
        $url = $images_data['rows'][0]['meta']['downloadHref'];

        $check_id = self::uploadRemoteImageAndAttach($url, $product_id, $image_name);

        if (!empty($check_id)) {

            set_post_thumbnail($product_id, $check_id);
            delete_post_meta($product_id, 'wooms_url_for_get_thumbnail');
            delete_post_meta($product_id, 'wooms_image_data');

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Загружена картинка для продукта %s (ИД %s, filename: %s)', $product_id, $check_id, $image_name)
            );
            return true;
        } else {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка назначения картинки для продукта %s (url %s, filename: %s)', $product_id, $url, $image_name)
            );
            return false;
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
        if (!self::is_enable()) {
            return;
        }

        $strings = [];

        if (as_next_scheduled_action(self::$walker_hook_name)) {
            $strings[] = sprintf('<strong>Статус:</strong> %s', 'Выполняется очередями в фоне');
        } else {
            $strings[] = sprintf('<strong>Статус:</strong> %s', 'в ожидании новых задач');
        }

        $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_product_image_sync&orderby=schedule&order=desc'));

        if (defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER) {
            $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductImage'));
        } else {
            $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
        }

?>

        <h2>Изображения</h2>
        <p>Ручная загрузка изображений по 5 штук за раз.</p>

        <?php printf('<a href="%s" class="button button-primary">Выполнить</a>', add_query_arg('a', 'wooms_products_images_manual_start', admin_url('admin.php?page=moysklad')));


        ?>
        <div class="wrap">
            <div id="message" class="notice notice-warning">
                <?php

                foreach ($strings as $string) {
                    printf('<p>%s</p>', $string);
                }

                do_action('wooms_product_images_info');

                ?>
            </div>
        </div>
<?php
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
