<?php
/*
 * Добавляем страницу настроек в WordPress
 */

class SettingsMoySkladS {
  function __construct(){
     add_action('admin_menu', array($this, 'add_menu_pages'));
     add_action('admin_init', array($this, 'add_section_commone'));
     add_action('admin_init', array($this, 'add_setting_login_mss'));
     add_action('admin_init', array($this, 'add_setting_pass_mss'));


  }

  //Добавляем страницу настроек в консоль
  function add_menu_pages(){

     add_menu_page(
        $page_title = 'Настройка МойСклад',
        $menu_title = 'МойСклад',
        $capability = 'manage_options',
        $menu_slug = 'mss_menu_settings',
        $function = array($this, 'mss_menu_settings_callback'),
        $icon_url = 'dashicons-cart',
        $position = 100
     );

     add_submenu_page(
        $parent_slug = 'tools.php',
        $page_title = "Инструменты МойСклад",
        $menu_title = "МойСклад",
        $capability = "manage_options",
        $menu_slug = "mss-tools",
        $function = array($this, 'mss_tools_callback')
     );
  }

  function mss_tools_callback(){
    ?>
    <div class="wrap">
        <h1>Инструменты МойСклад</h1>
        <p>Настройки <a href='<?php echo admin_url("admin.php?page=mss_menu_settings");  ?>'>тут</a></p>
        <?php do_action( $tag = "add_section_mss_tool" )?>
    </div>
    <?php
  }

  function mss_menu_settings_callback() {
     ?>
     <div class="wrap">
         <h1>Настройки МойСклад</h1>
         <p>Инструменты <a href='<?php echo admin_url("tools.php?page=mss-tools");  ?>'>тут</a></p>
         <form action="options.php" method="POST">
             <?php settings_fields( $option_group = 'mss_options' ); ?>
             <?php do_settings_sections( $page = 'mss_menu_settings' ); ?>
             <?php submit_button(); ?>
         </form>
     </div>
     <?php
  }

  function add_section_commone(){
      /*
    	Добавляем секцию на страницу настроек
    	*/
    	add_settings_section(
    		$id = 'mss_login_section_s',
    		$title = 'Данные авторизации МойСклад',
    		$callback = function(){echo 'Секция для ввода данных авторизации';},
    		$page = 'mss_menu_settings'
    	);

  }

  //Добавлем опция Логин для МойСклад
  function add_setting_login_mss(){
    //Опция Логин
    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_login_s' );

    add_settings_field(
      $id = 'mss_login_field_s',
      $title = 'Логин',
      $callback = array($this, 'mss_login_s_callback'),
      $settings_fields_key = "mss_menu_settings",
      $section_id = "mss_login_section_s"
    );
  }
  function mss_login_s_callback(){
    $setting_name = 'mss_login_s';
  	$setting_value = get_option( $setting_name );
  	?>
    	<div class="mss_login_s_wrapper">
        <input id="<?php echo $setting_name; ?>" type="text" name="<?php echo $setting_name ?>" value="<?php echo $setting_value ?>">
    	</div>
  	<?php
  }


  //Добавлем опция Пароль для МойСклад
  function add_setting_pass_mss(){
    //Опция Логин
    register_setting(
      $settings_fields_key = 'mss_options',
      $name = 'mss_pass_s' );

    add_settings_field(
      $id = 'mss_pass_field_s',
      $title = 'Пароль',
      $callback = array($this, 'mss_pass_s_callback'),
      $settings_fields_key = "mss_menu_settings",
      $section_id = "mss_login_section_s"
    );
  }
  function mss_pass_s_callback(){
    $setting_name = 'mss_pass_s';
    $setting_value = get_option( $setting_name );
    ?>
      <div class="mss_pass_s_wrapper">
        <input id="<?php echo $setting_name; ?>" type="password" name="<?php echo $setting_name ?>" value="<?php echo $setting_value ?>">
      </div>
    <?php
  }

 }
 $TheSettingsMoySkladS = new SettingsMoySkladS();
