<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * SiteHealthXT
 * 
 * @issue https://github.com/wpcraft-ru/wooms/issues/216
 */
class SiteHealthXT
{

    public static $plugin_dir = ABSPATH . "wp-content/plugins/";
    public static $base_plugin_url = "wooms/wooms.php";
    public static $xt_plugin_url = "wooms-extra/wooms-extra.php";

 
    public static function init()
    {
        add_filter('site_status_tests', [__CLASS__, 'new_health_tests']);

        add_filter('debug_information', [__CLASS__, 'add_info_to_debug']);

        add_filter('add_wooms_plugin_debug', [__CLASS__, 'wooms_check_moy_sklad_user_tarrif']);
    
    }

    /**
     * adding hooks for site health
     *
     * @param [type] $tests
     * @return void
     */
    public static function new_health_tests($tests)
    {

        $tests['direct']['wooms_check_base_plugin'] = [
            'test'  => [__CLASS__,'wooms_check_base_plugin'],
        ];

        $tests['direct']['wooms_check_different_versions'] = [
            'test'  => [__CLASS__, 'wooms_check_different_versions_of_plugins'],
        ];


        return $tests;
    }


    /**
     * Check different versions of plugins WooMS and WoomsXT
     *
     * @return void
     */
    public static function wooms_check_different_versions_of_plugins()
    {

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $xt_plugin_data = get_plugin_data(self::$plugin_dir . self::$xt_plugin_url);
        $base_version = $base_plugin_data['Version'];
        $xt_version = $xt_plugin_data['Version'];

        $result = [
            'label' => 'Разные версии плагина WooMS & WooMS XT',
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf('Все хорошо! Спасибо что выбрали наш плагин %s', '🙂'),
            'test' => 'wooms_check_different_versions' // this is only for class in html block
        ];

        if ($base_version !== $xt_version) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf("Обновить плагин")
            );
        }

        /**
         * if base version is lower
         */
        if ($base_version < $xt_version) {

            $result['description'] = sprintf('Пожалуйста, обновите плагин %s для лучшей производительности', $base_plugin_data['Name']);
        }

        /**
         * if xt version is lower
         */
        if ($base_version > $xt_version) {
            $result['description'] = sprintf('Пожалуйста, обновите плагин %s для лучшей производительности', $xt_plugin_data['Name']);
        }

        return $result;
    }

    /**
     * check_base_plugin
     */
    public static function wooms_check_base_plugin()
    {
        if ( ! function_exists('get_plugin_data') ) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        
        $result = [
            'label' => 'Для работы плагина WooMS XT требуется основной плагин WooMS',
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf('Все хорошо! Спасибо что выбрали наш плагин %s', '🙂'),
            'test' => 'wooms_check_base_plugin' // this is only for class in html block
        ];

        if (!is_plugin_active('wooms/wooms.php')) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['actions'] = sprintf(
                '<p><a href="%s" target="_blank">%s</a></p>',
                '//wordpress.org/plugins/wooms/',
                sprintf("Установить плагин")
            );
            $result['description'] = 'Для работы плагина WooMS XT требуется основной плагин WooMS.';
        }


        return $result;
    }

    /**
     * debuging and adding to debug sections of health page
     *
     * @param [type] $debug_info
     * @return void
     */
    public static function add_info_to_debug($debug_info)
    {

        if (is_plugin_active('wooms/wooms.php')) {
            return $debug_info;
        }

        $debug_info['wooms-plugin-debug'] = [
            'label'    => 'Wooms',
            'fields'   => [
                'Wooms Error' => [
                    'label'    => 'Wooms Version ',
                    'value'   => sprintf('Для работы плагина WooMS XT требуется основной плагин WooMS. %s', '❌'),
                ],
            ],
        ];

        $debug_info = apply_filters('add_wooms_plugin_debug', $debug_info);

        return $debug_info;
    }

    /**
     * check user tariff
     *
     * @param [type] $debug_info
     * @return void
     */
    public static function wooms_check_moy_sklad_user_tarrif($debug_info){

        if (!get_transient('wooms_check_moysklad_tariff')) {
            return $debug_info;
        }

        $debug_info['wooms-plugin-debug']['fields']['wooms-tariff-for-orders'] = [
            'label'    => 'Тариф МойСклад',
            'value'   => sprintf('Для корректной работы плагина нужно сменить тариф %s', '❌'),
        ];
        

        return $debug_info;
    }

}

SiteHealthXT::init();
