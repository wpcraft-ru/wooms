<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WooMS_Settings {
	
	function __construct() {
		
		add_action( 'admin_menu', function () {
			
			add_options_page( $page_title = 'МойСклад', $menu_title = "МойСклад", $capability = 'manage_options', $menu_slug = 'mss-settings', $function = array(
				$this,
				'mss_settings_callback',
			) );
		} );
		
		add_action( 'admin_init', array( $this, 'settings_general' ), $priority = 10, $accepted_args = 1 );
		add_action( 'admin_init', array( $this, 'settings_shedules' ), $priority = 20, $accepted_args = 1 );
		add_action( 'admin_init', array( $this, 'settings_other' ), $priority = 100, $accepted_args = 1 );
		
	}
	
	
	public function settings_shedules() {
		
		add_settings_section( 'wooms_section_cron', 'Расписание синхронизации', null, 'mss-settings' );
		
		register_setting( 'mss-settings', 'woomss_walker_cron_enabled' );
		add_settings_field( $id = 'woomss_walker_cron_enabled', $title = 'Включить синхронизацию продуктов по расписанию', $callback = array(
			$this,
			'woomss_walker_cron_display',
		), $page = 'mss-settings', $section = 'wooms_section_cron' );
		
		register_setting( 'mss-settings', 'woomss_walker_cron_timer' );
		add_settings_field( $id = 'woomss_walker_cron_timer', $title = 'Перерыв синхронизации в часах', $callback = array(
			$this,
			'woomss_walker_cron_timer_display',
		), $page = 'mss-settings', $section = 'wooms_section_cron' );
		
	}
	
	public function woomss_walker_cron_timer_display() {
		
		$option_name = 'woomss_walker_cron_timer';
		printf( '<input type="number" name="%s" value="%s"  />', $option_name, get_option( $option_name, 24 ) );
	}
	
	public function woomss_walker_cron_display() {
		
		$option_name = 'woomss_walker_cron_enabled';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option( $option_name ), false ) );
		
	}
	
	public function settings_general() {
		
		add_settings_section( 'woomss_section_login', 'Данные для доступа МойСклад', null, 'mss-settings' );
		
		register_setting( 'mss-settings', 'woomss_login' );
		add_settings_field( $id = 'woomss_login', $title = 'Логин (admin@...)', $callback = array(
			$this,
			'woomss_login_display',
		), $page = 'mss-settings', $section = 'woomss_section_login' );
		
		register_setting( 'mss-settings', 'woomss_pass' );
		add_settings_field( $id = 'woomss_pass', $title = 'Пароль', $callback = array( $this, 'woomss_pass_display' ), $page = 'mss-settings', $section = 'woomss_section_login' );
	}
	
	public function woomss_pass_display() {
		
		printf( '<input type="password" name="woomss_pass" value="%s"/>', get_option( 'woomss_pass' ) );
	}
	
	public function woomss_login_display() {
		
		printf( '<input type="text" name="woomss_login" value="%s"/>', get_option( 'woomss_login' ) );
	}
	
	/**
	 * Settings - Other
	 */
	public function settings_other() {
		
		add_settings_section( 'woomss_section_other', 'Прочие настройки', null, 'mss-settings' );
		
		register_setting( 'mss-settings', 'wooms_use_uuid' );
		add_settings_field( $id = 'wooms_use_uuid', $title = 'Использование UUID', $callback = array(
			$this,
			'display_field_wooms_use_uuid',
		), $page = 'mss-settings', $section = 'woomss_section_other' );
		
		register_setting( 'mss-settings', 'wooms_replace_title' );
		add_settings_field( $id = 'wooms_replace_title', $title = 'Замена заголовока при обновлении', $callback = array(
			$this,
			'display_wooms_replace_title',
		), $page = 'mss-settings', $section = 'woomss_section_other' );
		
		register_setting( 'mss-settings', 'wooms_replace_description' );
		add_settings_field( $id = 'wooms_replace_description', $title = 'Замена описания при обновлении', $callback = array(
			$this,
			'display_wooms_replace_desc',
		), $page = 'mss-settings', $section = 'woomss_section_other' );
	}
	
	public function display_wooms_replace_desc() {
		
		$option_name = 'wooms_replace_description';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option( $option_name ), false ) );
		?>
		<p>
			<small>Если включить опцию, то плагин будет обновлять описание продукта из МойСклад всегда.</small>
		</p>
		<?php
		
	}
	
	public function display_wooms_replace_title() {
		
		$option_name = 'wooms_replace_title';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option( $option_name ), false ) );
		?>
		<p>
			<small>Если включить опцию, то плагин будет обновлять заголовки продукта из МойСклад. Иначе при наличии заголовока он не будет обновлен.</small>
		</p>
		<?php
		
	}
	
	
	public function display_field_wooms_use_uuid() {
		
		$option_name = 'wooms_use_uuid';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option_name, checked( 1, get_option( $option_name ), false ) );
		?>
		
		<p><strong>Если товары не попадают из МойСклад на сайт - попробуйте включить эту опцию.</strong></p>
		<p>
			<small>По умолчанию используется связь продуктов по артикулу. Это позволяет обеспечить синхронизацию без удаления всех продуктов с сайта при их наличии. Но без артикула
				товары не будут синхронизироваться. Если товаров на сайте нет, либо их можно удалить без вреда, то можно включить синхронизацию по UUID. В этом случае артикулы
				будут не нужны. <br/>При создании записи о продукте произойдет связка по UUID (meta_key = wooms_id)
			</small>
		</p>
		<?php
		
	}
	
	
	public function mss_settings_callback() {
		
		?>
		<form method="POST" action="options.php">
			<style media="screen" scoped="true">
				.lead {
					background-color: blanchedalmond;
					padding: 10px;
				}
			</style>
			<h1>Настройки интеграции МойСклад</h1>
			<p><strong class="lead">Внимание! Настройки следует сначала выполнять на тестовой копии сайта. Только после тестов, переносить конфигурацию на рабочий сайт с
					клиентами.</strong></p>
			<?php
			
			settings_fields( 'mss-settings' );
			do_settings_sections( 'mss-settings' );
			submit_button();
			?>
		</form>
		
		
		<?php
		
		printf( '<p><a href="%s">Управление синхронизацией</a></p>', admin_url( 'tools.php?page=moysklad' ) );
		printf( '<p><a href="%s" target="_blank">Расширенная версия с дополнительными возможностями</a></p>', "https://wpcraft.ru/product/wooms-extra/" );
		printf( '<p><a href="%s" target="_blank">Предложения по улучшению и запросы на доработку</a></p>', "https://github.com/wpcraft-ru/wooms/issues" );
		printf( '<p><a href="%s" target="_blank">Помощь и техическая поддержка</a></p>', "https://wpcraft.ru/contacts/" );
	}
	
}

new WooMS_Settings;
