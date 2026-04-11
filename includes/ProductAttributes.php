<?php

namespace WooMS;

use function WooMS\request;


if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Update attributes for products from custom fields MoySklad
 */
class ProductAttributes
{
	public static function init()
	{
		add_filter('wooms_product_update', array(self::class, 'update_product'), 10, 2);

		add_filter('wooms_attributes', [self::class, 'update_country'], 10, 3);
		add_filter('wooms_attributes', [self::class, 'save_other_attributes'], 10, 3);
		add_filter('wooms_allow_data_types_for_attributes', [self::class, 'add_text'], 10, 1);
		add_action('admin_init', [self::class, 'add_settings'], 150);

		add_filter('testeroid_tests', function ($tests) {

			$tests['ProductAttributes'] = function () {
				$path = 'entity/product?filter=code=12300001182351';
				$data = request($path);
				$row = $data['rows'][0] ?? null;
				$product_id = \WooMS\Products\product_update($row);
				$product = wc_get_product($product_id);
				// dd($product);
				// exit;
				return true;
			};

			return $tests;
		});
	}


	/**
	 * fix https://github.com/wpcraft-ru/wooms/issues/299
	 */
	public static function add_text($atts)
	{

		$atts[] = 'text';

		return $atts;
	}


	/**
	 * Update product
	 */
	public static function update_product($product, $item)
	{
		if (! self::is_enabled()) {
			return $product;
		}
		$product_id = $product->get_id();

		if (! empty($item['weight'])) {
			$product->set_weight($item['weight']);
		}

		if (! empty($item['attributes'])) {
			foreach ($item['attributes'] as $attribute) {
				if (empty($attribute['name'])) {
					continue;
				}

				if ($attribute['name'] == 'Ширина') {
					$product->set_width($attribute['value']);
					continue;
				}

				if ($attribute['name'] == 'Высота') {
					$product->set_height($attribute['value']);
					continue;
				}

				if ($attribute['name'] == 'Длина') {
					$product->set_length($attribute['value']);
					continue;
				}
			}
		}


		$product_attributes = $product->get_attributes('edit');

		if (empty($product_attributes)) {
			$product_attributes = array();
		}

		$product_attributes = apply_filters('wooms_attributes', $product_attributes, $product_id, $item);

		do_action('wooms_logger', __CLASS__,
			sprintf('Артибуты Продукта: %s (%s) сохранены', $product->get_title(), $product->get_id()),
			$product_attributes
		);

		$product->set_attributes($product_attributes);

		return $product;
	}

	/**
	 * Get attribute id by label
	 * or false
	 */
	public static function get_attribute_id_by_label($label = '')
	{
		if (empty($label)) {
			return false;
		}

		$attr_taxonomies = wc_get_attribute_taxonomies();
		if (empty($attr_taxonomies)) {
			return false;
		}

		if (! is_array($attr_taxonomies)) {
			return false;
		}

		foreach ($attr_taxonomies as $attr) {
			if ($attr->attribute_label == $label) {
				return (int) $attr->attribute_id;
			}
		}

		return false;
	}

	/**
	 * Сохраняем прочие атрибуты, не попавшивае под базовые условия
	 */
	public static function save_other_attributes($product_attributes, $product_id, $value)
	{
		if (! empty($value['attributes'])) {
			foreach ($value['attributes'] as $attribute) {
				if (empty($attribute['name'])) {
					continue;
				}

				if (in_array($attribute['name'], ['Ширина', 'Высота', 'Длина', 'Страна'])) {
					continue;
				}

				//Если это не число и не строка - пропуск, тк другие типы надо обрабатывать иначе
				$allow_data_type_for_attribures = array('string', 'number', 'customentity');

				/**
				 * add new type for attributes
				 *
				 * @issue https://github.com/wpcraft-ru/wooms/issues/184
				 */
				$allow_data_type_for_attribures = apply_filters('wooms_allow_data_types_for_attributes', $allow_data_type_for_attribures);
				if (! in_array($attribute['type'], $allow_data_type_for_attribures)) {
					continue;
				}

				if (! empty($attribute['value']['name'])) {
					$value = $attribute['value']['name'];
				} else {
					$value = $attribute['value'];
				}

				$attribute_name = $attribute['name'];

				$attribute_taxonomy_id = self::get_attribute_id_by_label($attribute_name);
				if ($attribute_taxonomy_id) {
					$taxonomy_slug = wc_attribute_taxonomy_name_by_id($attribute_taxonomy_id);
				}

				$attribute_slug = sanitize_title($attribute_name);

				if (empty($attribute_taxonomy_id)) {

					$attribute_object = new \WC_Product_Attribute();
					$attribute_object->set_name($attribute_name);
					$attribute_object->set_options(array($value));
					$attribute_object->set_position(0);
					$attribute_object->set_visible(1);
					$product_attributes[$attribute_slug] = $attribute_object;

				} else {

					//Очищаем индивидуальный атрибут с таким именем если есть
					if (isset($product_attributes[$attribute_slug])) {
						unset($product_attributes[$attribute_slug]);
					}

					$attribute_object = new \WC_Product_Attribute();
					$attribute_object->set_id($attribute_taxonomy_id);
					$attribute_object->set_name($taxonomy_slug);
					$attribute_object->set_options(array($value));
					$attribute_object->set_position(0);
					$attribute_object->set_visible(1);
					$product_attributes[$taxonomy_slug] = $attribute_object;
				}

			}
		}
		return $product_attributes;
	}


	/**
	 * Country - update
	 */
	public static function update_country($product_attributes, $product_id, $value)
	{
		if (empty($value['country']["meta"]["href"])) {
			return $product_attributes;
		} else {
			$url = $value['country']["meta"]["href"];
		}

		$data_api = request($url);

		if (empty($data_api["name"])) {
			return $product_attributes;
		} else {
			$country = sanitize_text_field($data_api["name"]);

			$attribute_object = new \WC_Product_Attribute();
			$attribute_object->set_name("Страна");
			$attribute_object->set_options([$country]);
			$attribute_object->set_position('0');
			$attribute_object->set_visible(1);
			$attribute_object->set_variation(0);
			$product_attributes[] = $attribute_object;
		}

		return $product_attributes;
	}


	/**
	 * Settings UI
	 */
	public static function add_settings()
	{
		add_settings_field(
			$id = 'wooms_attr_enabled',
			$title = 'Синхронизация доп. полей как атрибутов',
			$callback = [self::class, 'render_settings_fields'],
			$page = 'mss-settings',
			$section = 'wooms_products_and_attributes'
		);
	}

	public static function render_settings_fields()
	{
		self::check_option_and_delete();
		$enable = Settings::getValue('wooms_attributes_sync_enabled');
		$enable_field_name = Settings::getFieldName('wooms_attributes_sync_enabled');
		$sync_as_taxonomy = Settings::getValue('wooms_attributes_sync_as_taxonomy');
		$sync_as_taxonomy_field_name = Settings::getFieldName('wooms_attributes_sync_as_taxonomy');

		printf('<input id="wooms_attributes_sync_enabled" type="checkbox" name="%s" value="1" %s />', $enable_field_name, checked(1, $enable, false));
		printf('<label for="wooms_attributes_sync_enabled">%s</label>', 'Включить синхронизацию доп. полей как атрибутов');

		// echo '<hr/>';
		// printf('<input id="wooms_attributes_sync_as_taxonomy" type="checkbox" name="%s" value="1" %s />', $sync_as_taxonomy_field_name, checked(1, $sync_as_taxonomy, false));
		// printf('<label for="wooms_attributes_sync_as_taxonomy">%s</label>', 'Синхронизировать доп. поля как общие атрибуты через таксономии');

		echo '<hr/>';
		printf('<p>%s</p>', 'Вес, Длина, Ширина, Высота - сохраняются в базовые поля продукта, остальные поля как индивидуальные атрибуты.');

		printf('<p>По умолчанию атрибуты сохраняются как индивидуальные, но если добавить атрибут с таким же названием в общие, то он будет сохраняться как общий. <a href="%s">Редактировать общие атрибуты</a></p>',  admin_url('edit.php?post_type=product&page=product_attributes'));

		printf('<p><strong>%s</strong></p>', 'Тестовый режим. Не включайте эту функцию на реальном сайте, пока не проверите ее на тестовой копии сайта.');

	}

	/**
	 * check if enabled
	 */
	public static function is_enabled()
	{
		return (bool) Settings::getValue('wooms_attributes_sync_enabled');
	}

	// check option wooms_attr_enabled and if exist - delete - like migration 260311
	public static function check_option_and_delete()
	{
		$value = get_option('wooms_attr_enabled');
		if (empty($value)) {
			return;
		}
		Settings::setValue('wooms_attributes_sync_enabled', $value);
		delete_option('wooms_attr_enabled');
	}
}

ProductAttributes::init();
