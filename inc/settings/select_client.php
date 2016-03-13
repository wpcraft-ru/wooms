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
        $title = 'Клиент для заказа',
        $callback = array($this, 'section_callback'),
        $page = 'mss_menu_settings'
      );
  }

  function section_callback(){
    ?>
      <p>Настройка параметров клиента.<br>
        Нужно ввести uuid клиента который будет подставляться в заказ из справочника МойСклад</p>
        <p>Нужно открыть карточку соответствующего клиента и взять параметр id из ссылки. <br/>Например online.moysklad.ru/app/#Company/view?id=754e2f4e-0537-11e5-90a2-8ecb001c4383, uuid будет 754e2f4e-0537-11e5-90a2-8ecb001c4383</p>
    <?php
  }


  //Добавлем опция Выбора склада
  function add_setting(){

    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_client'
    );

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

    //echo '234' . $setting_value;
    ?>

      <div class="<?php echo $setting_name ?>_wrapper">
        <input type="text" name="<?php echo $setting_name ?>" value="<?php echo $setting_value ?>">

      </div>
    <?php

  }
}
$TheMSSClientSelect = new MSSClientSelect;
