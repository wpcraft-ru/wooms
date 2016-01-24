<?php

/**
 * Выбор склада в опциях
 */
class MSSWarehouse
{

  function __construct()
  {
    add_action('admin_init', array($this, 'add_section'));
    add_action('admin_init', array($this, 'mss_warehouse_add_setting'));


    add_action('wp_ajax_mss-get-warehouses', array($this,'get_warehouses_ajax_callback'));
    add_action('wp_ajax_nopriv_mss-get-warehouses', array($this,'get_warehouses_ajax_callback'));
  }

  function get_warehouses_ajax_callback(){

    $data = array();

    //Подготовка данных для запроса
    $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');

    $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/Warehouse/list';
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
        )
    );

    //Запрос и получение XML-ответа
    $data_remote = wp_remote_get( $url, $args );


    $body = wp_remote_retrieve_body($data_remote);

    $xml  = new SimpleXMLElement($body);

    $i = 0;

    if( !isset($xml) ) wp_send_json_error( 'нет xml' );

    foreach($xml->warehouse as $item) {

      //Получаем данные для работы
      $name = (string)$item['name']; //имя склада
      $uuid = (string)$item->uuid; //индентификатор склада
      $data[] = array('name' => $name, 'uuid' => $uuid);

    }

    update_option( 'mss_warehouse_list', $data, $autoload = false );

    wp_send_json_success( count($data) );
  }

  function add_section(){
      /*
    	Добавляем секцию на страницу настроек
    	*/
    	add_settings_section(
    		$id = 'mss_warehouse_section_s',
    		$title = 'Склад',
        $callback = array($this, 'mss_warehouse_section_s'),
    		$page = 'mss_menu_settings'
    	);

  }

  function mss_warehouse_section_s(){
    ?>
      <div class="mss-get-warehouses-control-wrapper">
        <p>Настройка параметров склада.<br> Если список еще не получен или данные складов изменились, то нужно обновить список</p>
        <button id="mss-get-warehouses" class="button button-small">Получить список складов</button>
      </div>
      <script type="text/javascript">
          jQuery(document).ready(function($) {
            $('#mss-get-warehouses').click(function (e) {
              e.preventDefault();
              var data = {
                action: 'mss-get-warehouses',
              };

              $.getJSON(ajaxurl, data, function(response){

                //обновляем текст кнопки о результате
                $('#mss-get-warehouses').text('успех = ' + response.success + ' (' + response.data + ')');

                //перезагружаем страницу
                location.reload();

              });
            });
          });
      </script>
    <?php
  }

  //Добавлем опция Выбора склада
  function mss_warehouse_add_setting(){

    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_warehouse' );

    add_settings_field(
      $id = 'mss_warehouse_field_s',
      $title = 'Выбор склада:',
      $callback = array($this, 'mss_warehouse_field_s_callback'),
      $page = "mss_menu_settings",
      $section_id = "mss_warehouse_section_s"
    );
  }

  function mss_warehouse_field_s_callback(){
    $setting_name = 'mss_warehouse';
  	$setting_value = get_option( $setting_name );

    $mss_warehouse_list = get_option('mss_warehouse_list');
    //print_r($mss_warehouse_list);


    if($mss_warehouse_list){

      ?>
        <div class="mss_warehouse_wrapper">
          <ul>
            <?php foreach($mss_warehouse_list as $key => $value): ?>
              <li>
                <input id="<?php echo $value['uuid']; ?>" <?php checked( $value['uuid'], $setting_value) ?> type="radio" name="<?php echo $setting_name ?>" value="<?php echo $value['uuid']; ?>" />
                <label for="<?php echo $value['uuid']; ?>"><?php echo $value['name']; ?></label>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php
    } else {
      echo "Нужно получить список складов перед выбором";
    }
  }
}
$TheMSSWarehouse = new MSSWarehouse;
