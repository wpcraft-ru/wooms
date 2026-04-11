<?php

namespace WooMS;

use function WooMS\request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

Products::init();


/**
 * @todo - move code from Products.php to this class
 */
final class Products
{
	public static function init()
	{
		add_action('admin_init', [self::class, 'add_settings'], 11);
	}

	/**
	 * add settings section
	 */
	public static function add_settings()
	{
		add_settings_section(
			'wooms_products_and_attributes',
			'Продукты и атрибуты',
			function(){
				echo '<p>Настройки для управления продуктами и атрибутами</p>';
			},
			'mss-settings'
		);
	}
}
