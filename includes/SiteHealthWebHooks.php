<?php

namespace WooMS;

class SiteHealthWebHooks
{
    public static function init()
    {

        //load class only if webhook is active
        // https://github.com/wpcraft-ru/wooms/issues/271
        if (!get_option('wooms_enable_webhooks')) {
            return;
        }

        add_filter('site_status_tests', function ($tests) {

            $tests['async']['wooms_check_webhooks'] = [
                'test'  => 'check_webhooks',
            ];

            return $tests;
        });

        add_action('wp_ajax_health-check-check-webhooks', [__CLASS__, 'check_webhooks']);

        add_filter('add_wooms_plugin_debug', [__CLASS__, 'wooms_check_moy_sklad_user_tarrif']);
    }


    /**
     * Check can we add webhooks
     *
     * @param [type] $debug_info
     * @return void
     */
    public static function wooms_check_moy_sklad_user_tarrif($debug_info)
    {

        if (!get_transient('wooms_check_moysklad_tariff')) {
            return $debug_info;
        }

        $debug_info['wooms-plugin-debug']['fields']['wooms-tariff-for-orders'] = [
            'label'    => 'Подписка МойСклад',
            'value'   => get_transient('wooms_check_moysklad_tariff'),
        ];


        return $debug_info;
    }


    /**
     * Check can we add webhooks
     *
     * @return bool
     */
    public static function check_webhooks()
    {
        $url  = 'https://online.moysklad.ru/api/remap/1.2/entity/webhook';

        $employee_url = 'https://online.moysklad.ru/api/remap/1.1/context/employee';

        // создаем веб хук в МойСклад
        $data   = array(
            'url'        => rest_url('/wooms/v1/order-update/'),
            'action'     => "UPDATE",
            "entityType" => "customerorder",
        );
        $api_result = wooms_request($url, $data);

        $result = [
            'label' => "Проверка подписки МойСклад",
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf("Все хорошо! Спасибо что используете наш плагин %s", '🙂'),
            'test' => 'wooms_check_weebhooks' // this is only for class in html block
        ];

        if (empty($api_result['errors'][0]['code'])) {
            wp_send_json_success($result);
        }

        if (30006 == $api_result['errors'][0]['code']) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf("%s %s", $api_result['errors'][0]['error'], '❌');
            set_transient('wooms_check_moysklad_tariff', $result['description'], 60 * 60);
        }

        // Checking permissions too
        $data_api_p = wooms_request($employee_url, [], 'GET');

        foreach ($data_api_p['permissions']['webhook'] as $permission) {
            if (!$permission) {
                $description = "У данного пользователя не хватает прав для работы с вебхуками";
                $result['description'] = sprintf('%s %s', $description, '❌');
                if (!empty($api_result['errors'])) {
                    $result['description'] = sprintf("1. %s 2. %s %s", $api_result['errors'][0]['error'], $description, '❌');
                }
            }

            // Добовляем значение для вывода ошибки в здаровье сайта
            set_transient('wooms_check_moysklad_tariff', $result['description'], 60 * 60);
        }

        wp_send_json_success($result);
    }
}

SiteHealthWebHooks::init();
