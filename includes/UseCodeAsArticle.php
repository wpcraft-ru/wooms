<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * UseCodeAsArticle
 * 
 * issue https://github.com/wpcraft-ru/wooms/issues/98
 */
class UseCodeAsArticle
{

    /**
     * The Init
     */
    public static function init()
    {
        // add_action('init', function () {
        //     if (!isset($_GET['dd'])) {
        //         return;
        //     }

        //     $dd = wc_get_product_id_by_sku("00053");

        //     dd($dd);

        //     // dd(get_transient('wooms_end_timestamp'));
        //     //   self::set_state('timestamp', 0);

        //     //   self::batch_handler();

        //     dd(0);
        // });

        add_filter('wooms_get_product_id', array(__CLASS__, 'get_product_id_by_code'), 40, 2);
        add_action('admin_init', array(__CLASS__, 'add_settings'), 40);
    }



    public static function get_product_id_by_code($product_id, $data_api)
    {
        if (!self::is_enable()) {
            return $product_id;
        }

        if ($product_id_by_code = wc_get_product_id_by_sku((string)$data_api['code'])) {

            self::delete_other_product_with_uuid($product_id_by_code, $data_api['id']);

            return $product_id_by_code;
        }

        return $product_id;
    }


    /**
     * if isset product with uuid
     */
    public static function delete_other_product_with_uuid($product_id_by_code, $uuid)
    {
        if (!$product_id = self::get_product_id_by_uuid($uuid)) {
            return;
        }

        if ($product_id_by_code != $product_id) {
            wp_delete_post($product_id, true);

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Удаление дубликата по uuid (name: %s, id product %s, uuid: %s)', get_the_title( $product_id_by_code ), $product_id_by_code, $uuid)
            );
        }
    }

    /**
     * get_product_id_by_uuid
     */
    public static function get_product_id_by_uuid($uuid)
    {

        $posts = get_posts('post_type=product&post_status=any&meta_key=wooms_id&meta_value=' . $uuid);

        if (empty($posts[0]->ID)) {
            return false;
        } else {
            return $posts[0]->ID;
        }
    }


    public static function is_enable()
    {
        if (get_option('wooms_use_code_as_article_enable')) {
            return true;
        }

        return false;
    }

    /**
     * Setting
     */
    public static function add_settings()
    {
        $option_key = 'wooms_use_code_as_article_enable';

        register_setting('mss-settings', $option_key);
        add_settings_field(
            $id = $option_key,
            $title = 'Использовать код как артикул',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );
                printf('<p>%s</p>', 'Если включена опция, то плагин будет пытаться связывать товары по коду из МойСклад и артикулу из Сайта');
                printf('<p>%s</p>', 'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/98" target="_blank">https://github.com/wpcraft-ru/wooms/issues/98</a>');
            },
            $page = 'mss-settings',
            $section = 'woomss_section_other',
            $args = [
                'key' => $option_key,
                'value' => get_option($option_key),
            ]
        );
    }
}

UseCodeAsArticle::init();
