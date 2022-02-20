<?php
namespace WooMS;

defined( 'ABSPATH' ) || exit;

/**
 * Additional notes for send order
 */
class OrderNotes
{
    public static function init(){
        add_filter('wooms_order_sender_notes', [__CLASS__, 'add_order_notes'], 10, 2);

        add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 50 );
    }

    /**
     * add_order_notes
     * 
     * @param \WC_Order $order
     */
    public static function add_order_notes($notes, $order)
    {
        if(!get_option('wooms_order_additional_notes_enable')){
            return $notes;
        }

        if ($shipment_method = $order->get_shipping_method()) {
            $notes['shipment_method'] = sprintf("Метод доставки: %s", $shipment_method);
        }

        if($formatted_shipping_address = $order->get_formatted_shipping_address()){
            $formatted_shipping_address = str_replace('<br/>', ', ', $formatted_shipping_address);
            $notes['formatted_shipping_address'] = sprintf("Указаны дополнительные данные для доставки: %s%s", PHP_EOL, $formatted_shipping_address);
        }

        if ($phone_number = $order->get_billing_phone()) {
            $notes['phone_number'] = sprintf("Телефон: %s", $phone_number);
        }

        if ($payment_method_title = $order->get_payment_method_title()) {
            $notes['pay_method'] = sprintf("Метод оплаты: %s", $payment_method_title);
            if ($transaction_id = $order->get_transaction_id()) {
                $notes['pay_transaction'] = sprintf("Транзакция №%s", $transaction_id);
            }
        }

        return $notes;
    }

    /**
     * Settings UI
     */
    public static function add_settings() {

        $order_additional_notes = 'wooms_order_additional_notes_enable';
        register_setting( 'mss-settings', $order_additional_notes );
        add_settings_field(
            $id = $order_additional_notes,
            $title = 'Дополнительные данные в примечании к Заказу',
            $callback = function($args){
                printf( '<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked( 1, $args['value'], false ) );
                printf(
                    '<p><small>%s</small></p>',
                    'Включите эту опцию, если нужно передавать дополнительные заметки в Заказе: адрес доставки, телефон и т д'
                );
            },
            $page = 'mss-settings',
            $section = 'wooms_section_orders',
            $args = [
                'key' => $order_additional_notes,
                'value' => get_option($order_additional_notes)
            ]
        );
    }
}

OrderNotes::init();