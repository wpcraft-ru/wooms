<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

trait MSImages
{
    /**
     * https://wordpress.stackexchange.com/questions/107346/download-an-image-from-a-webpage-to-the-default-uploads-folder
     *
     * @param [type] $image_url
     * @param [type] $parent_id
     */
    public static function uploadRemoteImageAndAttach($image_url, $product_id, $filename = 'image.jpg')
    {
        if ($check_id = self::check_exist_image_by_url($image_url)) {
            return $check_id;
        }

        $uploads_dir = wp_upload_dir();
        $post_name = get_post_field('post_name', $product_id);
        $filename_data = wp_check_filetype($filename);
        $filename = $post_name . '.' . $filename_data['ext'];
        $filename = sanitize_file_name($filename);
        $filename = wp_unique_filename($uploads_dir['path'], $filename);

        $image = wooms_download_image($image_url);

        if (is_wp_error($image)) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка загрузки картинки: %s', $image->get_error_message()),
                $image->get_error_code()
            );

            return false;
        }

        $mirror = wp_upload_bits($filename, '', $image);

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
            __CLASS__,
            sprintf('Image is downloaded %s (ИД %s, filename: %s)', $product_id, $attach_id, $filename)
        );

        return $attach_id;
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

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('We have such image (%s) already', $posts[0]->ID)
            );

            return $posts[0]->ID;
        }
    }
}
