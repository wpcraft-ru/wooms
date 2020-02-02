<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Import Product Images
 */
class SiteHealth
{

    public static $plugin_dir = ABSPATH . "wp-content/plugins/";
    public static $base_plugin_url = "wooms/wooms.php";
    public static $xt_plugin_url = "wooms-extra/wooms-extra.php";


    public static function init()
    {
        add_filter('site_status_tests', [__CLASS__, 'new__health_tests']);
        add_action('wp_ajax_health-check-wooms-check_different_versions_of_plugins', [__CLASS__, 'wooms_check_different_versions_of_plugins']);
    }

    /**
     * adding ajax hooks for site health
     *
     * @param [type] $tests
     * @return void
     */
    public static function new__health_tests($tests)
    {

        $tests['async']['wooms_check_different_versions'] = [
            'test'  => 'wooms_check_different_versions_of_plugins',
        ];
        return $tests;
    }

    /**
     * check differences of versions
     *
     * @return void
     */
    public static function wooms_check_different_versions_of_plugins()
    {

        check_ajax_referer('health-check-site-status');

        if (!current_user_can('view_site_health_checks')) {
            wp_send_json_error();
        }

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $xt_plugin_data = get_plugin_data(self::$plugin_dir . self::$xt_plugin_url);
        $base_version = $base_plugin_data['Version'];
        $xt_version = $xt_plugin_data['Version'];

        $result = [
            'label' => __('Different versions of plugins', 'wooms'),
            'status'      => 'good',
            'badge'       => array(
                'label' => sprintf(__('%s Notices', 'wooms'),$base_plugin_data['Name']),
                'color' => 'blue',
            ),
            'description' => sprintf(__('All is ok! Thank you for using our plugin %s', 'wooms'), '🙂'),
            'actions'     => '',
        ];

        /**
         * if base version is lower
         */
        if ($base_version < $xt_version) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf(__('Please update the plugin %s for better performance', 'wooms'), $base_plugin_data['Name']);
            $action = 'upgrade-plugin';
            $result['actions'] .= sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf(__('Update plugin', 'wooms'))
            );
        }

        /**
         * if xt version is lower
         */
        if ($base_version > $xt_version) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf(__('Please update the plugin %s for better performance', 'wooms'), $xt_plugin_data['Name']);
            $action = 'upgrade-plugin';
            $result['actions'] .= sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf(__('Update plugin', 'wooms'))
            );
        }

        wp_send_json_success($result);
    }
}

SiteHealth::init();
