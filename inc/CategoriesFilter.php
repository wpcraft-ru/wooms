<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Import Product Categories from MoySklad
 */
class CategoriesFilter
{

  /**
   * WooMS_Import_Product_Categories constructor.
   */
  public static function init()
  {

    add_action('admin_init', array(__CLASS__, 'settings_init'), 50);
    add_filter('wooms_url_get_products_filters', array(__CLASS__, 'product_add_filter_by_folder'), 10);
    add_filter('wooms_url_get_bundle_filter', array(__CLASS__, 'product_add_filter_by_folder'), 10);
    add_filter('wooms_url_get_service_filter', array(__CLASS__, 'product_add_filter_by_folder'), 10);

  }


  /**
   * Добавляем фильтр по папке
   * Если выбрана группа для синка
   * Use $url_api = apply_filters('wooms_url_get_products', $url);
   */
  public static function product_add_filter_by_folder($filters)
  {
    if ( ! $groups = get_option('wooms_set_folders')) {
      return $filters;
    }

    $groups = explode(',', $groups);

    if(empty($groups)){
      return $filters;
    }

    foreach($groups as $group){
      $filters[] = 'pathName~=' . trim($group);
    }

    return $filters;
  }


  /**
   * Settings UI
   */
  public static function settings_init()
  {

    register_setting('mss-settings', 'wooms_set_folders');
    add_settings_field(
      $name = 'wooms_set_folders',
      $title = 'Группы товаров для фильтрации',
      $render = function($args){
        printf('<input type="text" name="%s" value="%s" size="50" />', $args['key'], $args['value']);
        printf('<p><small>%s</small></p>', 
          'Тут можно указать группы для фильтрации товаров через запятую. Например: "Мебель/Диваны,Пицца,Одежда/Обувь/Ботинки"'
        );
      },
      $setings = 'mss-settings',
      $group = 'wooms_product_cat',
      $arts = [
        'key' => 'wooms_set_folders',
        'value' => get_option('wooms_set_folders'),
      ]
    );

  }

}

CategoriesFilter::init();
