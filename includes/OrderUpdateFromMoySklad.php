<?php

namespace WooMS;

use Exception;

/**
 * Send statuses from moysklad.ru to WooCommerce
 */
class OrderUpdateFromMoySklad
{

    /**
     * этот хук является ключем для ActionSheduler и для метаполя, по которому определяются заказы для обновления.
     */
    public static $hook_order_update_from_moysklad = 'wooms_order_update_from_moysklad';
    public static $state_key = 'wooms_order_update_from_moysklad_state';

    /**
     * The init
     */
    public static function init()
    {
        // add_action('init', function () {
        //     if (!isset($_GET['dd'])) {
        //         return;
        //     }

        //     self::update_order_from_moysklad(23690);

        //     die("ddd");
        // });

        add_action('wooms_order_update_from_moysklad', function ($args) {
            if (empty($args['post_id'])) {
                return;
            }
            self::update_order_from_moysklad($args['post_id']);
            delete_post_meta($args['post_id'], self::$hook_order_update_from_moysklad);
        });

        add_action('rest_api_init', array(__CLASS__, 'add_route'));

        add_action('init', array(__CLASS__, 'add_schedule_hook'));

        add_action('wooms_check_orders_for_sync_from_moysklad', array(__CLASS__, 'batch_handler'));


        add_action('admin_init', array(__CLASS__, 'add_settings'), 100);

        add_filter('wooms_update_order_from_moysklad', array(__CLASS__, 'update_order_status'), 11, 2);
        add_filter('wooms_update_order_from_moysklad', array(__CLASS__, 'update_order_items'), 15, 2);

        add_filter('wooms_skip_order_update', array(__CLASS__, 'skip_order_update_from_site'), 10, 2);

        add_action('save_post_shop_order', array(__CLASS__, 'lock_callback_update'));
    }


    public static function lock_callback_update($order_id)
    {

        //если стоит задача на апдейт из МойСклад, то пропускаем
        if (get_post_meta($order_id, self::$hook_order_update_from_moysklad, true)) {
            return;
        }

        update_post_meta($order_id, 'lock_callback_update', 1);
    }


    /**
     * update_order_status
     * 
     * use hook wooms_update_order_from_moysklad
     * 
     * @param \WC_Order $order
     * 
     * @return \WC_Order - order object
     */
    public static function update_order_status($order, $data_api)
    {
        if (!self::is_enable()) {
            return $order;
        }

        $state_url  = $data_api["state"]["meta"]["href"];
        $state_data = wooms_request($state_url);
        if (empty($state_data['name'])) {
            return $order;
        }

        $state_name = $state_data['name'];

        $statuses_match_default = array(
            'wc-pending' => 'Новый',
            'wc-processing' => 'Подтвержден',
            'wc-on-hold' => 'Новый',
            'wc-completed' => 'Отгружен',
            'wc-cancelled' => 'Отменен',
            'wc-refunded' => 'Возврат',
            'wc-failed' => 'Не удался',
        );

        $statuses_match = get_option('wooms_order_statuses_from_moysklad', $statuses_match_default);

        foreach ($statuses_match as $status_key => $status_name) {

            if ($status_name == $state_name && $order->get_status() != $status_key) {
                $order->set_status($status_key, sprintf('Выбран статус через МойСклад: "%s"', $status_name));
            }
        }

        return $order;
    }

    /**
     * update_order_items
     * 
     * @param \WC_Order $order
     * 
     * @return \WC_Order - order object
     */
    public static function update_order_items($order, $data_api)
    {

        if (empty($data_api['positions']['meta']['href'])) {
            return $order;
        }

        $url_api = $data_api['positions']['meta']['href'];

        $data = wooms_request($url_api);


        $shipment_product_href = self::get_shipment_product_href();

        // remove shipment if exist from data api
        foreach ($data['rows'] as $key => $row) {
            if ($shipment_product_href == $row['assortment']['meta']['href']) {
                unset($data['rows'][$key]);
            }
        }

        $data_api_wooms_id_array = [];

        foreach ($data['rows'] as $row) {

            $product_href = $row['assortment']['meta']['href'];
            $product_uuid = wooms_get_wooms_id_from_href($product_href);
            $data_api_wooms_id_array[] = $product_uuid;
            $product_id = wooms_get_product_id_by_uuid($product_uuid);

            $product = wc_get_product($product_id);

            if ($item_id = self::get_order_item_id_by_wooms_id($product_uuid, $order)) {
                $line_item = new \WC_Order_Item_Product($item_id);
            } else {
                $line_item = new \WC_Order_Item_Product();
            }

            $line_item->set_product($product);
            $line_item->set_order_id($order->get_id());
            $line_item->set_quantity($row['quantity']);

            $total = floatval($row['quantity'] * $row['price'] / 100);

            $discount = $row['discount'];

            if (empty($discount)) {
                $line_item->set_total($total);
            } else {
                $total_with_discont = $total - ($total * $discount / 100);
                $line_item->set_total($total_with_discont);
            }

            $line_item->set_subtotal($total);

            $line_item->update_meta_data('wooms_id', $product_uuid);
            $line_item->set_total_tax(0);

            // if(!empty($row['vat'])){

            //     $tax = $row['vat'];

            //     if(empty($total_with_discont)){
            //         $tax_total = $tax * $total;
            //     } else {
            //         $tax_total = $tax * $total_with_discont;
            //     }

            //     $line_item->set_tax_class(strval($tax));
            //     $line_item->set_total_tax($tax_total);
            // }

            $item_id = $line_item->save();

            // $items = $order->get_items();
        }


        //delete old line items
        foreach ($order->get_items('line_item') as $line_item) {

            if ($wooms_id = self::get_wooms_id_from_line_item($line_item)) {

                if (!in_array($wooms_id, $data_api_wooms_id_array)) {
                    $order->remove_item($line_item->get_id());
                }
            }
        }


        // $order->calculate_totals();
        // $order->save();

        return $order;
    }

    /**
     * get item_id from order and wooms_id
     * 
     * @param string $wooms_uuid
     * @param \WC_Order $order
     */
    public static function get_order_item_id_by_wooms_id($wooms_uuid, $order)
    {
        if (empty($wooms_uuid)) {
            return false;
        }

        $line_items = $order->get_items('line_item');

        foreach ($line_items as $line_item) {

            $wooms_id = self::get_wooms_id_from_line_item($line_item);

            if ($wooms_id == $wooms_uuid) {
                return $line_item->get_id();
            }
        }

        return false;
    }


    /**
     * Get wooms id from line item
     * 
     * @param \WC_Order_Item_Product $line_item
     */
    public static function get_wooms_id_from_line_item($line_item)
    {
        $wooms_id = $line_item->get_meta('wooms_id', true);
        if (empty($wooms_id)) {

            $product_id = $line_item->get_variation_id();
            if (empty($product_id)) {
                $product_id = $line_item->get_product_id();
            }
            $wooms_id = get_post_meta($product_id, 'wooms_id', true);
        }

        if (empty($wooms_id)) {
            return false;
        }

        return $wooms_id;
    }


    /**
     * Skip order update from site, if exist task to update from MoySklad
     * 
     * @param bool $skip
     * @param \WC_Order $order
     * 
     * @return bool - skip or no
     */
    public static function skip_order_update_from_site($skip, $order)
    {
        if (!self::is_enable()) {
            return $skip;
        }

        $task_exist = $order->get_meta(self::$hook_order_update_from_moysklad, true);

        if ($task_exist) {
            return true;
        }

        return $skip;
    }


    public static function get_shipment_product_href()
    {
        if ($href = get_transient('wooms_shipment_href')) {
            return $href;
        }

        if (!$code = get_option('wooms_order_shipment_item_code')) {
            return false;
        }

        $url_api = sprintf('https://online.moysklad.ru/api/remap/1.2/entity/service?filter=code=%s', $code);

        $data = wooms_request($url_api);

        if (empty($data['rows'][0]['meta']['href'])) {
            return false;
        }

        $href = $data['rows'][0]['meta']['href'];
        set_transient('wooms_shipment_href', $href, 60);

        return $href;
    }


    public static function batch_handler()
    {

        if (!self::is_enable()) {
            return;
        }

        $args = [
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'meta_query'             => array(
                array(
                    'key'     => self::$hook_order_update_from_moysklad,
                    'compare' => 'EXISTS',
                ),
            ),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'cache_results'          => false,
        ];

        $posts = get_posts($args);


        if (empty($posts)) {
            self::set_state('is_wait', 1);
            return;
        }

        foreach ($posts as $post) {

            $args = [
                'data' => [
                    'post_id' => $post->ID,
                    'time' => time(),
                ]
            ];

            // as_enqueue_async_action( self::$hook_order_update_from_moysklad, $args, 'WooMS' );
            as_schedule_single_action(time(), self::$hook_order_update_from_moysklad, $args, 'WooMS');
        }

        return;
    }

    /**
     * update_order_from_moysklad
     */
    public static function update_order_from_moysklad($order_id = 0)
    {
        if (empty($order_id)) {
            return false;
        }

        $order_id = intval($order_id);
        if (!$order = wc_get_order($order_id)) {
            return false;
        }

        if (!$wooms_id = $order->get_meta('wooms_id', true)) {
            delete_post_meta($order_id, self::$hook_order_update_from_moysklad);
            return false;
        }

        $url_api = sprintf('https://online.moysklad.ru/api/remap/1.2/entity/customerorder/%s', $wooms_id);

        $data = wooms_request($url_api);

        $order = apply_filters('wooms_update_order_from_moysklad', $order, $data);

        $order->save();
        $order = wc_get_order($order_id);


        $order->save();

        $order->calculate_totals();

        $sum = number_format($data['sum'] / 100, 2, '.', '');
        if ($sum != $order->get_total()) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка в сумме Заказа после обновления данных из МойСклад (сумма МС %s, сумма на сайте %s)', $sum, $order->get_total())
            );
        }

        do_action('wooms_update_order_from_moysklad_after_save', $order, $data);

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Заказ обновился данными из МойСклад (id %s, номер %s)', $order->get_id, $order->get_order_number())
        );

        return $order_id;
    }


    /**
     * Setup schedule
     *
     * @return mixed
     */
    public static function add_schedule_hook()
    {
        if (self::is_wait()) {
            as_unschedule_all_actions('wooms_check_orders_for_sync_from_moysklad');
            return;
        }

        if (!self::is_enable()) {
            as_unschedule_all_actions('wooms_check_orders_for_sync_from_moysklad');
            return;
        }

        if (false == as_next_scheduled_action('wooms_check_orders_for_sync_from_moysklad')) {
            as_schedule_recurring_action(time(), MINUTE_IN_SECONDS, 'wooms_check_orders_for_sync_from_moysklad', [], 'WooMS');
        }
    }


    /**
     * check wait state
     */
    public static function is_wait()
    {

        if (self::get_state('is_wait')) {
            return true;
        }

        return false;
    }


    /**
     * get state data
     */
    public static function get_state($key = '')
    {
        if (!$state = get_option(self::$state_key)) {
            $state = [];
            update_option(self::$state_key, $state);
        }

        if (empty($key)) {
            return $state;
        }

        if (empty($state[$key])) {
            return null;
        }

        return $state[$key];
    }

    /**
     * set state data
     */
    public static function set_state($key, $value)
    {

        if (!$state = get_option(self::$state_key)) {
            $state = [];
        }

        if (is_array($state)) {
            $state[$key] = $value;
        } else {
            $state = [];
            $state[$key] = $value;
        }

        update_option(self::$state_key, $state);
    }


    /**
     * Enable webhooks from MoySklad
     */
    public static function display_wooms_enable_webhooks()
    {
        $option = 'wooms_enable_webhooks';
        printf('<input type="checkbox" name="%s" value="1" %s />', $option, checked(1, get_option($option), false));

        printf(
            '<p><small>%s</small></p>',
            'Передатчик статусов из Мой Склад может работать только с 1 сайтом и только на платных тарифах сервиса Мой склад. Если вы используете платные тарифы, включите данную опцию.'
        );

        self::get_status_order_webhook();
    }

    /**
     * get_status_order_webhook
     */
    public static function get_status_order_webhook()
    {

        $check    = self::check_webhooks_and_try_fix();
        $url      = 'https://online.moysklad.ru/api/remap/1.2/entity/webhook?limit=50';
        $data     = wooms_request($url);

        $webhooks = array();

        if (!empty($data['rows'])) {
            foreach ($data['rows'] as $row) {
                if ($row['url'] == rest_url('/wooms/v1/order-update/')) {
                    $webhooks[$row['id']] = array(
                        'entityType' => $row['entityType'],
                        'url'        => $row['url'],
                        'method'     => $row['method'],
                        'enabled'    => $row['enabled'],
                        'action'     => $row['action'],
                    );
                }
            }
        }

        if (empty(get_option('wooms_enable_webhooks'))) {
            if (empty($webhooks)) {
                echo "Хук на стороне МойСклад отключен в соответствии с настройкой";
            } else {
                echo "Что то пошло не так. Хук на стороне МойСклад остался включен в нарушении настроек.";
            }
        } else {
            if (empty($webhooks)) {
                echo "Что то пошло не так. Хук на стороне МойСклад отключен в нарушении настройки. Попробуйте отключить и включить снова. Если не поможет - обратитесь в техподдержку.";
            } else {
                echo "Хук на стороне МойСклад добавлен в соответствии с настройки";
            }
        }
        echo '<p><small>Ссылка для приема данных из системы МойСклад: ' . rest_url('/wooms/v1/order-update/') . '</small></p>';

        if (!empty($data["errors"][0]["error"])) {
            $msg = $data["errors"][0]["error"];
            printf('<p><strong>%s</strong></p>', $msg);
        }
    }

    /**
     * Check isset hook and fix if not isset
     *
     * @return bool
     */
    public static function check_webhooks_and_try_fix()
    {

        $url  = 'https://online.moysklad.ru/api/remap/1.2/entity/webhook';
        $data = wooms_request($url);

        $webhooks = array();

        if (!empty($data['rows'])) {

            foreach ($data['rows'] as $row) {
                if ($row['entityType'] != 'customerorder') {
                    continue;
                }

                $webhooks[$row['id']] = array(
                    'entityType' => $row['entityType'],
                    'url'        => $row['url'],
                    'method'     => $row['method'],
                    'enabled'    => $row['enabled'],
                    'action'     => $row['action'],
                );
            }
        }


        //Проверка на включение опции и наличия хуков
        if (empty(get_option('wooms_enable_webhooks'))) {

            if (empty($webhooks)) {
                return true;
            } else {
                //пытаемся удалить лишний хук
                foreach ($webhooks as $id => $value) {
                    $url   = 'https://online.moysklad.ru/api/remap/1.2/entity/webhook/' . $id;
                    $check = wooms_request($url, null, 'DELETE');
                }

                return false;
            }
        } else {

            //Если нужного вебхука нет - создаем новый
            if (empty($webhooks)) {
                // создаем веб хук в МойСклад
                $data   = array(
                    'url'        => rest_url('/wooms/v1/order-update/'),
                    'action'     => "UPDATE",
                    "entityType" => "customerorder",
                );
                $result = wooms_request($url, $data);

                if (empty($result)) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return true;
            }
        }
    }


    /**
     * Match statuses from Site to MoySkald
     */
    public static function display_wooms_order_statuses_from_moysklad()
    {

        $option_key = 'wooms_order_statuses_from_moysklad';

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
                '<p><input type="text" name="%s[%s]" value="%s" /> > %s (%s)</p>',
                $option_key,
                $status_key,
                $option_value[$status_key],
                $status_name,
                $status_key
            );
        }
    }


    /**
     * Add endpoint /wp-json/wooms/v1/order-update/
     */
    public static function add_route()
    {
        register_rest_route('wooms/v1', '/order-update/', array(
            'methods'  => \WP_REST_Server::EDITABLE,
            'callback' => array(__CLASS__, 'get_data_order_from_moysklad'),
            'permission_callback' => '__return_true',
        ));
    }


    /**
     * Get data from MoySkald and start update order
     *
     * @param $data_request
     *
     * @return void|WP_REST_Response
     */
    public static function get_data_order_from_moysklad($data_request)
    {
        self::set_state('is_wait', 0);

        try {
            $body = $data_request->get_body();
            $data = json_decode($body, true);

            do_action(
                'wooms_logger',
                __CLASS__,
                'Прилетел веб-хук',
                $data
            );

            if (!self::is_enable()) {
                return;
            }

            if (empty($data["events"][0]["meta"]["href"])) {
                return;
            }

            $url = $data["events"][0]["meta"]["href"];

            $data_order = wooms_request($url);
            if (empty($data_order['id'])) {
                return;
            }

            $order_uuid = $data_order['id'];

            $args   = array(
                'numberposts' => 1,
                'post_type'   => 'shop_order',
                'post_status' => 'any',
                'meta_key'    => 'wooms_id',
                'meta_value'  => $order_uuid,
            );
            $orders = get_posts($args);


            if (empty($orders[0]->ID)) {
                return false;
            }
            $order_id = $orders[0]->ID;

            //Если стоит блокировка обновления Заказа, то выходим (защита от состояния гонки)
            if (get_post_meta($order_id, 'lock_callback_update', true)) {
                delete_post_meta($order_id, 'lock_callback_update');
                return;
            }

            update_post_meta($order_id, self::$hook_order_update_from_moysklad, 1);

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Заказ %s поставлен в очередь на обновление из МойСклад', $order_id)
            );

            do_action('wooms_order_update_from_moysklad_action', $order_id, $data_order, $order_uuid);

            $result     = true;
            if ($result) {
                $response = new \WP_REST_Response(array('success', 'Data received successfully'));
                $response->set_status(200);
                return $response;
            } else {
                throw new \Exception("Заказ не обновился");
            }
        } catch (Exception $e) {
            $response = new \WP_REST_Response(array('fail', $e->getMessage()));
            $response->set_status(500);
            return $response;
        }
    }

    /**
     * is_enable
     */
    public static function is_enable()
    {
        if (get_option('wooms_enable_webhooks')) {
            return true;
        }

        return false;
    }

    /**
     * Setting
     */
    public static function add_settings()
    {

        register_setting('mss-settings', 'wooms_enable_webhooks');
        add_settings_field(
            $id = 'wooms_enable_webhooks',
            $title = 'Передатчик Статуса и данных по Заказу:<br/> МойСклад > Сайт',
            $callback = array(__CLASS__, 'display_wooms_enable_webhooks'),
            $page = 'mss-settings',
            $section = 'wooms_section_orders'
        );

        if (get_option('wooms_enable_webhooks')) {
            register_setting('mss-settings', 'wooms_order_statuses_from_moysklad');
            add_settings_field(
                $id = 'wooms_order_statuses_from_moysklad',
                $title = 'Связь статусов:<br/> от МойСклад на Сайт',
                $callback = array(__CLASS__, 'display_wooms_order_statuses_from_moysklad',),
                $page = 'mss-settings',
                $section = 'wooms_section_orders'
            );
        }
    }
}

OrderUpdateFromMoySklad::init();
