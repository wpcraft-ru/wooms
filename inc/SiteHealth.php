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
    public static $settings_page_url = 'admin.php?page=mss-settings';


    public static function init()
    {
        add_filter('site_status_tests', [__CLASS__, 'new_health_tests']);

        add_action('wp_ajax_health-check-wooms-check_different_versions_of_plugins', [__CLASS__, 'wooms_check_different_versions_of_plugins']);
        
        add_action('wp_ajax_health-check-wooms-check_login_password', [__CLASS__, 'wooms_check_login_password']);

    }

    /**
     * adding ajax hooks for site health
     *
     * @param [type] $tests
     * @return void
     */
    public static function new_health_tests($tests)
    {

        $tests['async']['wooms_check_credentials'] = [
            'test'  => 'wooms_check_login_password',
        ];

        $tests['async']['wooms_check_different_versions'] = [
            'test'  => 'wooms_check_different_versions_of_plugins',
        ];

        // var_dump($tests);
        // exit;

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
            'badge'       => [
                'label' => sprintf(__('%s Notices', 'wooms'),$base_plugin_data['Name']),
                'color' => 'blue',
            ],
            'description' => sprintf(__('All is ok! Thank you for using our plugin %s', 'wooms'), '🙂'),
        ];

        if($base_version !== $xt_version){
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['actions'] .= sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf(__('Update plugin', 'wooms'))
            );
        }

        /**
         * if base version is lower
         */
        if ($base_version < $xt_version) {
            
            $result['description'] = sprintf(__('Please update the plugin %s for better performance', 'wooms'), $base_plugin_data['Name']);
        }

        /**
         * if xt version is lower
         */
        if ($base_version > $xt_version) {
            $result['description'] = sprintf(__('Please update the plugin %s for better performance', 'wooms'), $xt_plugin_data['Name']);
        }

        wp_send_json_success($result);
    }

    /**
     * checking credentials
     *
     * @return void
     */
    public static function wooms_check_login_password(){
        check_ajax_referer('health-check-site-status');

        if (!current_user_can('view_site_health_checks')) {
            wp_send_json_error();
        }

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $url = 'https://online.moysklad.ru/api/remap/1.2/security/token';
        $data_api = wooms_request($url,[],'POST');
    
        $result = [
            'label' => __('Checking MC login and password', 'wooms'),
            'status'      => 'good',
            'badge'       => [
                'label' => sprintf(__('%s Notices', 'wooms'),$base_plugin_data['Name']),
                'color' => 'blue',
            ],
            'description' => sprintf(__('All is ok! Thank you for using our plugin %s', 'wooms'), '🙂'),
        ];

        if(array_key_exists('errors', $data_api)){
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf(__('Something went wrong when connecting to the MoySklad','wooms'),'🤔');
        }

        /**
         * 1056 is mean that login or the password is not correct
         */
        if($data_api["errors"][0]['code'] === 1056){
            $result['description'] = sprintf(__('Login or password are not correct for entering to MoySklad %s','wooms'),'🤔');
            $result['actions'] .= sprintf(
                '<p><a href="%s">%s</a></p>',
                self::$settings_page_url,
                sprintf(__('Change credentials', 'wooms'))
            );
        }

        wp_send_json_success($result);
    }

}

SiteHealth::init();
