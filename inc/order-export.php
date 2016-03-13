<?php

/**
 * Экспорт продуктов из сайта в МойСклад
 */

class MSSOrderExport {

  var $ajax_tag = 'mss_order_export'; //метка для генерации запроса AJAX

  function __construct()
  {
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));

    add_action('wp_ajax_' . $this->ajax_tag, array($this,'ajax_callback'));
    add_action('wp_ajax_nopriv_' . $this->ajax_tag, array($this,'ajax_callback'));
  }




  //Запуск обработки через AJAX
  function ajax_callback(){


    $offset = 0; //ставим позицию старта забора данных по умолчанию
    $numberposts = 1; //ставим число одновременно запрашиваемых данных для забора
    if(isset($_REQUEST['offset'])) $offset = $_REQUEST['offset']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

    //ставим отметку на 60 секунд по запуску обновления данных из первой итерации
    if($offset == 0)
      set_transient( 'mss_order_export_start', current_time('mysql'), 60);

    //получаем заказ по очереди
    $posts = get_posts(array(
      'post_type' => 'shop_order',
      'numberposts' => $numberposts,
      'offset' => $offset
    ));

    if($posts) {

      //Установить время начала работы итерации
      set_transient( 'mss_order_export_start_i', date('Y-m-d h:m:s'), 1 * HOUR_IN_SECONDS );

      //Цикл обработки продуктов
      foreach ($posts as $key => $value) {
        $post_id = $value->ID;

        //создаем xml для заказа
        $xml = $this->mss_create_xml_order($post_id);

        //записываем в мету заказа данные запроса
        update_post_meta($post_id, 'xml_request', $xml);

        if(isset($xml)) {

  				$result = send_xml_to_moysklad('CustomerOrder', $xml);


          //запись ответа в мету заказа
          update_post_meta($post_id, 'xml_respond', $result);


          //запись uuid в мету заказа
          $xml_respond  = new SimpleXMLElement($result);
          if(isset($xml_respond->uuid)) update_post_meta($post_id, 'uuid', (string)$xml_respond->uuid);


			  }



      }

      //снова вызов этой же функции через AJAX с отступом в указанное число записей
      $offset = $offset + $numberposts;
      $url = admin_url('admin-ajax.php?action=' . $this->ajax_tag . '&offset=' . $offset);
      set_transient( 'mss_order_export_url', $url, 1 * HOUR_IN_SECONDS );
      $url_result = wp_remote_get($url);
    } else {

      set_transient( 'mss_order_export_end', date('Y-m-d h:m:s'), 1 * HOUR_IN_SECONDS );
      wp_send_json_success("Данные в запросе закончились");

    }

    wp_send_json_success(current_time('mysql'));

  }



  /*
  * Генерация XML для передачи заказа в МойСклад
  * return xml
  */
  function mss_create_xml_order($id){


    //================================================
    // Секция сбора данных для XML из данных заказа
    $order = new WC_Order( $id );

    $sourceStoreUuid = get_option('mss_warehouse'); // идентификатор склада
    $sourceAgentUuid = get_option('mss_client'); //идентификатор контрагента-клиента
    $targetAgentUuid = get_option('mss_my_company'); //идентификатор организации
    $uuid = get_post_meta($id, 'uuid', true); //получаем uuid если он есть

    //$moment = "2011-06-27T06:27:00+04:00";
    $moment = date("Y-m-d\TH:i:s+04:00", strtotime($order->order_date));
    $name = "WP-".$id;

    $description_data = '';
    $description_data .= 'Дата заказа: ' . $order->order_date;
    $description_data .= '; Имя в заказе: ' . $order->billing_first_name;
    $description_data .= '; Телефон в заказе: ' . $order->billing_phone;
    $description_data .= '; Email в заказе: ' . $order->billing_email;

    $description_data .= '; Адрес доставки: ' . $order->shipping_address_1;

    //================================================
    //Начало формирования XML документа
    $dom = new DomDocument('1.0', 'UTF-8');

    //добавление корня - <customerOrder>
    $customerOrder = $dom->appendChild($dom->createElement('customerOrder'));

    //Установка атрибутов
    $customerOrder->setAttribute("vatIncluded", 'true');
    $customerOrder->setAttribute("applicable", 'true');
    $customerOrder->setAttribute("sourceStoreUuid", $sourceStoreUuid);
    $customerOrder->setAttribute("payerVat", "true");
    $customerOrder->setAttribute("sourceAgentUuid", $sourceAgentUuid);
    $customerOrder->setAttribute("targetAgentUuid", $targetAgentUuid);
    $customerOrder->setAttribute("moment", $moment);
    $customerOrder->setAttribute("name", $name);

    //Описание заказа
    $description = $customerOrder->appendChild($dom->createElement('description', $description_data));

    //uuid заказа если есть, то добавить, и вместо добавления, будет обновление
    if(isset($uuid)) {
      $uuid = $customerOrder->appendChild($dom->createElement('uuid', $uuid));
    }


    //получаем позиции заказа и обрабатываем их в xml формат
    $items = $order->get_items();
    foreach ( $items as $item ) {
        $product_id = $item['product_id'];
        $product = new WC_Product( $product_id );

        //определение параметров продукта в заказе
        $product_name = $item['name'];
        $goodUuid = get_post_meta($product_id, 'uuid', true);

        $product_variation_id = $item['variation_id'];
        $vat = "18";
        $quantity = $item['qty']; //"4.0";quantity

        $sumInCurrency = $product->price*100;
        $sum = $product->price*100;

        set_transient('test', '3:'.print_r($item, true), 777);

        //$discount = "0.0";

        //создание ветки XML по данным продукта
        $customerOrderPosition = $customerOrder->appendChild($dom->createElement('customerOrderPosition'));
        $customerOrderPosition->setAttribute("vat", $vat);
        $customerOrderPosition->setAttribute("goodUuid", $goodUuid);
        $customerOrderPosition->setAttribute("quantity", $quantity);
        //$customerOrderPosition->setAttribute("discount", $discount);

        //создание ветки цены в продукте
        $basePrice = $customerOrderPosition->appendChild($dom->createElement('basePrice'));
        $basePrice->setAttribute("sumInCurrency", $sumInCurrency);
        $basePrice->setAttribute("sum", $sum);

        //создание ветки резерва в продукте
        $reserve = $customerOrderPosition->appendChild($dom->createElement('reserve', '0.0'));
    }

    //генерация xml
    $dom->formatOutput = true; // установка атрибута formatOutput domDocument в значение true

     // save XML as string or file
    $xml = $dom->saveXML();

    return $xml;
  }

  //Интерфейс пользователя для запуска выгрузки продуктов
  function add_section_mss_tool_callback(){
    ?>
    <section id="mss-order-export-wrapper">
      <header>
        <h2>Экспор заказов с сайта в МойСклад</h2>
      </header>
      <div class="instruction">
        <p>Обработка экспортирует заказы из сайта в МойСклад</p>
      </div>
      <button id="mss-orders-export" class="button button-small">Запустить обработку</button>
      <br>
      <div class="status-wrapper hide-if-js">
        <strong>Статус работы: </strong>
        <ul>
          <li>Результат первой итерации: <span class="first-result">отсутствует</span></li>
          <li>Время запуска итерации: <span class="mss_order_export_start_i">ждем данные</span></li>
          <li>Ссылка запуска новой итерации: <span class="mss_order_export_url">ждем данные</span></li>
          <li>Время окончания обработки: <span class="mss_order_export_end">ждем данные</span></li>
          <li>Результат: <span class="mss_product_import_result-result">ждем данные</span></li>

          <li>Тест-сообщение: <span class="test-result">отсутствует</span></li>
        </ul>
      </div>

      <div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
              $('#mss-order-export-wrapper button').click(function () {

                $('#mss-order-export-wrapper .status-wrapper').show();

                var data = {
            			action: '<?php echo $this->ajax_tag ?>',
            		};

                $.getJSON(ajaxurl, data, function(response){

                  $('#mss-order-export-wrapper .first-result').text('успех = ' + response.success + ' (' + response.data + ')');

                });
              });
            });
        </script>
      </div>
    </section>
    <?php
  }
} $TheMSSOrderExport = new MSSOrderExport;




/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSSOrderExport {
    function __construct(){
      //Hearbeat update data
      add_action( 'admin_enqueue_scripts', array($this, 'heartbeat_enqueue'));
      add_filter( 'heartbeat_settings', array($this, 'heartbeat_frequency') );

      add_filter( 'heartbeat_send', array($this, 'mss_import_products_heartbeat'), 10, 2 );
    }



    //Получаем количество обработанных записей из кеша для вывода через Hearbeat
    function mss_import_products_heartbeat($data, $screen_id){

      //Проверка экрана, если не тот, значит прерываем работу
      if('tools_page_mss-tools' != $screen_id) return $data;

      //Если запущна экспорт, то помечаем данные, иначе отключаем передачу данных на клиента
      if(get_transient( 'mss_order_export_start')) {
        $data['mss_order_export_start'] = get_transient( 'mss_order_export_start');
      } else {
        return $data;
      }

      $data['test'] = get_transient( 'test');

      $data['mss_order_export_start_i'] = get_transient( 'mss_order_export_start_i');
      $data['mss_order_export_url'] = get_transient( 'mss_order_export_url');
      $data['mss_order_export_end'] = get_transient( 'mss_order_export_end');

      return $data;
    }



    //Прослушка данных Hearbeat и их вывод в лог
    function mss_ep_heartbeat_footer_js(){

      //Если это не страница инструментов МойСклад, то не выводим прослушку Hearbeat
      $cs = get_current_screen();
      if('tools_page_mss-tools' != $cs->id) return;

      ?>
          <script type="text/javascript" id="mss_import_product_hb">
          (function($){

              $(document).on( 'heartbeat-tick', function(e, data) {

                //console.log('Сообщение HB для импорта продуктов готово: ' + data['mss_product_import_start']);

                //Если есть данное о старте, то работаем, иначе прирываем работу
                if ( data['mss_order_export_start'] ){
                  $('#mss-order-export-wrapper .start-result').text(data['mss_order_export_start']);
                } else {
                  return; //если отметки о старте экспорта нет, то пропуск работы
                }

                //Добавляем тестовое сообщение. Используется для отладки
                $('#mss-order-export-wrapper .test-result').text(data['test']);
                $('#mss-order-export-wrapper .mss_order_export_start_i').text(data['mss_order_export_start_i']);
                $('#mss-order-export-wrapper .mss_order_export_url').text(data['mss_order_export_url']);
                $('#mss-order-export-wrapper .mss_order_export_end').text(data['mss_order_export_end']);

                return;
              });

          }(jQuery));
          </script>
      <?php

    }



    //Установка интервала работы Heartbeat API WP
    function heartbeat_frequency( $settings ) {
      $settings['interval'] = 3;
      return $settings;
    }



    //Надо убедиться что скрипт Hearbeat есть и напечатать скрипт управления
    function heartbeat_enqueue() {
      // Make sure the JS part of the Heartbeat API is loaded.
      wp_enqueue_script( 'heartbeat' );
      add_action( 'admin_print_footer_scripts', array($this, 'mss_ep_heartbeat_footer_js'), 20 );
    }



} $TheHAPI_MSSOrderExport = new HAPI_MSSOrderExport;
