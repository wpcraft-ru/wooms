<?php

namespace WooMS;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Settings
 */
class MenuSettings
{

	/**
	 * The Init
	 */
	public static function init()
	{

		add_action(
			'admin_menu',
			function () {

				add_submenu_page(
					'moysklad',
					'Управление',
					'Управление',
					'manage_woocommerce',
					'moysklad'
				);
				add_submenu_page(
					'moysklad',
					'Настройки',
					'Настройки',
					'manage_options',
					'mss-settings',
					array(__CLASS__, 'display_settings')
				);
			},
			30
		);

		add_action('admin_init', array(__CLASS__, 'settings_general'), $priority = 10, $accepted_args = 1);
		add_action('admin_init', array(__CLASS__, 'settings_shedules'), $priority = 20, $accepted_args = 1);
		add_action('admin_init', array(__CLASS__, 'settings_other'), $priority = 100, $accepted_args = 1);

		add_action('wooms_settings_after_header', [__CLASS__, 'render_nav_menu']);
	}


	public static function render_nav_menu(){

		$nav_items = [
			'getting-started' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://github.com/wpcraft-ru/wooms/wiki/GettingStarted', 'С чего начать?'),
			'diagnostic' => sprintf('<a href="%s">%s</a>', admin_url('site-health.php'), 'Диагностика проблем'),
			'ms' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://online.moysklad.ru/', 'Вход в МойСклад'),
		];

		$nav_items = apply_filters('wooms_settings_nav_items', $nav_items);

		echo implode(' | ', $nav_items);

	}


	public static function settings_shedules()
	{

		add_settings_section('wooms_section_cron', 'Расписание синхронизации', null, 'mss-settings');

		register_setting('mss-settings', 'woomss_walker_cron_enabled');
		add_settings_field(
			$id = 'woomss_walker_cron_enabled',
			$title = 'Включить синхронизацию продуктов по расписанию',
			$callback = array(__CLASS__, 'woomss_walker_cron_display',),
			$page = 'mss-settings',
			$section = 'wooms_section_cron'
		);

		register_setting('mss-settings', 'woomss_walker_cron_timer');
		add_settings_field(
			$id = 'woomss_walker_cron_timer',
			$title = 'Перерыв синхронизации в часах',
			$callback = array(__CLASS__, 'woomss_walker_cron_timer_display',),
			$page = 'mss-settings',
			$section = 'wooms_section_cron'
		);
	}

	public static function woomss_walker_cron_timer_display()
	{
		$option_name = 'woomss_walker_cron_timer';
		printf('<input type="number" name="%s" value="%s"  />', $option_name, get_option($option_name, 24));
	}

	public static function woomss_walker_cron_display()
	{

		$option_name = 'woomss_walker_cron_enabled';
		printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked(1, get_option($option_name), false));
	}

	public static function settings_general()
	{

		add_settings_section('woomss_section_login', 'Данные для доступа МойСклад', null, 'mss-settings');

		register_setting('mss-settings', 'woomss_login');
		add_settings_field(
			$id = 'woomss_login',
			$title = 'Логин (admin@...)',
			$callback = array(__CLASS__, 'display_form_login',),
			$page = 'mss-settings',
			$section = 'woomss_section_login'
		);

		register_setting('mss-settings', 'woomss_pass');
		add_settings_field(
			$id = 'woomss_pass',
			$title = 'Пароль',
			$callback = array(__CLASS__, 'display_form_pass'),
			$page = 'mss-settings',
			$section = 'woomss_section_login'
		);
	}

	/**
	 * display_form_pass
	 */
	public static function display_form_pass()
	{
		printf('<input type="password" name="woomss_pass" value="%s"/>', get_option('woomss_pass'));
	}

	/**
	 * display_form_login
	 */
	public static function display_form_login()
	{
		printf('<input type="text" name="woomss_login" value="%s"/>', get_option('woomss_login'));

		printf('<p>%s</p>', 'Вводить нужно только логин и пароль здесь. На стороне МойСклад ничего настраивать не нужно.');
	}

	/**
	 * Settings - Other
	 */
	public static function settings_other()
	{
		add_settings_section('woomss_section_other', 'Прочие настройки', null, 'mss-settings');

		register_setting('mss-settings', 'wooms_use_uuid');
		add_settings_field(
			$id = 'wooms_use_uuid',
			$title = 'Использование UUID',
			$callback = array(__CLASS__, 'display_field_wooms_use_uuid'),
			$page = 'mss-settings',
			$section = 'woomss_section_other'
		);

		register_setting('mss-settings', 'wooms_replace_title');
		add_settings_field(
			$id = 'wooms_replace_title',
			$title = 'Замена заголовка при обновлении',
			$callback = array(__CLASS__, 'display_wooms_replace_title'),
			$page = 'mss-settings',
			$section = 'woomss_section_other'
		);

		register_setting('mss-settings', 'wooms_replace_description');
		add_settings_field(
			$id = 'wooms_replace_description',
			$title = 'Замена описания при обновлении',
			$callback = array(__CLASS__, 'display_wooms_replace_desc'),
			$page = 'mss-settings',
			$section = 'woomss_section_other'
		);
	}

	/**
	 * display_wooms_replace_desc
	 */
	public static function display_wooms_replace_desc()
	{

		$option_name = 'wooms_replace_description';
		printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked(1, get_option($option_name), false));
?>
		<p>
			<small>Если включить опцию, то плагин будет обновлять описание продукта из МойСклад всегда.</small>
		</p>
	<?php

	}

	/**
	 * display_wooms_replace_title
	 */
	public static function display_wooms_replace_title()
	{

		$option_name = 'wooms_replace_title';
		printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked(1, get_option($option_name), false));
	?>
		<p>
			<small>Если включить опцию, то плагин будет обновлять заголовки продукта из МойСклад. Иначе при наличии заголовока он не будет обновлен.</small>
		</p>
	<?php

	}

	/**
	 * display_field_wooms_use_uuid
	 */
	public static function display_field_wooms_use_uuid()
	{

		$option_name = 'wooms_use_uuid';
		printf('<input type="checkbox" name="%s" value="1" %s />', $option_name, checked(1, get_option($option_name), false));
	?>

		<p><strong>Если товары не попадают из МойСклад на сайт - попробуйте включить эту опцию.</strong></p>
		<p>
			<small>По умолчанию используется связь продуктов по артикулу. Это позволяет обеспечить синхронизацию без удаления всех продуктов с сайта при их наличии. Но без артикула
				товары не будут синхронизироваться. Если товаров на сайте нет, либо их можно удалить без вреда, то можно включить синхронизацию по UUID. В этом случае артикулы
				будут не нужны. <br />При создании записи о продукте произойдет связка по UUID (meta_key = wooms_id)
			</small>
		</p>
	<?php

	}

	/**
	 * display_settings
	 */
	public static function display_settings()
	{

	?>
		<form method="POST" action="options.php">
		
			<h1>Настройки интеграции МойСклад</h1>

			<?php do_action('wooms_settings_after_header') ?>
		
			<?php

			settings_fields('mss-settings');
			do_settings_sections('mss-settings');
			submit_button();
			?>
		</form>


<?php

		printf('<p><a href="%s">Управление синхронизацией</a></p>', admin_url('admin.php?page=moysklad'));
		printf('<p><a href="%s" target="_blank">Расширенная версия с дополнительными возможностями</a></p>', "https://wpcraft.ru/product/wooms-extra/");
		printf('<p><a href="%s" target="_blank">Предложения по улучшению и запросы на доработку</a></p>', "https://github.com/wpcraft-ru/wooms/issues");
		printf('<p><a href="%s" target="_blank">Рекомендуемые хостинги</a></p>', "https://wpcraft.ru/wordpress/hosting/");
		printf('<p><a href="%s" target="_blank">Сопровождение магазинов и консалтинг</a></p>', "https://wpcraft.ru/wordpress-woocommerce-mentoring/");
		printf('<p><a href="%s" target="_blank">Помощь и техическая поддержка</a></p>', "https://wpcraft.ru/contacts/");
	}
}

MenuSettings::init();
