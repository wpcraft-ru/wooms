<?php

namespace WooMS;

defined('ABSPATH') || exit;

/**
 * Send orders to MoySklad
 */
class Orders
{

    /**
     * Hookd and key for ActionSheduler
     *
     * @var string
     */
    public static $walker_hook_name = 'wooms_send_order_in_moysklad';

    /**
     * The init
     */
    public static function init()
    {

        // add_action('init', function () {
        //   if (!isset($_GET['dd'])) {
        //     return;
        //   }

        //   echo '<pre>';

        //   $check = self::order_update_to_moysklad(26455);

        //   die(0);
        // });


        add_action('wooms_send_order_in_moysklad', function($args){

            if(empty($args['post_id'])){
                return;
            }

            if( ! $order = wc_get_order($args['post_id'])){
                return;
            }

            self::order_update_to_moysklad($order->get_id());
        });

        add_action('wooms_check_orders_for_update_to_moysklad', array(__CLASS__, 'batch_hadler'));

        add_action('init', array(__CLASS__, 'add_schedule_hook'));

        add_action('save_post_shop_order', array(__CLASS__, 'order_update_by_hook'));

        add_action('woocommerce_new_order', array(__CLASS__, 'auto_add_order_for_send'), 20, 2);


        add_filter('wooms_order_data', [__CLASS__, 'add_currency'], 11, 3);
        add_filter('wooms_order_data', [__CLASS__, 'add_positions'], 11, 3);
        // add_filter('wooms_order_send_data', [__CLASS__, 'add_positions'], 10, 3);
        add_filter('wooms_order_data', [__CLASS__, 'add_moment'], 11, 3);
        add_filter('wooms_order_data', [__CLASS__, 'add_client_as_agent'], 22, 3);
        add_filter('wooms_order_data', [__CLASS__, 'add_agent_by_phone'], 22, 3);
        add_filter('wooms_order_data', [__CLASS__, 'add_agent_as_new'], 55, 3);
        add_filter('wooms_order_data', [__CLASS__, 'agent_update_data'], 55, 3);

        add_action('add_meta_boxes', function () {
            add_meta_box('metabox_order', 'МойСклад', array(__CLASS__, 'display_metabox'), 'shop_order', 'side', 'low');
        });

        add_action('wooms_order_metabox_controls', array(__CLASS__, 'add_controle_for_sync'));

        add_action('admin_init', array(__CLASS__, 'add_settings'), 40);

        add_action( 'woocommerce_order_item_meta_start', function () {

            add_filter( 'woocommerce_order_item_get_formatted_meta_data',  [__CLASS__, 'wooms_id_remove_from_order_data'], 10, 2 );
        });

    }


    /**
     * Removal of wooms_id from order data in customer email and order-received / view-order pages
    */
    public static function wooms_id_remove_from_order_data( $attr, $data ) {

        foreach( $attr as $key => $att ) {
    
            if ( $att->key == 'wooms_id' ) {
                unset( $attr[$key] );
            }
        }
    
        return $attr;
    }

    /**
     * order_update_to_moysklad
     */
    public static function order_update_to_moysklad($order_id, $order = false)
    {

        if (empty($order)) {
            $order    = wc_get_order($order_id);
        }

        $wooms_id = $order->get_meta('wooms_id', true);

        if (!$order->get_items()) {
            return false;
        }

        $skip = apply_filters('wooms_skip_order_update', false, $order);
        if ($skip) {

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Пропуск Заказ %s - обновлен', $order_id)
            );

            delete_post_meta($order_id, 'wooms_order_sync');

            return false;
        }

        /**
         * Send order if no wooms_id
         */
        if (empty($wooms_id)) {

            $check = self::send_order($order_id, $order);
            if ($check) {

                $order = wc_get_order($order_id);
                $order->delete_meta_data('wooms_order_sync');
                $order->save();

                return true;
            } else {
                return false;
            }
        }

        $url    = 'https://online.moysklad.ru/api/remap/1.2/entity/customerorder/' . $wooms_id;

        $data = wooms_request($url);

        /**
         * Preparation the data for update an existing order
         */
        $data["name"] = self::get_data_name($order_id);

        /**
         * New order description (customer note)
         */
		$data["description"] = self::get_order_note($order);

        /**
         * for send and update
         */
        $data = apply_filters('wooms_order_data', $data, $order_id);

        /**
         * only for update exist order
         */
        $data = apply_filters('wooms_order_update_data', $data, $order_id);

        /* В заказе может быть только доставка и тогда не ясно, что остальные позиции не встали */
        if (!isset($data['positions']) || empty($data['positions'])) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('При передаче Заказа %s - нет позиций', $order_id)
            );
        }


        $result = wooms_request($url, $data, 'PUT');

        if (empty($result["id"])) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('При передаче Заказа %s - данные не переданы', $order_id),
                $result
            );
        } else {

            $order = apply_filters('wooms_order_send_save', $order, $data);

            $data_json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $order->update_meta_data('wooms_data', $data_json);

            $order = apply_filters('wooms_order_update', $order, $result);

            $order->update_meta_data('wooms_send_timestamp', date("Y-m-d H:i:s"));
            $order->delete_meta_data('wooms_order_sync');

            $order->save();

            do_action(
                'wooms_logger',
                __CLASS__,
                sprintf('Заказ %s - обновлен', $order_id),
                $data
            );

        }

	    return true;
    }


    /**
     * add_currency
     *
     * @issue https://github.com/wpcraft-ru/wooms/issues/189
     */
    public static function add_currency($data_order, $order_id, $order = [])
    {
        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        $currency_code = $order->get_currency();

        if ('RUB' == $currency_code) {
            return $data_order;
        }

        if (!$data = get_transient('wooms_currency_api')) {
            $url = 'https://online.moysklad.ru/api/remap/1.2/entity/currency/';
            $data = wooms_request($url);
            set_transient('wooms_currency_api', $data, HOUR_IN_SECONDS);
        }

        if (empty($data['rows'])) {
            return $data_order;
        }

        $meta = '';
        foreach ($data['rows'] as $key => $row) {
            if ($currency_code == $row['isoCode']) {
                $meta = $row['meta'];
            }
        }

        if (empty($meta)) {
            return $data_order;
        }

        $data_order['rate'] = [
            'currency' => [
                'meta' => $meta,
            ],
        ];

        return $data_order;
    }

    /**
     * Auto add meta for send order by schedule
     */
    public static function auto_add_order_for_send($order_id, $order)
    {

        if (!self::is_enable()) {
            return;
        }


        update_post_meta($order_id, 'wooms_order_sync', 1);

        //issue https://github.com/wpcraft-ru/wooms/issues/330
        if (!get_option('wooms_get_number_async_enable')) {
            self::order_update_to_moysklad($order_id, $order);
        }

    }


    /**
     * order - add task for sync if enable
     */
    public static function order_update_by_hook($post_id)
    {

        // remove_action('woocommerce_update_order', [__CLASS__, 'order_update']);
        $order_id = $post_id;
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order->get_items()) {
            return;
        }

        if (self::is_enable()) {
            update_post_meta($order_id, 'wooms_order_sync', 1);

            self::order_update_to_moysklad($order_id);
            delete_transient('wooms_order_timestamp_end');

            return;
        }

        //check for manual sync
        if (!isset($_POST['wooms_order_sync'])) {
            return;
        }

        if (empty($_POST['wooms_order_sync'])) {
            delete_post_meta($post_id, 'wooms_order_sync');
        } else {
            update_post_meta($post_id, 'wooms_order_sync', 1);
            self::order_update_to_moysklad($order_id);
        }

        delete_transient('wooms_order_timestamp_end');

        // add_action('woocommerce_update_order', [__CLASS__, 'order_update']);
    }


    /**
     * Setup schedule
     *
     * @return mixed
     */
    public static function add_schedule_hook($force = false)
    {
        if (self::is_wait()) {
            as_unschedule_all_actions('wooms_check_orders_for_update_to_moysklad');
            return;
        }

        if ( false === as_next_scheduled_action( 'wooms_check_orders_for_update_to_moysklad' ) ) {
            as_schedule_recurring_action( time (), MINUTE_IN_SECONDS, 'wooms_check_orders_for_update_to_moysklad', [], 'WooMS' );
        }
    }

    /**
     * Checking if schedule can be created or not
     *
     * @return void
     */
    public static function is_wait()
    {
        $is_wait = true;
        if (get_transient('wooms_order_timestamp_end')) {
            $is_wait = true;
        } else {
            $is_wait = false;
        }

        return apply_filters('wooms_order_sender_is_wait', $is_wait);
    }


    /**
     * Main walker for send orders
     */
    public static function batch_hadler()
    {

        $args = array(
            'numberposts'  => apply_filters('wooms_orders_number', 5),
            'post_type'    => 'shop_order',
            'post_status'  => 'any',
            'meta_key'     => 'wooms_order_sync',
            'meta_compare' => 'EXISTS',
        );

        $orders = get_posts($args);

        if (empty($orders)) {
            set_transient('wooms_order_timestamp_end', time());
            return false;
        }

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Старт очереди отправки заказов - %s', date("Y-m-d H:i:s"))
        );

        $result_list = [];
        foreach ($orders as $order) {

            self::add_task_order_update($order->ID);
          
        }

        self::add_schedule_hook(true);
    }

    public static function add_task_order_update($order_id){
        $args = [
            'data' => [
                'post_id' => $order_id,
                'time' => time(),
            ]
        ];

        as_enqueue_async_action( self::$walker_hook_name, $args, 'WooMS' );
    }


    /**
     * Send order to moysklad.ru and mark the order as sended
     */
    public static function send_order($order_id, $order = [])
    {

        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        $data              = array(
            "name" => self::get_data_name($order_id),
        );

        if ($meta_organization = self::get_data_organization()) {
            $data["organization"] = $meta_organization;
        } else {
            return false;
        }

        $data["description"] = self::get_order_note($order);

        if (empty($data)) {
            $order->update_meta_data('wooms_send_timestamp', date("Y-m-d H:i:s"));

            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка подготовки данных по заказу %s', $order_id)
            );

            return false;
        }

        /**
         * only for send order first time
         */
        $data = apply_filters('wooms_order_send_data', $data, $order_id, $order);

        /**
         * for send and update
         */
        $data = apply_filters('wooms_order_data', $data, $order_id, $order);

        $url = 'https://online.moysklad.ru/api/remap/1.2/entity/customerorder';

        $result = wooms_request($url, $data, 'POST');

        if (empty($result['id']) || !isset($result['id']) || isset($result['errors'])) {
            update_post_meta($order_id, 'wooms_send_timestamp', date("Y-m-d H:i:s"));
            // $errors = "\n\r" . 'Код ошибки:' . $result['errors'][0]['code'] . "\n\r";
            // $errors .= 'Параметр:' . $result['errors'][0]['parameter'] . "\n\r";
            // $errors .= $result['errors'][0]['error'];

            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Ошибка передачи заказа %s', $order_id),
                $result
            );

            return false;
        }

        $order->update_meta_data('wooms_id', $result['id']);
        $order->delete_meta_data('wooms_order_sync');

        /**
         * issue https://github.com/wpcraft-ru/wooms/issues/319
         */
        $order = apply_filters('wooms_order_update', $order, $result);

        $order->save();

        if (empty($result['positions'])) {
            $positions_count = 0;
        } else {
            $positions_count = count($result['positions']);
        }

        if ($positions_count == 0) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('В заказе %s передано 0 позиций', $order_id)
            );
        }

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Заказ %s - отправлен (позиций: %s)', $order_id, $positions_count),
            $data
        );

        return true;
    }


    /**
     * Get data name for send MoySklad
     */
    public static function get_data_name($order_id)
    {
        $prefix_postfix_name  = get_option('wooms_orders_send_prefix_postfix');
        $prefix_postfix_check = get_option('wooms_orders_send_check_prefix_postfix');
        if ($prefix_postfix_name) {
            if ('prefix' == $prefix_postfix_check) {
                $name_order = $prefix_postfix_name . '-' . $order_id;
            } elseif ('postfix' == $prefix_postfix_check) {
                $name_order = $order_id . '-' . $prefix_postfix_name;
            }
        } else {
            $name_order = $order_id;
        }

        return apply_filters('wooms_order_name', (string) $name_order);
    }

    /**
     * add positions to order
     */
    public static function add_positions($data_order, $order_id, $order = [])
    {

        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        //issue https://github.com/wpcraft-ru/wooms/issues/344
        // if ($order->meta_exists('wooms_order_task_update')) {
        //     return $data_order;
        // }

        $items = $order->get_items();

        if (empty($items)) {
            return $data_order;
        }

        $data = array();
        foreach ($items as $key => $item) {

            if ($item['variation_id'] != 0) {
                $product_id   = $item['variation_id'];
                $product_type = 'variant';
            } else {
                $product_id   = $item["product_id"];
                $product_type = 'product';
            }

            $product_type = self::get_product_type($item);

            $uuid = get_post_meta($product_id, 'wooms_id', true);

            /* Если wooms_id у товара отсутствует,
            * позиция не может быть передана (из существующей логики)
            * поэтому, чтобы обратить внимание менеджера склада,
            * что заказ не полный (при наличии других товаров в заказе)
            * следует передать пустой заказ, иначе отправка зацикливается:
            * https://github.com/wpcraft-ru/wooms/issues/408
            */
            if (!$uuid) {

                return $data_order;
            }

            $item->update_meta_data('wooms_id', $uuid);
            $item->save();

            $price    = $item->get_total();
            $quantity = $item->get_quantity();
            if (empty(get_option('wooms_orders_send_reserved'))) {
                $reserve_qty = $quantity;
            } else {
                $reserve_qty = 0;
            }

            $position = [
                'quantity'   => $quantity,
                'price'      => ($price / $quantity) * 100,
                'discount'   => 0,
                'vat'        => 0,
                'assortment' => array(
                    'meta' => array(
                        "href"      => "https://online.moysklad.ru/api/remap/1.2/entity/{$product_type}/" . $uuid,
                        "type"      => "{$product_type}",
                        "mediaType" => "application/json",
                    ),
                ),
                'reserve'    => $reserve_qty,
            ];

            $position = apply_filters('wooms_order_sender_position', $position, $product_id);

            $data[] = $position;
        }

        if (empty($data)) {
            return $data_order;
        }

        $data_order['positions'] = $data;

        return $data_order;
    }

    public static function get_product_type($item)
    {
        if ($item['variation_id'] != 0) {
            $product_id   = $item['variation_id'];
            $product_type = 'variant';
        } else {
            $product_id   = $item["product_id"];
            $product_type = 'product';
        }

        if ($product = wc_get_product($product_id)) {
            if ($product->is_virtual()) {
                $product_type = 'service';
            }
        }

        return $product_type;
    }

    /**
     * Get meta for organization
     */
    public static function get_data_organization()
    {
        $url  = 'https://online.moysklad.ru/api/remap/1.2/entity/organization';
        $data = wooms_request($url);

        if (empty($data['rows'][0]['meta'])) {
            do_action(
                'wooms_logger_error',
                __CLASS__,
                'Нет юр лица в базе для отправки Заказа. Добавьте юр лицо в МойСклад.'
            );

            return false;
        }

        $meta = '';
        if ($org_name_site = get_option('wooms_org_name')) {
            foreach ($data['rows'] as $row) {
                if ($org_name_site == $row['name']) {
                    $meta = $row["meta"];
                }
            }

            if (empty($meta)) {
                do_action(
                    'wooms_logger_error',
                    __CLASS__,
                    sprintf('Для указанного наименования юр лица не найдены данные в МойСклад: %s', $org_name_site)
                );
            }
        }

        if (empty($meta)) {
            $meta = $data['rows'][0]['meta'];
        }

        return array('meta' => $meta);
    }

    /**
     * agent_update_data
     */
    public static function agent_update_data($data_order, $order_id, $order = false)
    {
        if (empty($data_order['agent']['meta']['href'])) {
            return $data_order;
        }

        if(empty(get_option('wooms_clients_update_enable'))){
            return $data_order;
        }

        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        $name = self::get_data_order_name($order_id);

        if (empty($name)) {
            $name = 'Клиент по заказу ID' . $order->get_order_number();
        }

        $data = array(
            "name"          => $name,
            "companyType"   => self::get_data_order_company_type($order_id),
            "legalAddress"  => self::get_data_order_address($order_id),
            "actualAddress" => self::get_data_order_address($order_id),
            "phone"         => self::get_data_order_phone($order_id, $order),
            "email"         => $order->get_billing_email()
        );

        $url    = $data_order['agent']['meta']['href'];
        $result = wooms_request($url, $data, 'PUT');

        return $data_order;
    }

    /**
     * add_agent_by_phone
     */
    public static function add_agent_by_phone($data_order, $order_id, $order = false)
    {
        if (!empty($data_order['agent'])) {
            return $data_order;
        }

        if (empty($order)) {
            $order = wc_get_order($order_id);
        }


        //main method - by email
        if($order->get_billing_email()){
            return $data_order;
        }

        if (!$phone = $order->get_billing_phone()) {
            return $data_order;
        }

        if (!empty($phone)) {

            $phone = self::phone_prepare($phone);
            $url_search_agent = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty?search=' . $phone;

            $data_agents      = wooms_request($url_search_agent);
            if (isset($data_agents['rows'][0]['phone'])) {
                $agent_meta = $data_agents['rows'][0];
            }
        }

        if (!empty($agent_meta)) {
            $data_order['agent']['meta'] = $agent_meta['meta'];
        }

        return $data_order;
    }

    public static function phone_prepare($phone)
    {
        $phone = preg_replace('![^0-9]+!', '', $phone);

        return $phone;
    }

    public static function add_agent_as_new($data_order, $order_id, $order = false)
    {
        if (!empty($data_order['agent'])) {
            return $data_order;
        }

        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        $name = self::get_data_order_name($order_id);

        if (empty($name)) {
            $name = 'Клиент по заказу ID: ' . $order->get_order_number();
        }

        $data = array(
            "name"          => $name,
            "companyType"   => self::get_data_order_company_type($order_id),
            "legalAddress"  => self::get_data_order_address($order_id),
            "actualAddress" => self::get_data_order_address($order_id),
            "phone"         => self::get_data_order_phone($order_id, $order),
            "email"         => $order->get_billing_email()
        );

        $url    = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty';
        $result = wooms_request($url, $data, 'POST');

        if (empty($result["meta"])) {
            return $data_order;
        }

        if (isset($result['id'])) {
            self::save_uuid_agent_to_order($result['id'], $order_id);
        }

        if (!empty($result['meta'])) {
            $data_order['agent']['meta'] = $result['meta'];
        }

        do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Добавлен новый клиент в МойСклад по заказу id %s', $order_id),
            $data
        );

        return $data_order;
    }

    /**
     * add_client_as_agent
     */
    public static function add_client_as_agent($data_order, $order_id, $order = false)
    {
        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        $user  = $order->get_user();
        $email = '';

        $data = [
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
        ];

        $agent_meta = [];

        if (!empty($data['email'])) {
            $url_search_agent = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty?filter=email=' . $data['email'];
            $data_agents      = wooms_request($url_search_agent);
            if (isset($data_agents['rows'][0]['email'])) {
                $agent_meta = $data_agents['rows'][0];
            }
        }

        if (!empty($agent_meta)) {
            $data_order['agent']['meta'] = $agent_meta['meta'];
        }

        return $data_order;
    }


    /**
     * check_agent_uuid
     *
     * return $agent_uuid if isset
     * if no agent - return false
     */
    public static function check_agent_uuid($agent_uuid)
    {
        if (empty($agent_uuid)) {
            return false;
        }

        $url    = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty/' . $agent_uuid;
        $result = wooms_request($url);
        if (empty($result['id'])) {
            return false;
        }

        return $result['id'];
    }

    /**
     * save uuid agent to order
     */
    public static function save_uuid_agent_to_order($uuid = '', $order_id = '')
    {
        if (empty($order_id) || empty($uuid)) {
            return false;
        }

        $order = wc_get_order($order_id);
        $order->update_meta_data('agent_uuid', $uuid);
        $order->save();
    }

    /**
     * Get name counterparty from order
     *
     * @param $order_id
     *
     * @return string
     */
    public static function get_data_order_name($order_id)
    {
        $order = wc_get_order($order_id);

        $name = 'Клиент по заказу №' . $order->get_order_number();

        $data = [
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
        ];

        if ($data['billing_first_name']) {
            $name = $data['billing_first_name'];
        }

        if ($data['billing_last_name']) {
            if ($data['billing_first_name']) {
                $name = $name . ' ' . $data['billing_last_name'];
            } else {
                $name = $data['billing_last_name'];
            }
        }

        if ($billing_company = $order->get_billing_company()) {
            $name = $name . sprintf(' (%s)', $billing_company);
        }

        return $name;
    }

    /**
     * Get company type counterparty from order
     *
     * @param $order_id
     *
     * @return string
     */
    public static function get_data_order_company_type($order_id)
    {
        $order = wc_get_order($order_id);
        if (!empty($order->get_billing_company())) {
            $company_type = "legal";
        } else {
            $company_type = "individual";
        }

        return $company_type;
    }

    /**
     * Get address counterparty from order
     *
     * @param $order_id
     *
     * @return string
     */
    public static function get_data_order_address($order_id)
    {
        $order   = wc_get_order($order_id);
        $address = '';

        if ($order->get_billing_postcode()) {
            $address .= $order->get_billing_postcode();
        }

        if ($order->get_billing_state()) {
            $address .= ', ' . $order->get_billing_state();
        }

        if ($order->get_billing_city()) {
            $address .= ', ' . $order->get_billing_city();
        }

        if ($order->get_billing_address_1() || $order->get_billing_address_2()) {
            $address .= ', ' . $order->get_billing_address_1();
            if (!empty($order->get_billing_address_2())) {
                $address .= ', ' . $order->get_billing_address_2();
            }
        }

        return $address;
    }

    /**
     * Get phone counterparty from order
     *
     * @param $order_id
     *
     * @return string
     */
    public static function get_data_order_phone($order_id, $order = false)
    {
        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        if ($order->get_billing_phone()) {
            $phone = preg_replace("/[^0-9]/", '', $order->get_billing_phone());
        } else {
            $phone = '';
        }

        return $phone;
    }

    /**
     * Get meta by email agent
     *
     * @param string $email
     *
     * @return bool
     */
    public static function get_agent_meta_by_email($email = '')
    {
        $url_search_agent = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty?filter=email=' . $email;
        $data_agents      = wooms_request($url_search_agent);
        if (empty($data_agents['rows'][0]['meta'])) {
            return false;
        }

        return $data_agents['rows'][0]['id'];
    }

    /**
     * Get data customerorder date created for send MoySklad
     *
     * @param $order_id
     *
     * @return string
     */
    public static function add_moment($data_order, $order_id, $order = [])
    {
        if (empty($order)) {
            $order = wc_get_order($order_id);
        }

        if (empty($order)) {

            do_action(
                'wooms_logger_error',
                __CLASS__,
                sprintf('Заказ %s - ордер пустой при попытке добавить время', $order_id)
            );

            return $data_order;
        }

        if($date = $order->get_date_created()){
            $timezone = new \DateTimeZone("Europe/Moscow");
            $date = $date->setTimeZone($timezone);
            $date = $date->date('Y-m-d H:i:s');
            $data_order['moment'] = $date;
        }

        return $data_order;
    }

    /**
     * Get data customerorder description created for send MoySklad
     *
     * @param \WC_Order $order
     *
     * @return string
     */
    public static function get_order_note($order)
    {
        // $order         = wc_get_order($order_id);

        $customer_notes = [];
        $customer_notes['order_url'] = sprintf('Посмотреть заказ на сайте: %s', $order->get_edit_order_url());

        if ($order_comment = $order->get_customer_note()) {
            $customer_notes['comment'] = 'Примечание Клиента к Заказу:' . PHP_EOL . $order_comment;
        }

        $customer_notes = apply_filters('wooms_order_sender_notes', $customer_notes, $order);
        $customer_notes = implode(PHP_EOL . '---' . PHP_EOL, $customer_notes);

        return $customer_notes;
    }


    /**
     * check is enable
     *
     * @return boolean
     */
    public static function is_enable()
    {
        if (get_option('wooms_orders_sender_enable', false)) {
            return true;
        }

        return false;
    }


    /**
     * Setting
     */
    public static function add_settings()
    {
        add_settings_section('wooms_section_orders', 'Заказы - передача в МойСклад', '', 'mss-settings');

        $orders_sender_enable_key = 'wooms_orders_sender_enable';
        register_setting('mss-settings', $orders_sender_enable_key);
        add_settings_field(
            $id = $orders_sender_enable_key,
            $title = 'Автоматически передавать заказы в МойСклад',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );
                printf('<p>%s</p>', 'Если включена, то заказы будут передаваться автоматически по мере создания и обновления. Если отключена, то можно вручную отправить Заказ в МойСклад');
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $orders_sender_enable_key,
                'value' => get_option($orders_sender_enable_key),
            ]
        );

        register_setting('mss-settings', 'wooms_orders_send_prefix_postfix');
        add_settings_field(
            $id = 'wooms_orders_send_prefix_postfix',
            $title = 'Префикс или постфикс к номеру заказа',
            $callback = array(__CLASS__, 'display_wooms_orders_send_prefix_postfix'),
            $page = 'mss-settings',
            $section = 'wooms_section_orders'
        );

        register_setting('mss-settings', 'wooms_orders_send_check_prefix_postfix');
        add_settings_field(
            $id = 'wooms_orders_send_check_prefix_postfix',
            $title = 'Использовать как префикс или как постфикс',
            $callback = array(__CLASS__, 'display_wooms_orders_send_check_prefix_postfix'),
            $page = 'mss-settings',
            $section = 'wooms_section_orders'
        );

        self::register_setting_orders_send_reserved();
        self::register_setting_find_client_by_phone();
        self::register_setting_select_org_name();
        self::register_setting_clients_update_enable();
    }

    /**
     * register_setting_clients_update_enable
     */
    public static function register_setting_clients_update_enable()
    {
        $option_name = 'wooms_clients_update_enable';
        register_setting('mss-settings', $option_name);
        add_settings_field(
            $id = $option_name,
            $title = 'Обновление клиентов',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s /> %s',
                    $args['key'],
                    checked(1, $args['value'], false),
                    '<small>При включении данной опции, при обновлении заказов - будут обновляться клиенты в МойСклад</small>'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $option_name,
                'value' => get_option($option_name),
            ]
        );
    }


    /**
     * register_setting_orders_send_reserved
     */
    public static function register_setting_orders_send_reserved()
    {
        $option_name = 'wooms_orders_send_reserved';
        register_setting('mss-settings', $option_name);
        add_settings_field(
            $id = $option_name,
            $title = 'Выключить резервирование товаров',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s /> %s',
                    $args['key'],
                    checked(1, $args['value'], false),
                    '<small>При включении данной настройки, резеревирование товаров на складе будет отключено</small>'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $option_name,
                'value' => get_option($option_name),
            ]
        );
    }


    /**
     * register_setting_select_org_name
     */
    public static function register_setting_select_org_name()
    {
        $option_name = 'wooms_org_name';
        register_setting('mss-settings', $option_name);
        add_settings_field(
            $id = $option_name,
            $title = 'Наименование юр лица для Заказов',
            $callback = function ($args) {
                printf('<input type="text" name="%s" value="%s" />', $args['key'], $args['value']);
                printf(
                    '<p><small>%s</small></p>',
                    'Тут можно указать краткое наименование юр лица из МойСклад. Если пусто, то берется первое из списка. Иначе будет выбор указанного юр лица.'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $option_name,
                'value' => get_option($option_name),
            ]
        );
    }

    /**
     * register_setting_find_client_by_phone
     */
    public static function register_setting_find_client_by_phone()
    {
        $option_name = 'wooms_orders_find_client_by_phone';
        register_setting('mss-settings', $option_name);
        add_settings_field(
            $id = $option_name,
            $title = 'Пытаться искать клиента по номеру телефона',
            $callback = function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    $args['key'],
                    checked(1, $args['value'], $echo = false)
                );
                printf(
                    '<p>%s</p>',
                    'Если включена, то плагин будет пытаться связать Заказ с Клиентом по номеру телефона, если по email не получилось'
                );
                printf(
                    '<p>%s</p>',
                    'Подробнее: <a href="https://github.com/wpcraft-ru/wooms/issues/146" target="_blank">https://github.com/wpcraft-ru/wooms/issues/146</a>'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $option_name,
                'value' => get_option($option_name),
            ]
        );
    }


    /**
     * display_wooms_orders_send_prefix_postfix
     *
     * XXX придумать лучше способ авто простановки префикса https://github.com/wpcraft-ru/wooms/issues/166
     */
    public static function display_wooms_orders_send_prefix_postfix()
    {

        $option_key = 'wooms_orders_send_prefix_postfix';
        $value = get_option($option_key);

        printf('<input type="text" name="%s" value="%s" />', $option_key, $value);
        echo '<p><strong>Рекомендуем использовать эту опцию, чтобы исключить ошибки в передаче Заказов</strong></p>';
        echo '<p><small>Укажите тут уникальную приставку к номеру заказа. Например - S</small></p>';
    }

    /**
     * display_wooms_orders_send_check_prefix_postfix
     */
    public static function display_wooms_orders_send_check_prefix_postfix()
    {
        $selected_prefix_postfix = get_option('wooms_orders_send_check_prefix_postfix');
?>
        <select class="check_prefix_postfix" name="wooms_orders_send_check_prefix_postfix">
            <?php
            printf(
                '<option value="%s" %s>%s</option>',
                'prefix',
                selected('prefix', $selected_prefix_postfix, false),
                'перед номером заказа'
            );
            printf(
                '<option value="%s" %s>%s</option>',
                'postfix',
                selected('postfix', $selected_prefix_postfix, false),
                'после номера заказа'
            );
            ?>
        </select>
<?php
        echo '<p><small>Выберите как выводить уникальную приставку: перед номером заказа (префикс) или после номера заказа (постфикс)</small></p>';
    }

    /**
     * display_wooms_orders_send_reserved
     */
    public static function display_wooms_orders_send_reserved()
    {
        $option = 'wooms_orders_send_reserved';
        $desc   = '<small>При включении данной настройки, резеревирование товаров на складе будет отключено</small>';
        printf(
            '<input type="checkbox" name="%s" value="1" %s /> %s',
            $option,
            checked(1, get_option($option), false),
            $desc
        );
    }

    /**
     * add_controle_for_sync
     * 
     * issue https://github.com/wpcraft-ru/wooms/issues/316
     */
    public static function add_controle_for_sync()
    {
        if (self::is_enable()) {
            return;
        }

        $post    = get_post();

        $need_update = get_post_meta($post->ID, 'wooms_order_sync', true);
        echo '<hr/>';
        printf(
            '
            <input id="wooms-order-sync" type="checkbox" name="wooms_order_sync" %s>
            <label for="wooms-order-sync">%s</label>
            ',
            checked($need_update, 1, false),
            'Синхронизировать'
        );
    }

    /**
     * Meta box in order
     */
    public static function display_metabox()
    {
        $post    = get_post();
        $data_id = get_post_meta($post->ID, 'wooms_id', true);
        if ($data_id) {
            $meta_data = sprintf('<div>ID заказа в МойСклад: <div><strong>%s</strong></div></div>', $data_id);
            $meta_data .= sprintf(
                '<p><a href="https://online.moysklad.ru/app/#customerorder/edit?id=%s" target="_blank">Посмотреть заказ в МойСклад</a></p>',
                $data_id
            );
        } else {
            $meta_data = 'Заказ не передан в МойСклад';
        }
        echo $meta_data;

        do_action('wooms_order_metabox_controls');
    }
}

Orders::init();
