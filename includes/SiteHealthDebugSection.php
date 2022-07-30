<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * SiteHealthDebugSection
 */
class SiteHealthDebugSection
{

    public static $plugin_dir = ABSPATH . "wp-content/plugins/";
    public static $base_plugin_url = "wooms/wooms.php";
    public static $settings_page_url = 'admin.php?page=mss-settings';
    public static $wooms_check_login_password;
    public static $wooms_check_woocommerce_version_for_wooms;



    public static function init()
    {
        add_filter('debug_information', [__CLASS__, 'add_info_to_debug']);

        add_filter('add_wooms_plugin_debug', [__CLASS__, 'wooms_debug_check_version_for_wooms']);


        add_filter('add_wooms_plugin_debug', [__CLASS__, 'check_login_and_password']);


    }

    public static function wooms_debug_check_version_for_wooms($debug_info)
    {
        $wc_version = WC()->version;

        $result = [
            'label'    => 'Версия WooCommerce',
            'value'   => sprintf('%s %s', $wc_version, '✔️'),
        ];

        if (version_compare($wc_version, '3.6.0', '<=')) {
            $result['value'] = sprintf('Ваша версия WooCommerce плагина %s. Обновите пожалуйста WooCommerce чтобы WooMS & WooMS XT работали %s', $wc_version, '❌');
        }

        $debug_info['wooms-plugin-debug']['fields']['Woocommerce'] = $result;

        return $debug_info;
    }


    /**
     * debuging and adding to debug sections of health page
     *
     * @param [type] $debug_info
     * @return void
     */
    public static function add_info_to_debug($debug_info)
    {

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $base_version = $base_plugin_data['Version'];

        $debug_info['wooms-plugin-debug'] = [
            'label'    => 'Wooms',
            'fields'   => [
                'Wooms Version' => [
                    'label'    => 'Версия WooMS',
                    'value'   => sprintf('%s %s', $base_version, '✔️'),
                ]
            ],
        ];

        $debug_info = apply_filters('add_wooms_plugin_debug', $debug_info);

        return $debug_info;
    }

    /**
     * checking login and password moy sklad
     *
     * @param [type] $debug_info
     * @return void
     */
    public static function check_login_and_password($debug_info)
    {

        if (!get_transient('wooms_check_login_password')) {
            return $debug_info;
        }

        $debug_info['wooms-plugin-debug']['fields']['wooms-login-check'] = [
            'label'    => 'Версия WooMS',
            'value'   => get_transient('wooms_check_login_password'),
        ];
        return $debug_info;
    }

}

SiteHealthDebugSection::init();
