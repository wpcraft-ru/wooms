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

      set_transient( 'mss_product_images_import_start', current_time('mysql'), 600); //ставим отметку на 60 секунд по запуску обновления данных

      $start = 0; //ставим позицию старта забора данных по умолчанию
      $count = 10; //ставим число одновременно запрашиваемых данных для забора
      if(isset($_REQUEST['start'])) $start = $_REQUEST['start']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

      //Подготовка данных для запроса
      $login = get_option('mss_login_s');
      $pass = get_option('mss_pass_s');



      //$url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Good/list?start=' .  . '&count=' . ;
      //$url = 'https://online.moysklad.ru/api/remap/1.0/entity/product?limit=' . $count . '&offset=' . $start;

      //получаем путь типа https://online.moysklad.ru/exchange/rest/ms/xml/Good/list?fileContent=true&start=0&count=10
      $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Good/list?fileContent=true&start=' . $start . '&count=' . $count;

      $args = array(
          'headers' => array(
              'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
          )
      );



      //Запрос, получение и обработка ответа
      $data_remote = wp_remote_get( $url, $args );
      $body = wp_remote_retrieve_body($data_remote);
      $xml  = new SimpleXMLElement($body);


      $data = array();
      $i = 0; //счетички итераций
      $ir = 0; //счетчик результативных итераций

      foreach($xml->good as $good) {
        $i++;

        $uuid = (string)$good->uuid; //uuid продукта в МойСклад
        $img_uuid = (string)$good->images->image[0]->uuid; //получаем uuid первой картинки

        //запрос постов с нужным uuid
        $post_current_src = get_posts(array(
          'post_type' => 'product',
          'numberposts' => 1,
          'post_status' => 'any',
          'meta_key' => 'uuid',
          'meta_value' => $uuid
        ));

        //Если пост нашли с нужным uuid то определяем id поста $post_current_id, иначе пропуск
        if(isset($post_current_src)) {
            $post_current_id = $post_current_src[0]->ID;
        } else {
          continue;
        }

        //запрос медиа с нужным uuid
        $media_current_src = get_posts(array(
          'post_type' => 'attachment',
          'numberposts' => 1,
          'post_status' => 'any',
          'meta_key' => 'uuid',
          'meta_value' => $img_uuid
        ));

        //Если нашли медиа с нужным uuid то пропуск, тк не нужно загружать еще раз один и тот же файл
        if(isset($media_current_src[0])) {
          continue;
        }

        //получаем файл, сохраняем в библиотеку и привязываем к продукту

        $img_filename = (string)$good->images->image[0]['filename']; //артикул продукта
        $img_name = (string)$good->images->image[0]['name']; //артикул продукта
        $img_base64 = (string)$good->images->image[0]->contents; //получаем код первой картинки

        $upload_dir = wp_upload_dir();
        $upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

        $decoded = base64_decode( $img_base64 );
        $filename = $img_filename;

        //получаем уникальное имя файла с учетом времени
        $hashed_filename  = md5( $filename . microtime() ) . '_' . $filename;

        //сохраняем файл из строки в папку
        $image_upload = file_put_contents( $upload_path . $hashed_filename, $decoded );

        //определяем mime тип по имени файла
        $filetype = wp_check_filetype( $img_filename );

        // @new
        $file             = array();
        $file['error']    = '';
        $file['tmp_name'] = $upload_path . $hashed_filename;
        $file['name']     = $hashed_filename;
        $file['type']     = $filetype['type'];
        $file['size']     = filesize( $upload_path . $hashed_filename );

        // upload file to server
        // @new use $file instead of $image_upload
        //$file_return      = wp_handle_sideload( $file, array( 'test_form' => false ) );
        $img_id = media_handle_sideload( $file, $post_current_id, $desc = $img_name, $post_data = array() );
        if(empty($img_id)) wp_send_json_success('ошибка сохранения картинки'); //если картинка не сохранилась, то продолжить

        //добавляем uuid картинки, чтобы проверять ее наличие в будущих синхронизациях
        update_post_meta( $post_id = $img_id, $meta_key = 'uuid', $meta_value = $img_uuid );

        //устанавливаем картинку как миниатюра для продукта
        set_post_thumbnail( $post_current_id, $img_id );

        $ir++;

      }

      //set_transient( 'test', '1-' . print_r($i, true), 600); //ставим отметку на 60 секунд по запуску обновления данных
      set_transient( 'test', print_r($url, true), 600); //ставим отметку на 60 секунд по запуску обновления данных


      $ir_count = get_transient('mss_count_ir');
      if(isset($ir_count)) {
        set_transient('mss_count_i', $ir_count + $ir, 1111);
      } else {
        set_transient('mss_count_i', $ir, 1111);
      }

      //Если данные еще есть, то запускаем новую итерацию
      if($i >0) {
        $start = $start + $count;
        $url = admin_url('admin-ajax.php?action=mss_product_images_import&start=' . $start);
        $url_result = wp_remote_get($url);
      } else {
        set_transient('mss_result_msg', "работа закончилась", 777);

      }

      $data_success = current_time('mysql');
      //$data_success = print_r($img_id, true);
      wp_send_json_success($data_success);

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
            <li>Старт работы: <span class="mss_product_images_import_start">ждем данные</span></li>
            <li>Число успешных итераций: <span class="mss_count_i">ждем данные</span></li>
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
      $data['mss_count_i'] = get_transient( 'mss_count_i');

      $data['mss_product_images_import_start'] = get_transient( 'mss_product_images_import_start');
      $data['mss_result_msg'] = get_transient( 'mss_result_msg');

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
                  $('#mss_product_import_images_wrapper .mss_product_images_import_start').text(data['mss_product_images_import_start']);
                  $('#mss_product_import_images_wrapper .mss_result_msg').text(data['mss_result_msg']);
                  $('#mss_product_import_images_wrapper .mss_count_i').text(data['mss_count_i']);


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
