<?php


/**
 * Импорт картинок продуктов из МойСклад на сайт
 */

class MSSProductsImportImages
{
  function __construct()
  {
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));

    add_action('wp_ajax_mss_product_images_import', array($this,'ajax_callback'));
    add_action('wp_ajax_nopriv_mss_product_images_import', array($this,'ajax_callback'));
  }





    //Запуск обработки
    function ajax_callback(){

      set_transient( 'mss_product_images_import_start', current_time('mysql'), 60); //ставим отметку на 60 секунд по запуску обновления данных

      $start = 0; //ставим позицию старта забора данных по умолчанию
      $count = 50; //ставим число одновременно запрашиваемых данных для забора
      if(isset($_REQUEST['start'])) $start = $_REQUEST['start']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

      //Подготовка данных для запроса
      $login = get_option('mss_login_s');
      $pass = get_option('mss_pass_s');



      //$url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Good/list?start=' .  . '&count=' . ;
      $url = 'https://online.moysklad.ru/api/remap/1.0/entity/product?limit=' . $count . '&offset=' . $start;
      $args = array(
          'headers' => array(
              'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
          )
      );

      //set_transient( 'test', $url, 600); //ставим отметку на 60 секунд по запуску обновления данных


      //Запрос, получение и обработка ответа
      $data_remote = wp_remote_get( $url, $args );
      $body = wp_remote_retrieve_body($data_remote);
      $json  = json_decode($body);


      $data = array();
      $i = 0;

      foreach($json->rows as $good) {

          $post_isset = get_posts(array(
            'post_type' => 'product',
            'numberposts' => 1,
            'post_status' => 'any',
            'meta_key' => 'uuid',
            'meta_value' => '51a3fc72-1f2e-11e5-90a2-8ecb001fb6f0'//$good->id
          ));

          if(isset($post_isset[0]->ID))
            set_transient( 'test', '7-' . print_r($post_isset[0]->ID, true), 600); //ставим отметку на 60 секунд по запуску обновления данных



          //Картинка
          $img_url = $good->image->meta->href;




          if(isset($img_url1)){

            $tmp = media_sideload_image( $img_url );

            if( is_wp_error( $tmp ) )
            	wp_send_json_success($tmp->get_error_messages());
            else
            	echo $tmp; // выведет: /tmp/wp-header-logo.tmp

            /* делаем что-либо с файлом ... */

            // удаляем временный файл
            @unlink( $tmp );
          }



          //set_transient( 'test', '5-' . print_r($img_url, true), 600); //ставим отметку на 60 секунд по запуску обновления данных


        $i++;

      }





      wp_send_json_success(current_time('mysql'));

    }






    //Интерфейс пользователя
    function add_section_mss_tool_callback(){
      ?>
      <section id="mss_product_import_images_wrapper">
        <header>
          <h2>Изображения товаров: МойСклад > Сайт</h2>
        </header>
        <div class="instruction">
          <p>Обязательно условие - наличие продукта в каталоге. Если его нет, то импорт не происходит.</p>

        </div>
        <button class="button button-small">Запустить импорт</button>
        <br>
        <div class="status-wrapper hide-if-js">
          <br/>
          <strong>Статус работы:</strong>
          <ul>
            <li>Результат первой итерации: <span class="mss_first_result">отсутствует</span></li>
            <li>Старт работы: <span class="mss_start_result">ждем данные</span></li>
            <li>Число итераций за последнюю минуту: <span class="mss_countr_result">ждем данные</span></li>
            <li>Число успешных итераций: <span class="mss_count_result">ждем данные</span></li>
            <li>Результат: <span class="mss_result_msg">ждем данные</span></li>


            <li>Тест-сообщение: <span class="mss_test_msg">отсутствует</span></li>
          </ul>
        </div>

        <div>
          <script type="text/javascript">
              jQuery(document).ready(function($) {
                $('#mss_product_import_images_wrapper button').click(function () {

                  $('#mss_product_import_images_wrapper .status-wrapper').show();

                  var data = {
              			action: 'mss_product_images_import',
              		};

                  $.getJSON(ajaxurl, data, function(response){

                    $('#mss_product_import_images_wrapper .mss_first_result').text('успех = ' + response.success + ' (' + response.data + ')');

                  });
                });
              });
          </script>
        </div>
      </section>
      <?php
    }








} $TheMSSProductsImportImages = new MSSProductsImportImages;




/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSSProductsImportImages {
    function __construct(){
      //Hearbeat update data
      add_action( 'admin_enqueue_scripts', array($this, 'heartbeat_enqueue'));
      add_filter( 'heartbeat_settings', array($this, 'heartbeat_frequency') );

      add_filter( 'heartbeat_send', array($this, 'heartbeat_callback'), 10, 2 );
    }



    //Получаем количество обработанных записей из кеша для вывода через Hearbeat
    function heartbeat_callback($data, $screen_id){

      //Проверка экрана, если не тот, значит прерываем работу
      if('tools_page_mss-tools' != $screen_id) return $data;

      //Если запущна экспорт, то помечаем данные, иначе отключаем передачу данных на клиента
      if(get_transient( 'mss_product_images_import_start')) {
        $data['mss_product_images_import_start'] = get_transient( 'mss_product_images_import_start');
      } else {
        return $data;
      }

      $data['test'] = get_transient( 'test' );

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
          <script type="text/javascript" id="mss_import_product_images_hb">

            (function($){

                $(document).on( 'heartbeat-tick', function(e, data) {

                  //console.log('Сообщение HB для импорта продуктов готово: ' + data['mss_product_images_import_start']);

                  //Если есть данное о старте, то работаем, иначе прирываем работу
                  if ( data['mss_product_images_import_start'] ){
                    $('#mss_product_import_images_wrapper .mss_first_result').text(data['mss_product_images_import_start']);
                  } else {
                    return; //если отметки о старте экспорта нет, то пропуск работы
                  }

                  //Добавляем тестовое сообщение. Используется для отладки
                  $('#mss_product_import_images_wrapper .mss_test_msg').text(data['test']);
                  $('#mss_product_import_images_wrapper .mss_first_result').text(data['count_product_import']);
                  $('#mss_product_import_images_wrapper .mss_new_product_count-result').text(data['mss_new_product_count']);
                  $('#mss_product_import_images_wrapper .mss_product_import_result-result').text(data['mss_product_import_result']);

                  return;
                });

            }(jQuery));

          </script>
      <?php

    }


    //Надо убедиться что скрипт Hearbeat есть и напечатать скрипт управления
    function heartbeat_enqueue(){
      // Make sure the JS part of the Heartbeat API is loaded.
      wp_enqueue_script( 'heartbeat' );
      add_action( 'admin_print_footer_scripts', array($this, 'mss_ep_heartbeat_footer_js'), 20 );
    }


    //Установка интервала работы Heartbeat API WP
    function heartbeat_frequency( $settings ) {
      $settings['interval'] = 5;
      return $settings;
    }



} $TheHAPI_MSSProductsImportImages = new HAPI_MSSProductsImportImages;
