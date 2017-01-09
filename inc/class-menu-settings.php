<?php

class woomss {
  function __construct(){

    add_action('admin_menu', function () {
        add_options_page(
          $page_title = 'МойСклад',
          $menu_title = "МойСклад",
          $capability = 'manage_options',
          $menu_slug = 'mss-settings',
          $function = array($this, 'mss_settings_callback')
        );
    });

    add_action( 'admin_init', array($this, 'settings_init'), $priority = 10, $accepted_args = 1 );
  }

  function settings_init(){

    add_settings_section(
    	'woomss_section_login',
    	'Данные для доступа МойСклад',
    	null,
    	'mss-settings'
    );

    add_settings_field(
      $id = 'woomss_login',
      $title = 'Логин (admin@...)',
      $callback = [$this, 'display_woomss_login'],
      $page = 'mss-settings',
      $section = 'woomss_section_login'
    );

    add_settings_field(
      $id = 'woomss_pass',
      $title = 'Пароль',
      $callback = [$this, 'display_woomss_pass'],
      $page = 'mss-settings',
      $section = 'woomss_section_login'
    );

    register_setting('woomss_section_login', 'woomss_login');
    register_setting('woomss_section_login', 'woomss_pass');

  }

  function display_woomss_pass(){
    printf('<input type="password" name="woomss_pass" value="%s"/>',get_option('woomss_pass'));
  }

  function display_woomss_login(){
    printf('<input type="text" name="woomss_login" value="%s"/>',get_option('woomss_login'));
  }

  function mss_settings_callback(){
    ?>

    <form method="POST" action="options.php">
      <h1>Настройки интеграции МойСклад</h1>
      <?php
        settings_fields( 'woomss_section_login' );
        do_settings_sections( 'mss-settings' );
        submit_button();
      ?>
    </form>
    <?php
  }



}
new woomss;
