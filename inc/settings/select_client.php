<?php

/**
 * Выбор клиента в опциях
 * ключ хранения в опциях uuid: mss_client
 */
class MSSClientSelect
{

  function __construct()
  {
    add_action('admin_init', array($this, 'add_section'));
    add_action('admin_init', array($this, 'add_setting'));

  }


  function add_section(){
      /*
      Добавляем секцию на страницу настроек
      */
      add_settings_section(
        $id = 'mss_client_section_s',
        $title = 'Клиент',
        $callback = array($this, 'section_callback'),
        $page = 'mss_menu_settings'
      );
  }

  function section_callback(){
    ?>
      <p>Настройка параметров клиента.<br>
        Нужно ввести uuid клиента который будет подставляться в заказ из справочника МойСклад</p>
    <?php
  }


  //Добавлем опция Выбора склада
  function add_setting(){

    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_client' );

    add_settings_field(
      $id = 'mss_client_field_s',
      $title = 'Укажите uuid клиента:',
      $callback = array($this, 'field_callback'),
      $page = "mss_menu_settings",
      $section_id = "mss_client_section_s"
    );
  }


  function field_callback(){
    $setting_name = 'mss_client';
  	$setting_value = get_option( $setting_name );


    ?>
      <div class="mss_client_wrapper">
        <input id="<?php echo $setting_name; ?>" type="text" name="<?php echo $setting_name ?>" value="<?php echo $setting_value ?>">

      </div>
    <?php

  }
}
$TheMSSClientSelect = new MSSClientSelect;
