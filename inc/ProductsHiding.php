<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Hide old products
 */
class ProductsHiding
{

  public static $walker_hook = 'wooms_schedule_clear_old_products_walker';

  /**
   * The init
   */
  public static function init()
  {
    add_action('init', array(__CLASS__, 'add_schedule_hook'));
    add_action('wooms_schedule_clear_old_products_walker', array(__CLASS__, 'walker_starter'));

    add_action('wooms_products_state_before', array(__CLASS__, 'display_state'));

    add_action('admin_init', array(__CLASS__, 'settings_init'));

    add_action('wooms_main_walker_finish', array(__CLASS__, 'add_task_for_hide'));
    add_action('wooms_main_walker_started', array(__CLASS__, 'remove_task_for_hide'));

    add_action('wooms_recount_terms', array( __CLASS__, 'recount_terms' ));

  }


  /**
   * recount_terms
   */
  public static function recount_terms(){
    $product_cats = get_terms(
      'product_cat', array(
        'hide_empty' => false,
        'fields'     => 'id=>parent',
      )
    );
    _wc_term_recount( $product_cats, get_taxonomy( 'product_cat' ), true, false );

    $product_tags = get_terms(
      'product_tag', array(
        'hide_empty' => false,
        'fields'     => 'id=>parent',
      )
    );
    _wc_term_recount( $product_tags, get_taxonomy( 'product_tag' ), true, false );
  }


  public static function add_task_for_hide(){
    set_transient('wooms_product_need_hide', 1);
    self::add_schedule_hook();
  }

  public static function remove_task_for_hide(){
    delete_transient('wooms_product_need_hide');
  }


  public static function is_wait(){

    if(as_next_scheduled_action('wooms_products_walker_batch') ){
      return true;
    }


    if(empty(get_transient('wooms_product_need_hide'))){
      return true;
    }

    return false;
  
  }

  /**
   * Hook restart
   */
  public static function add_schedule_hook($force = false)
  {

    if( self::is_disable()){
      return;
    }

    if(self::is_wait()){
      return;
    }

    if(as_next_scheduled_action(self::$walker_hook) && ! $force){
      return;
    }
  
    // Adding schedule hook
    as_schedule_single_action( time() + 5, self::$walker_hook, [], 'WooMS' );

  }


  /**
   * Starter walker if option enabled
   */
  public static function walker_starter()
  {
    if( self::is_disable()){
      return;
    }

    self::set_hidden_old_product();
  }


  /**
   * display_state
   */
  public static function display_state()
  {
    if( self::is_disable()){
      return;
    }

    $strings = [];

    // self::$walker_hook
    if(as_next_scheduled_action(self::$walker_hook)){
      $strings[] = 'Продукты скрываются в фоне очередями';
    } 
    
    if(self::is_wait()){
      $strings[] = 'Обработчик ожидает задач';
    }

    $strings[] = sprintf('Очередь задач: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=action-scheduler&s=wooms_schedule_clear_old_products_walker&orderby=schedule&order=desc'));
        
        
    if(defined('WC_LOG_HANDLER') && 'WC_Log_Handler_DB' == WC_LOG_HANDLER){
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs&source=WooMS-ProductsHiding'));
    } else {
      $strings[] = sprintf('Журнал обработки: <a href="%s">открыть</a>', admin_url('admin.php?page=wc-status&tab=logs'));
    }

    ?>
    <hr>
    <div>
      <br>
      <strong>Скрытие продуктов:</strong>
      <ul>
        <li>
        <?= implode('</li><li>', $strings); ?>
        </li>
      </ul>
    </div>
<?php 
  }

  /**
   * Adding hiding attributes to products
   */
  public static function set_hidden_old_product()
  {
    do_action(
      'wooms_logger',
      __CLASS__,
      sprintf('Проверка очереди скрытия продуктов: %s', date("Y-m-d H:i:s"))
    );

    $products = self::get_products_old_session();

    if (empty($products)) {
      

      delete_transient('wooms_product_need_hide');

      do_action('wooms_recount_terms');
      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Финишь скрытия продуктов: %s', date("Y-m-d H:i:s"))
      );
      return;
    }

    foreach ($products as $product_id) {
      $product = wc_get_product($product_id);

      if ($product->get_type() == 'variable') {
        $product->set_manage_stock('yes');
      }

      // $product->set_status( 'draft' );
      $product->set_catalog_visibility('hidden');
      // $product->set_stock_status( 'outofstock' );
      $product->save();

      do_action(
        'wooms_logger',
        __CLASS__,
        sprintf('Скрытие продукта: %s', $product_id)
      );
    }

    self::add_schedule_hook(true);

    do_action('wooms_hide_old_product', $products);
  }

  /**
   * Obtaining products with specific attributes
   *
   * @param int $offset
   *
   * @return array
   */
  public static function get_products_old_session()
  {
    $session = self::get_session();
    if (empty($session)) {
      return false;
    }

    $args = array(
      'post_type'   => 'product',
      'numberposts' => 30,
      'fields'      => 'ids',
      'tax_query'   => array(
        array(
          'taxonomy'  => 'product_visibility',
          'terms'     => array('exclude-from-catalog', 'exclude-from-search'),
          'field'     => 'name',
          'operator'  => 'NOT IN',
        ),
      ),
      'meta_query'  => array(
        array(
          'key'     => 'wooms_session_id',
          'value'   => $session,
          'compare' => '!=',
        ),
        array(
          'key'     => 'wooms_id',
          'compare' => 'EXISTS',
        ),
      ),

    );

    return get_posts($args);
  }

  /**
   * Method for getting the value of an option
   *
   * @return bool|mixed
   */
  public static function get_session()
  {
    $session_id = get_option('wooms_session_id');
    if (empty($session_id)) {
      return false;
    }

    return $session_id;
  }

  /**
   * проверяем надо ли скрывать продукты
   */
  public static function is_disable(){
    if(get_option('wooms_product_hiding_disable')){
      return true;
    }

    return false;
  }

  /**
   * settings_init
   */
  public static function settings_init()
  {
    $option_name = 'wooms_product_hiding_disable';
    register_setting('mss-settings', $option_name);
    add_settings_field(
      $id = $option_name,
      $title = 'Отключить скрытие продуктов',
      $callback = function($args)
      {
        printf('<input type="checkbox" name="%s" value="1" %s />', $args['name'], checked(1, $args['value'], false));
        printf( '<p><small>%s</small></p>', 'Если включить опцию, то обработчик скрытия продуктов из каталога будет отключен. Иногда это бывает полезно.' );
      },
      $page = 'mss-settings',
      $section = 'woomss_section_other',
      $args = [
        'name' => $option_name,
        'value' => get_option($option_name),
      ]
    );
  }

}

ProductsHiding::init();
