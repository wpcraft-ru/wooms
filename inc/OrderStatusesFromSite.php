<?php

namespace WooMS;

/**
 * Send statuses from WooCommerce to moysklad.ru
 */
class OrderStatusesFromSite
{

    public static $statuses_match;

    /**
     * The init
     */
    public static function init()
    {
        add_filter('wooms_order_data', [__CLASS__, 'update_order_data'], 11, 2);

        add_action('admin_init', array(__CLASS__, 'settings_init'), 100);
    }


    public static function update_order_data($data_order, $order_id, $order = false){

        if(self::is_disable()){
            return $data_order;
        }

        if(empty($order)){
            $order = wc_get_order($order_id);
        }

        $statuses_match_default = array(
            'wc-pending' => 'Новый',
            'wc-processing' => 'Подтвержден',
            'wc-on-hold' => 'Новый',
            'wc-completed' => 'Отгружен',
            'wc-cancelled' => 'Отменен',
            'wc-refunded' => 'Возврат',
            'wc-failed' => 'Не удался',
        );

        self::$statuses_match = get_option('wooms_order_statuses_match', $statuses_match_default);

        $status = $order->get_status();

        if (empty(self::$statuses_match['wc-' . $status])) {
            $ms_status = '';
        } else {
            $ms_status = self::$statuses_match['wc-' . $status];
        }


        if ($ms_status) {
            $meta_status = self::get_meta_status_for_orders($ms_status);
        } else {
            $order->add_order_note(sprintf('Ошибка обновления статуса в МойСклад, не удалось получить название статуса в МойСклад для "%s"', $ms_status));
            delete_post_meta($order_id, 'wooms_changed_status');
            return $data_order;
        }

        /**
         * Если с таким статусом ничего не вышло, то удаляем мету
         */
        if (empty($meta_status)) {
            delete_post_meta($order_id, 'wooms_changed_status');
            $error_msg = sprintf('Ошибка обновления статуса в МойСклад, не найден статус в МойСклад: %s', $ms_status);
            $order->add_order_note($error_msg);
            do_action('wooms_logger_error', __CLASS__, $error_msg);
            return $data_order;
        }

        $order->add_order_note(sprintf('Изменен сатус Заказа в МойСклад: %s', $ms_status));

        $data_order['state']['meta'] = $meta_status;

        return $data_order;
    }


    /**
     * Получаем мету статуса для заказов
     * Нужна для обновления статуса Заказа из Сайта на Склад
     */
    public static function get_meta_status_for_orders($changed_status = '')
    {
        if (empty($changed_status)) {
            return false;
        }

        $statuses = get_transient('wooms_order_statuses');
        if (empty($statuses)) {
            $url_statuses = 'https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata';
            $statuses = wooms_request($url_statuses);
            $statuses = $statuses["states"];
            set_transient('wooms_order_statuses', $statuses, 600);
        }

        foreach ($statuses as $statuse) {
            if ($statuse['name'] == $changed_status) {
                $meta_status = $statuse["meta"];
                return $meta_status;
            }
        }

        return false;
    }


    public static function is_disable(){
        if(get_option('wooms_enable_orders_statuses_updater')){
            return false;
        }

        return true;
    }


    /**
     * settings_init
     */
    public static function settings_init()
    {

        register_setting('mss-settings', 'wooms_enable_orders_statuses_updater');
        add_settings_field(
            $id = 'wooms_enable_orders_statuses_updater',
            $title = 'Передатчик Статуса:<br/> Сайт > МойСклад',
            $callback = array(__CLASS__, 'display_enable_orders_statuses_updater',),
            $page = 'mss-settings',
            $section = 'wooms_section_orders'
        );

        if (get_option('wooms_enable_orders_statuses_updater')) {
            register_setting('mss-settings', 'wooms_order_statuses_match');
            add_settings_field(
                $id = 'wooms_order_statuses_match',
                $title = 'Связь статусов:<br/> от Сайта на МойСклад',
                $callback = array(__CLASS__, 'display_wooms_order_statuses_match',),
                $page = 'mss-settings',
                $section = 'wooms_section_orders'
            );
        }
    }

    /**
     * Match statuses from Site to MoySkald
     */
    public static function display_wooms_order_statuses_match()
    {

        if (empty(get_option('wooms_enable_orders_statuses_updater'))) {
            printf('<p>%s</p>', __('Для связи статусов, нужно включить опцию передачи статусов из Сайта на Склад', 'wooms'));
            return;
        }

        $option_key = 'wooms_order_statuses_match';

        $statuses = wc_get_order_statuses();
        if (empty($statuses) or !is_array($statuses)) {
            printf(
                '<p>%s</p>',
                __('Что то пошло не так, сообщите о проблеме в тех поддержку', 'wooms')
            );
            return;
        }

        printf(
            '<p>%s</p>',
            __('Нужно написать какие статусы указывать в МойСклад, при смене статуса Заказов на Сайте, названия должны совпадать со статусами в МойСклад.', 'wooms')
        );

        $option_value = get_option($option_key);
        if (empty($option_value)) {
            $option_value = array();
        }

        foreach ($statuses as $status_key => $status_name) {

            if (empty($option_value[$status_key])) {
                switch ($status_key) {
                    case 'wc-pending':
                        $option_value[$status_key] = 'Новый';
                        break;

                    case 'wc-processing':
                        $option_value[$status_key] = 'Подтвержден';
                        break;

                    case 'wc-on-hold':
                        $option_value[$status_key] = 'Новый';
                        break;

                    case 'wc-completed':
                        $option_value[$status_key] = 'Отгружен';
                        break;

                    case 'wc-cancelled':
                        $option_value[$status_key] = 'Отменен';
                        break;

                    case 'wc-refunded':
                        $option_value[$status_key] = 'Возврат';
                        break;

                    case 'wc-failed':
                        $option_value[$status_key] = 'Не удался';
                        break;

                    default:
                        $option_value[$status_key] = 'Новый';
                        break;
                }
            }


            printf(
                '<p>%s (%s) > <input type="text" name="%s[%s]" value="%s" /></p>',
                $status_name,
                $status_key,
                $option_key,
                $status_key,
                $option_value[$status_key]
            );
        }
    }

    /**
     * Send statuses to MoySklad
     */
    public static function display_enable_orders_statuses_updater()
    {
        $option = 'wooms_enable_orders_statuses_updater';
        printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));
?>
        <p><small>Передатчик статусов с Сайта в МойСклад при активации будет выполняться 1 раз в минуту. Можно менять механизм с помощью программистов через хуки WP.</small></p>

<?php
    }
}

OrderStatusesFromSite::init();
