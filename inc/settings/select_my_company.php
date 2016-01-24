<?php

/**
 * Выбор нашей организации в опциях
 * ключ хранения в опциях uuid: mss_my_company
 * ключ хранения списка компаний в опциях uuid: mss_my_company_list
 * ключ запроса AJAX: mss-get-my-company
 */
class MSSMyCompanySelect
{


  function __construct()
  {
    add_action('admin_init', array($this, 'add_section'));
    add_action('admin_init', array($this, 'mss_my_company_add_setting'));


    add_action('wp_ajax_mss-get-my-company', array($this,'mss_my_company_ajax_callback'));
    add_action('wp_ajax_nopriv_mss-get-my-company', array($this,'mss_my_company_ajax_callback'));
  }

  function mss_my_company_ajax_callback(){

    $data = array();

    //Подготовка данных для запроса
    $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');

    $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/MyCompany/list';
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

    foreach($xml->myCompany as $item) {

      //Получаем данные для работы
      $name = (string)$item['name']; //имя склада
      $uuid = (string)$item->uuid; //индентификатор склада
      $data[] = array('name' => $name, 'uuid' => $uuid);

    }

    update_option( 'mss_my_company_list', $data, $autoload = false );

    wp_send_json_success( count($data) );
  }



  function mss_my_company_section_s(){
    ?>
      <p>Настройка параметров нашей компании.<br> Если список еще не получен или данные изменились, то нужно обновить список</p>
      <button id="mss-get-my-company-list" class="button button-small">Получить список</button>
      <script type="text/javascript">
          jQuery(document).ready(function($) {
            $('#mss-get-my-company-list').click(function (e) {
              e.preventDefault();
              var data = {
                action: 'mss-get-my-company',
              };

              $.getJSON(ajaxurl, data, function(response){

                //обновляем текст кнопки о результате
                $('#mss-get-my-company-list').text('успех = ' + response.success + ' (' + response.data + ')');

                //перезагружаем страницу
                location.reload();

              });
            });
          });
      </script>
    <?php
  }


  function add_section(){
      /*
      Добавляем секцию на страницу настроек
      */
      add_settings_section(
        $id = 'mss_my_company_section_s',
        $title = 'Наша организация',
        $callback = array($this, 'mss_my_company_section_s'),
        $page = 'mss_menu_settings'
      );

  }

  //Добавлем опция Выбора нашей компании
  function mss_my_company_add_setting(){

    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_my_company' );

    add_settings_field(
      $id = 'mss_my_company_field_s',
      $title = 'Выбор нашей организации:',
      $callback = array($this, 'mss_my_company_field_s_callback'),
      $page = "mss_menu_settings",
      $section_id = "mss_my_company_section_s"
    );
  }

  function mss_my_company_field_s_callback(){
    $setting_name = 'mss_my_company';
  	$setting_value = get_option( $setting_name );

    $data_list = get_option('mss_my_company_list');

    if($data_list){

      ?>
        <div class="data_list_wrapper">
          <ul>
            <?php foreach($data_list as $key => $value): ?>
              <li>
                <input id="<?php echo $value['uuid']; ?>" <?php checked( $value['uuid'], $setting_value) ?> type="radio" name="<?php echo $setting_name ?>" value="<?php echo $value['uuid']; ?>" />
                <label for="<?php echo $value['uuid']; ?>"><?php echo $value['name']; ?></label>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php
    } else {
      echo "Нужно получить список наших организаций перед выбором";
    }
  }
}
$TheMSSMyCompanySelect = new MSSMyCompanySelect;
