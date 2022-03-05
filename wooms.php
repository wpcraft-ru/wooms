<?php
/**
 * Plugin Name: WooMS
 * Plugin URI: https://wpcraft.ru/product/wooms/
 * Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
 * Author: WPCraft
 * Author URI: https://wpcraft.ru/
 * Developer: WPCraft
 * Developer URI: https://wpcraft.ru/
 * Text Domain: wooms
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * PHP requires at least: 5.6
 * WC requires at least: 5.0
 * WP requires at least: 5.0
 * Tested up to: 5.9
 * WC tested up to: 6.2.0
 * 
 * Version: 9.0
 */

namespace WooMS;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Add hook for activate plugin
 */
register_activation_hook(__FILE__, function () {
  do_action('wooms_activate');
});

register_deactivation_hook(__FILE__, function () {
  do_action('wooms_deactivate');
});


require_once __DIR__ . '/functions.php';

if( wooms_can_start() ){
    
  /**
   * Этот класс должен работать до хука plugins_loaded
   * Птм что иначе хук wooms_activate не срабатывает
   */
  require_once __DIR__ . '/inc/Logger.php';
  require_once __DIR__ . '/inc/MSImagesTrait.php';

  require_once __DIR__ . '/inc/LoaderIcon.php';
  require_once __DIR__ . '/inc/MenuSettings.php';
  require_once __DIR__ . '/inc/MenuTools.php';
  require_once __DIR__ . '/inc/MetaColumn.php';

  require_once __DIR__ . '/inc/ProductsWalker.php';
  ProductsWalker::init();

  require_once __DIR__ . '/inc/ProductsPrices.php';
  ProductsPrices::init();


  require_once __DIR__ . '/inc/AbstractWalker.php';
  require_once __DIR__ . '/inc/Orders.php';
  require_once __DIR__ . '/inc/ProductsServices.php';
  require_once __DIR__ . '/inc/ProductsCategories.php';
  require_once __DIR__ . '/inc/ProductsHiding.php';
  require_once __DIR__ . '/inc/ProductGallery.php';
  require_once __DIR__ . '/inc/ProductImage.php';
  require_once __DIR__ . '/inc/SiteHealth.php';
  require_once __DIR__ . '/inc/SiteHealthDebugSection.php';
  require_once __DIR__ . '/inc/LoggerProductSave.php';
  require_once __DIR__ . '/inc/UseCodeAsArticle.php';
  require_once __DIR__ . '/inc/ProductAttributes.php';
  require_once __DIR__ . '/inc/ProductSingleSync.php';
  require_once __DIR__ . '/inc/ProductStocks.php';
  require_once __DIR__ . '/inc/ProductGrouped.php';
  require_once __DIR__ . '/inc/ProductVariable.php';
  require_once __DIR__ . '/inc/ProductVariableImage.php';
  require_once __DIR__ . '/inc/VariationsHider.php';
  require_once __DIR__ . '/inc/OrderUpdateFromMoySklad.php';
  require_once __DIR__ . '/inc/OrderShipment.php';
  require_once __DIR__ . '/inc/OrderNotes.php';
  require_once __DIR__ . '/inc/OrderStatusesFromSite.php';
  require_once __DIR__ . '/inc/TaxSupport.php';
  require_once __DIR__ . '/inc/CategoriesFilter.php';
  require_once __DIR__ . '/inc/SalePrices.php';
  require_once __DIR__ . '/inc/SendWarehouse.php';
  require_once __DIR__ . '/inc/SiteHealthXT.php';
  require_once __DIR__ . '/inc/SiteHealthWebHooks.php';
  require_once __DIR__ . '/inc/CurrencyConverter.php';
  require_once __DIR__ . '/inc/OrderNumber.php';
  require_once __DIR__ . '/inc/ProductsExclusion.php';

  // require_once __DIR__ . '/migrations/7-2.php';

  add_action('plugins_loaded', function () {
    
    load_plugin_textdomain('wooms', false, dirname(plugin_basename(__FILE__)) . '/languages/');
  	add_action('save_post', 'wooms_id_check_if_unique', 10, 3);
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\' . 'admin_styles');

  });


}

add_filter('wooms_xt_load', '__return_false');
add_filter("plugin_action_links_" . plugin_basename(__FILE__), __NAMESPACE__ . '\\' . 'plugin_add_settings_link');
add_filter('plugin_row_meta', __NAMESPACE__ . '\\' . 'add_wooms_plugin_row_meta', 10, 2);
add_action('after_plugin_row_wooms-extra/wooms-extra.php', __NAMESPACE__ . '\\' . 'xt_plugin_update_message', 10, 2);

function xt_plugin_update_message($data, $response)
  {

    echo 1;
    $wp_list_table = _get_list_table('WP_Plugins_List_Table');

    printf(
      '<tr class="plugin-update-tr">
        <td colspan="%s" class="plugin-update update-message notice inline notice-warning notice-alt">
          <div class="update-message">
            <span>Этот плагин следует удалить: <a href="https://github.com/wpcraft-ru/wooms/wiki/2022" target="_blank">https://github.com/wpcraft-ru/wooms/wiki/2022</a></span>
          </div>
        </td>
      </tr>',
      $wp_list_table->get_column_count()
    );
}


/**
 * Add Settings link in pligins list
 */
function plugin_add_settings_link($links)
{
  $settings_link = '<a href="admin.php?page=mss-settings">Настройки</a>';
  $xt_link = '<a href="https://github.com/wpcraft-ru/wooms/wiki/2022" target="_blank">Изменения 2022</a>';
  array_unshift($links, $xt_link);
  array_unshift($links, $settings_link);
  return $links;
}


/**
 * Add GettingStarted link in row meta at pligins list
 */
function add_wooms_plugin_row_meta($links, $file)
{
  if ( strpos( $file, 'wooms.php' ) !== false ) {
    $new_links = array(
      '<a style="color:green;" href="https://github.com/wpcraft-ru/wooms/wiki/GettingStarted" target="_blank"><strong>Руководство по началу работы</strong></a>'
      );
    
    $links = array_merge( $links, $new_links );
  }
  
  return $links;
}


/**
 * Styles for Dashboard
 *
 * @return void
 */
function admin_styles() {

  $admin_style = plugin_dir_url( __FILE__ ) . 'css/admin.css';

  wp_enqueue_style( 'wooms_styles', $admin_style, array() );

}
