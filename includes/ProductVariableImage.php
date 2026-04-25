<?php

namespace WooMS;

use function WooMS\request;


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
     *
     * Phase 2 fixes:
     * - Clear malformed wooms_miniature meta (missing/invalid downloadHref) to prevent infinite loops
     * - Only set image ID if attachment download was successful
     */
    public static function download_img_for_product($variation_id)
    {

        $img_meta = get_post_meta($variation_id, self::$image_meta_key, true);
        $img_meta = json_decode($img_meta, true);

        // Phase 2a: Clear malformed task meta if downloadHref is missing or invalid
        if (empty($img_meta['meta']['downloadHref'])) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variation->delete_meta_data(self::$image_meta_key);
                $variation->save();
            }

            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Malformed wooms_miniature meta for variation %d: missing downloadHref', $variation_id),
                $img_meta
            );
            return false;
        }

        $url_download = $img_meta['meta']['downloadHref'];
        $image_name = $img_meta['filename'] ?? 'image.jpg';

        // Phase 2b: Guard against failed image download before clearing task meta
        $check_id = self::uploadRemoteImageAndAttach($url_download, $variation_id, $image_name);

        if (empty($check_id)) {
            // Log failure but do NOT clear meta — allow retry on next worker cycle
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Failed to download image for variation %d from %s', $variation_id, $url_download)
            );
            return false;
        }

        $variation = wc_get_product($variation_id);
        if ($variation) {
            $variation->set_image_id($check_id);
            $variation->delete_meta_data(self::$image_meta_key);
            $variation->save();
        }
    }


    /**
     * add_image_task
     *
     * Phase 1 fix: Guard against re-queuing variation images that already have matching thumbnail.
     * Only set wooms_miniature meta if:
     * - No current thumbnail exists, OR
     * - Current thumbnail.wooms_url differs from new downloadHref
     *
     * use hook $variation = apply_filters('wooms_variation_save', $variation, $variant_data, $product_id);
     */
    public static function add_image_task($variation, $variant_data, $product_id)
    {

        if (empty($variant_data['images']['meta']['href'])) {
            return $variation;
        }

        $href = $variant_data['images']['meta']['href'];
        $img_metadata = request($href);

        if (empty($img_metadata['rows'][0])) {
            return $variation;
        }

        $img_metadata = $img_metadata['rows'][0];

        // Phase 1: Check if current thumbnail already matches the new downloadHref
        $variation_id = $variation->get_id();
        $current_thumb_id = get_post_thumbnail_id($variation_id);

        if (!empty($current_thumb_id)) {
            $current_wooms_url = get_post_meta($current_thumb_id, 'wooms_url', true);
            $new_download_href = $img_metadata['meta']['downloadHref'] ?? '';

            // If URLs match, skip re-queueing — thumbnail already matches MoySklad state
            if (!empty($current_wooms_url) && !empty($new_download_href) && $current_wooms_url === $new_download_href) {
                return $variation;
            }
        }

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
