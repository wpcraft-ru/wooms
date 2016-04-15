<?php

add_action('init','test_load');
function test_load(){

  /*
  $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');
  $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Feature/list?start=' . 7 . '&count=' . 10;

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
        )
    );


    //Запрос и получение XML-ответа
    $data_remote = wp_remote_get( $url, $args );
    $body = wp_remote_retrieve_body($data_remote);
    $xml  = new SimpleXMLElement($body);
    foreach($xml->feature as $item_feature) {
      foreach($item_feature->attribute as $item_attribute){
         $valueString = (string)$item_attribute['valueString'];
         $test=explode(':', $valueString);
         echo str_replace(' ','',$test[1]);
         if(empty($test[1])){echo str_replace(' ','',$valueString);}
      }
    }
*/
}

/* Загрузка вариаций из МойСклад в WooCommerce
пример url https://online.moysklad.ru/exchange/rest/ms/xml/EmbeddedEntityMetadata/list
Если feature="true"  то это вариация

Программное добавление вариативных продуктов
http://jafty.com/blog/programatically-create-woocommerce-products-with-variations/
*/



/**
 * Синхронизация вариаций из МойСклад на сайт
 */

class MSSVariationImport
{
  function __construct()
  {

    add_action('add_section_mss_tool', array($this, 'test'));
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));

    add_action('wp_ajax_mss_variation_import', array($this,'mss_variation_import_ajax_callback'));
    add_action('wp_ajax_nopriv_mss_variation_import', array($this,'mss_variation_import_ajax_callback'));
  }

  function test(){
    ?>
      <hr>
    <?php
  }


  //Запуск обработки экспорта товаров
  function mss_variation_import_ajax_callback(){

    set_transient( 'mss_variation_import_start', current_time('mysql'), 60); //ставим отметку на 60 секунд по запуску обновления данных

    $start = 0; //ставим позицию старта забора данных по умолчанию
    $count = 500; //ставим число одновременно запрашиваемых данных для забора
    if(isset($_REQUEST['start'])) $start = $_REQUEST['start']; //если в запросе передали параметр старта позиций, то присваиваем его в $start

    //Подготовка данных для запроса
    $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');


    $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Feature/list?start=' . $start . '&count=' . $count;

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
    $i = 0; //счетчик итераций в целом
    $r = 0; //счетчик результирующих итераций


    foreach($xml->feature as $item_feature) {

        $i++;

        $archived = (string)$item_feature['archived']; //в архиве? true или false
        $goodUuid = $item_feature['goodUuid']; //uuid продукта
        $name = $item_feature['name']; //название модификации продукта

        $uuid = (string)$item_feature->uuid; //uuid модификации продукта в МойСклад


        //
        // Условия обработки и пропуска
        //

        if($archived == 'true') continue; //Если модификация в архиве то пропуск итерации
        if(! isset($item_feature->attribute)) continue; //Если нет атрибутов, то пропускаем итерацию



        //получить продукт в базе WooCommerce по uuid из МойСклад
        $product_by_uuid_good = get_posts('post_status=any&post_type=product&numberposts=1&meta_key=uuid&meta_value=' . $goodUuid);
        if(empty($product_by_uuid_good)) continue; //Если нет продукта с UUID то пропуск
        $product_by_uuid = $product_by_uuid_good[0];


        $product_id = $product_by_uuid->ID;
        $product = new WC_Product( $product_id );

        //Делаем товар вариативным
        wp_set_object_terms( $product_id, 'variable', 'product_type' );
        $attributes=array();

        foreach($item_feature->attribute as $item_attribute){


            $valueString = (string)$item_attribute['valueString']; //Название модификации
            $metadataUuid = (string)$item_attribute['metadataUuid']; //идентификатор модификации
            $uuid_attribute = (string)$item_attribute->uuid; //uuid модификации продукта в МойСклад

            //Получить id атрибута по uuid модификации
            $mss_am = get_option( 'mss_am' ); //получаем список связи атрибутов и модификаций

            foreach($mss_am as $key => $value) {

              if(empty($value)) continue;
              $uuids=explode(',', (string)$value);
              

              if(in_array($metadataUuid, $uuids)) $id_pa = $key;

              set_transient('test', '6: ' . print_r( $id_pa, true), 777);

            }

            $data[] = $metadataUuid . ', ' . $valueString;

            global $wpdb;
            $attr_names=$wpdb->get_results('SELECT attribute_name FROM wp_woocommerce_attribute_taxonomies WHERE attribute_id='.$id_pa);
            $attr_name='pa_'.$attr_names[0]->attribute_name;
            $attr_parts=explode(':', $valueString);
            $attr_value=str_replace(' ','',$attr_parts[1]);
            
            if(empty($attr_parts[1])){$attr_value=str_replace(' ','',$valueString);}
            wp_set_object_terms( $product_id,$attr_value, $attr_name);

            $thedata = array($attr_name=>array('name'=>$attr_name,'value'=>'','is_visible' => '1','is_variation' => '1','is_taxonomy' => '1'));
            $attr_meta_value=get_post_meta($product_id,'_product_attributes',true);
            if(!empty($attr_meta_value)){
              $thedata=array_merge($attr_meta_value,$thedata);
            }
            
            update_post_meta( $product_id,'_product_attributes',$thedata);

            //получить вариацию продукта по uuid модификации товара МойСклад
        $product_var_by_uuid = get_posts('post_status=any&post_type=product_variation&numberposts=1&meta_key=uuid&meta_value=' . $uuid);


        //Если  продукт для вариации есть, то создать под него вариацию
        //if(empty($product_var_by_uuid[0])) continue; //Если нет продукта в базе для вариации то пропуск обработки


        if(isset($product_var_by_uuid[0])) {
          $product_var_by_uuid = $product_var_by_uuid[0]; //Если есть вариация продукта, то присвоить ее значение в переменную
          $product_var_id = $product_var_by_uuid->ID;
        } else {

          //Сборка данных для записи о вариации продукта
          $new_product = array(
            'post_title' => $name,
            'post_name' => 'product-' . $product_id . '-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'guid' => home_url() . '/product_variation/' . 'product-' . $product_id . '-variation'
          );

          $product_var_id = wp_insert_post($new_product);
        }
        $price=get_post_meta($product_id,'_price',true);
        $price=empty($price)?0:$price;
        $regular_price=get_post_meta($product_id,'_regular_price',true);
        $regular_price=empty($regular_price)?0:$regular_price;

        if(!empty($attr_name) && !empty($attr_value)){
          update_post_meta($product_var_id, 'attribute_'.$attr_name, $attr_value);
        }
        update_post_meta($product_var_id, '_price', $price);
        update_post_meta($product_var_id, '_regular_price', $regular_price);
        update_post_meta($product_var_id, '_sku', $post_name);
        update_post_meta($product_var_id, 'uuid', $uuid);

        update_post_meta($product_var_id, '_virtual', 'no');
        update_post_meta($product_var_id, '_downloadable', 'no');
        update_post_meta($product_var_id, '_manage_stock', 'no');
        update_post_meta($product_var_id, '_stock_status', 'instock');
            
            $r++;

        } //endforeach $item_feature->attribute

    } //endforeach $xml->feature

    set_transient('mss_i', $i, 777);
    set_transient('mss_r', $r, 777);

    //set_transient('test', print_r($data, true), 777);

    //wp_send_json_success(current_time('mysql'));
    wp_send_json_success(print_r($data, true));


  }



/*
 * Интерфейс пользователя для запуска обработки
*/
  function add_section_mss_tool_callback(){
    ?>
    <section id="mss-variation-import-wrapper">
      <header>
        <h2>Вариации продуктов: МойСклад > WooCommerce</h2>
      </header>
      <div class="instruction">
        <p>Обработка импортирует модификации из МойСклад в атрибуты WooCommerce.</p>

      </div>
      <button class="button button-small">Выполнить</button>
      <br>
      <div class="status-wrapper hide-if-js">
        <strong>Статус работы: </strong>
        <ul>
          <li>Результат первой итерации: <span class="first-result">отсутствует</span></li>
          <li>Старт работы: <span class="start-result">ждем данные</span></li>
          <li>Число итераций за последнюю минуту: <span class="mss_i">ждем данные</span></li>
          <li>Число обработанных записей: <span class="mss_r">ждем данные</span></li>
          <li>Результат: <span class="mss_result">ждем данные</span></li>


          <li>Тест-сообщение: <span class="test-result">отсутствует</span></li>
        </ul>
      </div>

      <div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
              $('#mss-variation-import-wrapper button').click(function () {

                $('#mss-variation-import-wrapper .status-wrapper').show();

                var data = {
            			action: 'mss_variation_import',
            		};

                $.getJSON(ajaxurl, data, function(response){

                  $('#mss-variation-import-wrapper .first-result').text('успех = ' + response.success + ' (' + response.data + ')');

                });
              });
            });
        </script>
      </div>
    </section>
    <?php
  }
} $TheMSSVariationImport = new MSSVariationImport;















/**
 * Hearbeat API для мониторинга состояния экспорта
 */
class HAPI_MSS_Variation_Import {
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
      if(get_transient( 'mss_variation_import_start')) {
        $data['mss_variation_import_start'] = get_transient( 'mss_variation_import_start');
      } else {
        return $data;
      }

      $data['test'] = get_transient( 'test');

      $data['mss_i'] = get_transient( 'mss_i');
      $data['mss_r'] = get_transient( 'mss_r');

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
                if ( data['mss_variation_import_start'] ){
                  $('#mss-variation-import-wrapper .start-result').text(data['mss_variation_import_start']);
                } else {
                  return; //если отметки о старте экспорта нет, то пропуск работы
                }

                //Добавляем тестовое сообщение. Используется для отладки
                $('#mss-variation-import-wrapper .test-result').text(data['test']);
                $('#mss-variation-import-wrapper .mss_i').text(data['mss_i']);
                $('#mss-variation-import-wrapper .mss_r').text(data['mss_r']);

                $('#mss-variation-import-wrapper .mss_new_product_count-result').text(data['mss_new_product_count']);
                $('#mss-variation-import-wrapper .mss_product_import_result-result').text(data['mss_product_import_result']);

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



} $TheHAPI_MSS_Variation_Import = new HAPI_MSS_Variation_Import;





















/*
Редактирование идентификаторов модификаций и вариаций продуктов МойСклад и WooCommerce

$value = get_option( 'mss_am' );
echo $value[$id] - получить uuid по id модификации

*/
class MSS_AM
{

  function __construct()
  {
    add_action('admin_init', array($this, 'add_section'), 11);
    add_action('admin_init', array($this, 'add_setting'));

    add_action('wp_ajax_mss_get_list_modifications', array($this,'mss_get_list_modifications_callback'));
    add_action('wp_ajax_nopriv_mss_get_list_modifications', array($this,'mss_get_list_modifications_callback'));

  }


  function mss_get_list_modifications_callback(){

        //Подготовка данных для запроса
        $login = get_option('mss_login_s');
        $pass = get_option('mss_pass_s');


        $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/EmbeddedEntityMetadata/list';

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
            )
        );

        //Запрос и получение XML-ответа
        $data_remote = wp_remote_get( $url, $args );
        $body = wp_remote_retrieve_body($data_remote);
        $xml  = new SimpleXMLElement($body);

        //Обработка данных от МойСклад и получение списка модификаций
        foreach($xml->embeddedEntityMetadata as $item_embeddedEntityMetadata) {
          foreach($item_embeddedEntityMetadata->attributeMetadata as $item){


            $name = (string)$item['name']; //название вариации
            $uuid = (string)$item->uuid; //uuid модификации в МойСклад
            $feature = $item['feature']; //это модификация? если true то да, иначе нет


            //Если есть реквизит feature то это модификация, иначе пропускаем итерацию
            if(isset($item['feature']) and $item['feature'] == true) {

              //Проверка сбора данных
              echo '<p>' . $name . ': ' . $uuid .  '</p>';

            } else {
                continue;
            }
          }
        }

    die(); //Завершение выполнения скрипта
  }


  function add_section(){
      /*
      Добавляем секцию на страницу настроек
      */
      add_settings_section(
        $id = 'mss_am_section_s',
        $title = 'Атрибуты и модификации',
        $callback = array($this, 'section_callback'),
        $page = 'mss_menu_settings'
      );
  }

  function section_callback(){
    ?>
      <p>Настройка параметров связки модификаций МойСклад и атрибутов WooCommerce.</p>
    <?php
  }


  //Добавлем опция Выбора склада
  function add_setting(){

    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_am' );

    add_settings_field(
      $id = 'mss_am',
      $title = 'Укажите uuid модификаций МойСклад для атрибутов WooCommerce:',
      $callback = array($this, 'field_callback'),
      $page = "mss_menu_settings",
      $section_id = "mss_am_section_s"
    );
  }


  function field_callback(){
    global $wpdb;

    $setting_name = 'mss_am';
  	$setting_value = get_option( $setting_name );

    $attributes = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies" );

    //var_dump($setting_value);
    ?>
      <div id="mss_am_wrapper">
          <?php
            if($attributes){
              ?>
              <ul>
                <?php foreach($attributes as $value): ?>
                  <li>
                    <label for="pa<?php echo $value->attribute_id ?>">
                      <span><?php echo $value->attribute_label . ' (' . $value->attribute_name . ')' ?></span>
                    </label>
                    <br/>
                    <input
                      id="pa<?php echo $value->attribute_id ?>"
                      type="text"
                      name="<?php echo $setting_name . '[' . $value->attribute_id . ']' ?>"
                      value="<?php echo $setting_value[$value->attribute_id] ?>"
                      size='55'
                    />
                  </li>
                <?php endforeach; ?>
              </ul>
              <?php
            } else {
              echo "Нужно создать атрибуты WooCommerce для настройки";
            }

          ?>

        <div class="mss_list_data">
          <a class="button button-small">Получить список модификаций МойСклад</a>
          <div class="data_list"></div>
          <script type="text/javascript">
              jQuery(document).ready(function($) {

                $('#mss_am_wrapper .button').click(function(e) {

                  e.preventDefault();

                  $.ajax({
                    method: "GET",
                    url: ajaxurl,
                    data: { action: 'mss_get_list_modifications' },
                    dataType: "html"
                  })
                  .done(function(response) {
                    $('#mss_am_wrapper .data_list').html(response);
                  });

                });
              });

          </script>
        </div>

      </div>
    <?php


  }
}
$TheMSS_AM = new MSS_AM;
