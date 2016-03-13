<?php

/**
 * Экспорт продуктов из сайта в МойСклад
 */

class MSSProductsImport
{
  function __construct()
  {
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));

    add_action('wp_ajax_mss_product_import', array($this,'mss_product_import_ajax_callback'));
    add_action('wp_ajax_nopriv_mss_product_import', array($this,'mss_product_import_ajax_callback'));
  }




  //Запуск обработки экспорта товаров
  function mss_product_import_ajax_callback(){

    set_transient( 'mss_product_import_start', current_time('mysql'), 60); //ставим отметку на 60 секунд по запуску обновления данных

    $start = 0; //ставим позицию старта забора данных по умолчанию
    $count = 50; //ставим число одновременно запрашиваемых данных для забора
    if(isset($_REQUEST['start'])) $start = $_REQUEST['start']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

    //Подготовка данных для запроса
    $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');


    $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Good/list?start=' . $start . '&count=' . $count;
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
        )
    );


    //Запрос и получение XML-ответа
    $data_remote = wp_remote_get( $url, $args );
    $body = wp_remote_retrieve_body($data_remote);
    $xml  = new SimpleXMLElement($body);


    $data = array();
    $i = 0;
    foreach($xml->good as $good) {


      //Получаем данные для работы с продуктом
      $name = (string)$good['name']; //имя продукта
      $productCode = (string)$good['productCode']; //артикул продукта
      $salePrice = (string)$good['salePrice']/100; //цена продукта
      $updated = (string)$good['updated']; //дата обновления
      $parentUuid = (string)$good['parentUuid']; //дата обновления

      //$groupUuid = (string)$good->groupUuid; //идентификатор группы


      //описание продукта $description
      if(isset($good['description'])){
        $description = (string)$good['description']; //описание продукта
      } else {
        $description = '';
      }

      $code = (string)$good->code; //код продукта в МойСклад
      $uuid = (string)$good->uuid; //uuid продукта в МойСклад


      //
      //Условия обработки
      //

      if(empty($productCode)) continue; //Если у товара нет артикула то пропуск обработки


      //Смотри есть ли такой продукт с артикулом. Если есть то обновлем, если нет то создаем
      $product_w_sku = get_posts('post_status=any&post_type=product&numberposts=1&meta_key=_sku&meta_value=' . $productCode);
      if(isset($product_w_sku[0]->ID)) {
        $post_id = $product_w_sku[0]->ID;
        //обработка продукта с артикулом
        $post_data = array(
          'ID'            => $post_id,
          'post_title'    => wp_strip_all_tags( $name ),
          'post_content'  => $description,
          "post_type" => 'product',
        );
        wp_update_post( $post_data );

      } else {

        //создание продукта с артикулом
        $post_data = array(
          'post_title'    => wp_strip_all_tags( $name ),
          'post_content'  => $description,
          'post_status'   => 'publish',
          "post_type" => 'product',
        );

        // Вставляем запись в базу данных
        $post_id = wp_insert_post( $post_data );
        // Присваиваем артикул
        update_post_meta($post_id, '_sku', $productCode);

      }

      //Если есть ИД поста, то выполнить доп обработки
      if($post_id){
        update_post_meta($post_id, 'mss_updated', $updated);
        update_post_meta($post_id, 'mss_code', $code);
        update_post_meta($post_id, '_regular_price', $salePrice);
        update_post_meta($post_id, '_price', $salePrice); //Присвоили значение  для вывода цены в списке всех продуктов
        update_post_meta($post_id, 'uuid', $uuid); //Присвоили значение uuid продукту

        //если есть uuid группы, то присваиваем категорию продуктов
        if(isset($parentUuid)){

          //set_transient('test', "5 " . $parentUuid, 777);
          $term_id = get_term_id_by_uuid_mss($parentUuid);

          //Если соответствующую рубрику нашли, то присваиваем продукту категорию
          if(isset($term_id)){
            wp_set_post_terms( $post_id, $term_id, 'product_cat', false );

          }

        }

        wp_update_post( array('ID'  => $post_id )); //обновление поста
        update_post_meta($post_id, '_visibility', 'visible'); //Указываем видимость продукта
      }

      $i++;

    }





    //цикл обработки данных

    //берем значение счетчика импорта продуктов.
    //Если есть то прибавляем число текущих итераций
    //если нет то помещаем туда число текущих итераций
    $itc = get_transient('count_product_import');
    if(isset($itc)) {
      set_transient('count_product_import', $itc + $i, 66);
    } else {
      set_transient('count_product_import', $i, 66);
    }

    //Если данные еще есть, то запускаем новую итерацию
    if($i >0) {
      $start = $start + $count;
      $url = admin_url('admin-ajax.php?action=mss_product_import&start=' . $start);
      $url_result = wp_remote_get($url);
    } else {
      set_transient('mss_product_import_result', "работа закончилась", 777);

    }
    set_transient('test', $url, 777);

    wp_send_json_success(current_time('mysql'));

  }


  //Интерфейс пользователя для запуска выгрузки продуктов
  function add_section_mss_tool_callback(){
    ?>
    <section id="mss-product-import-wrapper">
      <header>
        <h2>Импорт товаров из МойСклад на сайт</h2>
      </header>
      <div class="instruction">
        <p>Обработка импортирует товары из МойСклад в WooCommerce</p>
        <p>Обязательно условие - наличие уникального артикула. Если его нет, то импорт не происходит.</p>
        <!-- <p>Все новые товары сохраняются как черновики. Их публикация должна быть выполнена вручную</p> -->

      </div>
      <button id="mss-product-import" class="button button-small">Запустить импорт</button>
      <br>
      <div class="status-wrapper hide-if-js">
        <strong>Статус работы: </strong>
        <ul>
          <li>Результат первой итерации: <span class="first-result">отсутствует</span></li>
          <li>Старт работы: <span class="start-result">ждем данные</span></li>
          <li>Число итераций за последнюю минуту: <span class="countr-result">ждем данные</span></li>
          <li>Число новых продуктов: <span class="mss_new_product_count-result">ждем данные</span></li>
          <li>Результат: <span class="mss_product_import_result-result">ждем данные</span></li>


          <li>Тест-сообщение: <span class="test-result">отсутствует</span></li>
        </ul>
      </div>

      <div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
              $('#mss-product-import-wrapper button').click(function () {

                $('#mss-product-import-wrapper .status-wrapper').show();

                var data = {
            			action: 'mss_product_import',
            		};

                $.getJSON(ajaxurl, data, function(response){

                  $('#mss-product-import-wrapper .first-result').text('успех = ' + response.success + ' (' + response.data + ')');

                });
              });
            });
        </script>
      </div>
    </section>
    <?php
  }
} $TheMSSProductsImport = new MSSProductsImport;




/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSS_Import_Product {
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
      if(get_transient( 'mss_product_import_start')) {
        $data['mss_product_import_start'] = get_transient( 'mss_product_import_start');
      } else {
        return $data;
      }

      $data['test'] = get_transient( 'test');

      $data['count_product_import'] = get_transient( 'count_product_import');
      $data['mss_new_product_count'] = get_transient( 'mss_new_product_count');
      $data['mss_product_import_result'] = get_transient( 'mss_product_import_result');

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
                if ( data['mss_product_import_start'] ){
                  $('#mss-product-import-wrapper .start-result').text(data['mss_product_import_start']);
                } else {
                  return; //если отметки о старте экспорта нет, то пропуск работы
                }

                //Добавляем тестовое сообщение. Используется для отладки
                $('#mss-product-import-wrapper .test-result').text(data['test']);
                $('#mss-product-import-wrapper .countr-result').text(data['count_product_import']);
                $('#mss-product-import-wrapper .mss_new_product_count-result').text(data['mss_new_product_count']);
                $('#mss-product-import-wrapper .mss_product_import_result-result').text(data['mss_product_import_result']);

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



} $TheHAPI_MSS_Import_Product = new HAPI_MSS_Import_Product;
