<?php





/**
 * Экспорт продуктов из сайта в МойСклад
 */
class ExportProductsMSS
{
  function __construct()
  {
    add_action('add_section_mss_tool', array($this, 'export_tool'));
    add_action('add_section_mss_tool', array($this, 'test'));

    if(is_admin()){
      add_action('wp_ajax_export_product_mss', array($this,'export_product_mss_callback'));
      add_action('wp_ajax_nopriv_export_product_mss', array($this,'export_product_mss_callback'));
    }
  }

  function test(){
    ?>
      <hr>
      <hr>
    <?php
  }

  //Запуск обработки экспорта товаров
  function export_product_mss_callback(){

    set_transient( 'mss_product_export_start', true, 60); //ставим отметку на 60 секунд по запуску обновления данных

    $numberposts = 3; //число записей для обработки за одну итерацию
    $offset = 0;
    if(isset($_REQUEST['offset'])) $offset = $_REQUEST['offset'];

    set_transient( 'mss_offset_product', $offset, 1 * HOUR_IN_SECONDS );

    $posts = get_posts(array(
      'post_type' => 'product',
      'numberposts' => $numberposts,
      'offset' => $offset
    ));

    $data = array();


    if($posts) {
      if($offset == 0)
        set_transient( 'mss_export_products_i_start', date('Y-m-d h:m:s'), 1 * HOUR_IN_SECONDS );

      //Цикл обработки продуктов
      foreach ($posts as $key => $value) {
        //sleep(1);
        //запрос кеша счетчика, если есть то присвоение в $i иначе $i = 0
        $xml = $this->mss_create_xml_product($value->ID);

        if($xml) {
  				$result = send_xml_to_moysklad('Good', $xml);
          set_transient( 'test', '3:' . $result, 1 * HOUR_IN_SECONDS );

  				if ($result->uuid) {
  					update_post_meta($value->ID, '_woosklad_good_uuid', (string)$result->uuid);
  					update_post_meta($value->ID, '_woosklad_new_stock', '');

  					get_variation($value->ID,(string)$result['updated']);
  				}
          else {
  					update_option('woosklad_error', current_time('mysql').' Невозможно выгрузить товары. Проверьте правильность введенных логина и пароля');
            wp_send_json_error("Не вернулись данные МойСклад");

            exit;
  				}
			  }


      }

      //снова вызов этой же функции через AJAX с отступом в указанное число записей
      $offset = $offset + $numberposts;
      $url = admin_url('admin-ajax.php?action=export_product_mss&offset=' . $offset);
      $url_result = wp_remote_get($url);
      wp_send_json_success(array($url, $url_result['response']['code']));
    } else {

      set_transient( 'mss_export_product_end', date('Y-m-d h:m:s'), 1 * HOUR_IN_SECONDS );
      wp_send_json_success("Данные в запросе закончились");

    }
    wp_send_json_error("Не сработали условия");
  }


  /*
  * Генерация XML для продукта
  * return xml
  */
  function mss_create_xml_product($id){


    //================================================
    // Секция сбора данных для XML
    $product = new WC_Product($id);
		$weight = get_post_meta($id, '_weight', true); $weight = $weight ? $weight : "0.0";

		$buyPrice = get_post_meta($id, '_max_variation_regular_price', true) ? get_post_meta($id, '_max_variation_regular_price', true) :
			get_post_meta($id, '_regular_price', true);
		$buyPrice = $buyPrice ? $buyPrice*100 : "0.0";

		$salePrice = get_post_meta($id, '_max_variation_price', true) ? get_post_meta($id, '_max_variation_price', true) :
			get_post_meta($id, '_price', true);
		$salePrice = $salePrice ? $salePrice*100 : "0.0";

		$uuid = get_post_meta($id, '_woosklad_good_uuid', true);
		//echo $uuid."<br />";
		$categories = get_the_terms($id, 'product_cat');
		put_category_identity($categories);
		$parent = get_parent_category($categories);
		$parentUuid = get_option('woosklad_category_'.$parent.'_uuid') ?
			'parentUuid="'.get_option('woosklad_category_'.$parent.'_uuid').'"' : '';
    $mss_parent_uuid = get_option('woosklad_category_'.$parent.'_uuid');


    //================================================
    //Начало формирования XML документа


    $dom = new DomDocument('1.0', 'UTF-8');

    //добавление корня - <good>
    $good = $dom->appendChild($dom->createElement('good'));
    $good->setAttribute("isSerialTrackable", 'false');
    $good->setAttribute("weight", $weight);
    $good->setAttribute("salePrice", $salePrice);
    $good->setAttribute("buyPrice", $buyPrice);
    $good->setAttribute("name", $product->get_title());
    $good->setAttribute("productCode", get_post_meta($id, '_sku', true));
    if($mss_parent_uuid) $good->setAttribute("parentUuid", $mss_parent_uuid);

    //добавление элемента <code> в <good> с значением
    $code = $good->appendChild($dom->createElement('code', get_post_meta($id, '_sku', true)));

    if($uuid) {
      $uuid_dom = $dom->createElement("uuid", $uuid);
      $good->appendChild($uuid_dom);
    }

    //генерация xml
    $dom->formatOutput = true; // установка атрибута formatOutput domDocument в значение true

     // save XML as string or file
    $xml = $dom->saveXML();

    return $xml;
  }


  //Интерфейс пользователя для запуска выгрузки продуктов
  function export_tool(){
    ?>
    <section>
      <header>
        <h2>Экспорт товаров из сайта в МойСклад</h2>
      </header>
      <div>
        <button id="export-product-mss" class="button button-small">Запустить экспорт</button>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
              $('#export-product-mss').click(function () {
                var data = {
            			action: 'export_product_mss',
            		};

                $.getJSON(ajaxurl, data, function(response){
                  console.log('Got this from the server: ' + response.success + ', данное: ' + response.data );
                });
              });
            });
        </script>
      </div>
    </section>
    <?php
  }
} $TheExportProductsMSS = new ExportProductsMSS;







/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSS_Export_Product
{

    function __construct(){
      //Hearbeat update data
      add_action( 'admin_enqueue_scripts', array($this, 'heartbeat_enqueue'));
      add_filter( 'heartbeat_settings', array($this, 'heartbeat_frequency') );

      add_filter( 'heartbeat_send', array($this, 'mss_export_products_heartbeat'), 10, 2 );

    }



    //Получаем количество обработанных записей из кеша для вывода через Hearbeat
    function mss_export_products_heartbeat($data, $screen_id){

      //Проверка экрана, если не тот, значит прерываем работу
      if('tools_page_mss-tools' != $screen_id) return $data;


      $data['test'] = get_transient( 'test');

      //Если запущна экспорт, то помечаем данные, иначе отключаем передачу данных на клиента
      if(get_transient( 'mss_product_export_start')) {
        $data['mss_product_export_start'] = get_transient( 'mss_product_export_start');
      } else {
        return $data;
      }



      //Если есть данные кеша, то запись значения в Hearbeat
      if(get_transient( 'mss_offset_product'))
        $data['mss_offset_product'] = get_transient( 'mss_offset_product');

      //Запись о завершении экспорта
      if(get_transient( 'mss_export_product_end'))
        $data['mss_export_product_end'] = get_transient( 'mss_export_product_end');

      //Запись о номере итерации
      if(get_transient( 'mss_export_product_count_i'))
        $data['mss_export_product_count_i'] = get_transient( 'mss_export_product_count_i');

      //Запись времени старта
      if(get_transient( 'mss_export_products_i_start'))
        $data['mss_export_products_i_start'] = get_transient( 'mss_export_products_i_start');

      return $data;
    }



    //Прослушка данных Hearbeat и их вывод в лог
    function mss_ep_heartbeat_footer_js(){
      $cs = get_current_screen();
      if('tools_page_mss-tools' != $cs->id) return;
      ?>
          <script type="text/javascript" id="mss_export_product_hb">
          (function($){
              $( document ).ready(function() {
                //console.log('Сообщение HB для экспорта продуктов готово');

              });

              $(document).on( 'heartbeat-tick', function(e, data) {


              if ( !data['mss_product_export_start'] ) return; //если отметки о старте экспорта нет, то пропуск работы

                console.log('test: ' + data['test']);

                //Если есть данны об очереди
                if ( data['mss_export_products_i_start'] )
                  console.log('Время старт итерации экспорта продуктов в МойСклад: ' + data['mss_export_products_i_start']);

                //Если есть данны об очереди
                if ( data['mss_offset_product'] )
                  console.log('Очередь экспорта продуктов в МойСклад: ' + data['mss_offset_product']);

                //Если есть данные о завершении
                if ( data['mss_export_product_end'] )
                  console.log('Очередь экспорта продуктов в МойСклад завершена: ' + data['mss_export_product_end']);

                //Если есть данные о завершении
                if ( data['mss_export_product_count_i'] )
                  console.log('Номер итерации экспорта продуктов в МойСклад: ' + data['mss_export_product_count_i']);

                return;
              });

          }(jQuery));
          </script>
      <?php

    }

    //Установка интервала работы HB
    function heartbeat_frequency( $settings ) {
      $settings['interval'] = 2;
      return $settings;
    }

    //Надо убедиться что скрипт Hearbeat есть и напечатать скрипт управления
    function heartbeat_enqueue() {
      // Make sure the JS part of the Heartbeat API is loaded.
      wp_enqueue_script( 'heartbeat' );
      add_action( 'admin_print_footer_scripts', array($this, 'mss_ep_heartbeat_footer_js'), 20 );
    }
} $TheHAPI_MSS_Export_Product = new HAPI_MSS_Export_Product;
