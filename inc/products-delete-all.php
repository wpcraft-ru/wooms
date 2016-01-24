<?php
/*
Plugin Name: Обновление всех постов в системе
Plugin URI: https://github.com/systemo-biz/bulk-post-updater
Description: Этот плагин реализует функцию массовой обработки всех постов в системе по очереди из 100 штук
Author: Systemo
Version: 1.0
Author URI: http://systemo.biz/
*/

class BulkPostRemover
{
  function __construct()
  {

    //AJAX callback mss_product_remover
    add_action('wp_ajax_mss_product_remover', array($this,'mss_product_remover_callback'));
    add_action('wp_ajax_nopriv_mss_product_remover', array($this,'mss_product_remover_callback'));

    //Add section
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));
  }
  //AJAX callback
  function mss_product_remover_callback(){
    set_transient( 'mss_pr_start_time', current_time('mysql'), 600); //ставим отметку на 60 секунд по запуску обновления данных

    delete_transient( 'mss_pr_end' );//удаляем отметку о результате, если работа идет

    $offset = 0; //ставим позицию старта забора данных по умолчанию
    $numberposts = 100; //ставим число одновременно запрашиваемых данных для забора
    if(isset($_REQUEST['offset'])) $offset = $_REQUEST['offset']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

    set_transient('test', $offset, 777);

    //получить все посты типа Продукты и Вариация продукта
    $posts = get_posts(array(
      'post_status'=>array('publish', 'draft'),
      'post_type' => array('product', 'product_variation'),
      'numberposts' => $numberposts,
      'offset' => $offset
    ));

    $i = 0;

    if(isset($posts)) {
      foreach ($posts as $key => $post) {
        wp_delete_post( $post->ID );
        $i++;
      }
    }

    //берем значение счетчика импорта продуктов.
    //Если есть то прибавляем число текущих итераций
    //если нет то помещаем туда число текущих итераций
    $itc = get_transient('mss_rp_count');
    if(isset($itc)) {
      set_transient('mss_rp_count', $itc + $i, 66);
    } else {
      set_transient('mss_rp_count', $i, 66);
    }
    //Если есть данные, то выполнение новой порции иначе запись результата
    if($i) {

      //Перезапуск итерации с новой порцией данных
      $offset = $offset + $numberposts;
      $url = admin_url('admin-ajax.php?action=mss_product_remover&offset=' . $offset);
      $url_result = wp_remote_get($url);

    } else {

      set_transient('mss_pr_end', "Работа выполнена", 777);

    }
    wp_send_json_success(current_time('mysql'));
  }


  function add_section_mss_tool_callback(){
    ?>
    <section id="mss_product_remover_wrapper">
      <header>
        <h2>Удаление всех продуктов в базе Woocommerce</h2>
      </header>
      <div class="instruction">
        <p>Обработка удаляет все продукты из базы WooCommerce.</p>
      </div>
      <button class="button button-small">Выполнить</button>
      <br>
      <div class="status_wrapper hide-if-js">
        <strong>Статус работы: </strong>
        <ul>
          <li>Результат первой итерации: <span class="first_result">отсутствует</span></li>
          <li>Старт работы: <span class="start_result">ждем данные</span></li>
          <li>Число итераций за последнюю минуту: <span class="mss_rp_count">ждем данные</span></li>
          <li>Результат: <span class="mss_pr_end">ждем данные</span></li>
          <li>Тест-сообщение: <span class="test_result">отсутствует</span></li>
        </ul>
      </div>

      <div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
              $('#mss_product_remover_wrapper button').click(function () {

                $('#mss_product_remover_wrapper .status_wrapper').show();

                var data = {
            			action: 'mss_product_remover',
            		};

                $.getJSON(ajaxurl, data, function(response){

                  $('#mss_product_remover_wrapper .first_result').text('успех = ' + response.success + ' (' + response.data + ')');

                });
              });
            });
        </script>
      </div>
    </section>
    <?php
  }

}
$TheBulkPostRemover = new BulkPostRemover;



/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSS_BulkPostRemover {
    function __construct(){
      //Hearbeat update data
      add_action( 'admin_enqueue_scripts', array($this, 'heartbeat_enqueue'));
      add_filter( 'heartbeat_settings', array($this, 'heartbeat_frequency') );
      add_filter( 'heartbeat_send', array($this, 'heartbeat_send_cb'), 10, 2 );
    }



    //Получаем количество обработанных записей из кеша для вывода через Hearbeat
    function heartbeat_send_cb($data, $screen_id){

      //Проверка экрана, если не тот, значит прерываем работу
      if('tools_page_mss-tools' != $screen_id) return $data;

      //Если запущна экспорт, то помечаем данные, иначе отключаем передачу данных на клиента
      if(get_transient( 'mss_pr_start_time')) {
        $data['mss_pr_start_time'] = get_transient( 'mss_pr_start_time');
      } else {
        return $data;
      }

      $data['test'] = get_transient( 'test');

      $data['mss_rp_count'] = get_transient( 'mss_rp_count');

      $data['mss_new_product_count'] = get_transient( 'mss_new_product_count');
      $data['mss_pr_end'] = get_transient( 'mss_pr_end');

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
                if ( data['mss_pr_start_time'] ){
                  $('#mss_product_remover_wrapper .start_result').text(data['mss_pr_start_time']);
                } else {
                  return; //если отметки о старте экспорта нет, то пропуск работы
                }

                //Добавляем тестовое сообщение. Используется для отладки
                $('#mss_product_remover_wrapper .test_result').text(data['test']);
                $('#mss_product_remover_wrapper .mss_rp_count').text(data['mss_rp_count']);
                $('#mss_product_remover_wrapper .mss_pr_end').text(data['mss_pr_end']);

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



} $TheHAPI_MSS_BulkPostRemover = new HAPI_MSS_BulkPostRemover;
