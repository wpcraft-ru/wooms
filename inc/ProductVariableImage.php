<?php

namespace WooMS;


class ProductVariableImage
{

    use MSImages;


    public static $image_meta_key = 'wooms_miniature';

    public static $state_key = 'wooms_variation_image_sync_state';

    public static function init()
    {

        add_action('wooms_variaion_image_sync', [__CLASS__, 'walker']);

        add_filter('wooms_variation_save', [__CLASS__, 'add_image_task'], 10, 3);
        add_action('init', [__CLASS__, 'add_schedule_hook']);
        add_action('wooms_wakler_variations_finish', [__CLASS__, 'restart']);
    }


    /**
     * restart if finish variations walker
     */
    public static function restart()
    {
        delete_transient('wooms_variations_image_sync_finish_timestamp');
    }


    public static function walker()
    {
        $state = self::get_state();

        $variants = get_posts(array(
            'post_type'   => 'product_variation',
            'numberposts' => 5,
            'meta_query'  => array(
                array(
                    'key'     => self::$image_meta_key,
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        if (empty($variants)) {
            set_transient('wooms_variations_image_sync_finish_timestamp', time(), HOUR_IN_SECONDS);
        }

        foreach ($variants as $variant) {
            self::download_img_for_product($variant->ID);
        }
    }


    /**
     * download_img_for_product
     */
    public static function download_img_for_product($variation_id)
    {

        $img_meta = get_post_meta($variation_id, self::$image_meta_key, true);
        $img_meta = json_decode($img_meta, true);


        if (empty($img_meta['meta']['downloadHref'])) {
            return false;
        }

        $url_download = $img_meta['meta']['downloadHref'];

        $image_name = $img_meta['filename'];

        $check_id = self::uploadRemoteImageAndAttach($url_download, $variation_id, $image_name);

        $variation = wc_get_product($variation_id);
        $variation->set_image_id($check_id);
        $variation->delete_meta_data(self::$image_meta_key);
        $variation->save();
    }


    /**
     * add_image_task
     *
     * use hook $variation = apply_filters('wooms_variation_save', $variation, $variant_data, $product_id);
     */
    public static function add_image_task($variation, $variant_data, $product_id)
    {

        if (empty($variant_data['images']['meta']['href'])) {
            return $variation;
        }

        $href = $variant_data['images']['meta']['href'];
        $img_metadata = wooms_request($href);

        if (empty($img_metadata['rows'][0])) {
            return $variation;
        }

        $img_metadata = $img_metadata['rows'][0];
        $img_metadata = json_encode($img_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $variation->update_meta_data(self::$image_meta_key, $img_metadata);

        return $variation;
    }


    /**
     * Cron task restart
     */
    public static function add_schedule_hook()
    {

        if (self::is_wait()) {
            return;
        }

        if (as_next_scheduled_action('wooms_variaion_image_sync')) {
            return;
        }
        
        // Adding schedule hook
        as_schedule_single_action(
            time() + 60,
            'wooms_variaion_image_sync',
            [],
            'WooMS'
        );
    }


    /**
     * check need walker start or not
     */
    public static function is_wait()
    {
        if (get_transient('wooms_variations_image_sync_finish_timestamp')) {
            return true;
        }

        return false;
    }


    /**
     * get_state
     */
    public static function get_state($key = '')
    {
        $state = get_transient(self::$state_key);
        if (empty($key)) {
            return $state;
        }

        if (isset($state[$key])) {
            return $state[$key];
        }

        return null;
    }


    /**
     * set_state
     */
    public static function set_state($key = '', $value = '')
    {

        $state = get_transient(self::$state_key);

        if (is_array($state)) {
            $state[$key] = $value;
        } else {
            $state = [
                $key => $value
            ];
        }

        set_transient(self::$state_key, $state);

        return $state;
    }
}

ProductVariableImage::init();
