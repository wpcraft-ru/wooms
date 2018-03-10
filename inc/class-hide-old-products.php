<?php
/**
 * Hide old products
 */
class WooMS_Hide_Old_Products
{
  public function __construct()
  {
    //Main Walker
    add_action( 'init', array($this, 'cron_init'));
    add_action( 'wooms_cron_clear_old_products_walker', array($this, 'cron_starter'));

  }

  function walker(){

    // do_action('logger_u7', ['tt2', 1]);

    $session_id = get_option('wooms_session_id');
    if(empty($session_id)){
      return false;
    }

    //Получаем продукты у которых сессия старее чем текущая
    $args = array(
      'post_type' => 'product',
      'meta_compare' => '!=',
      'meta_key' => 'wooms_session_id',
      'meta_value' => $session_id,
    );

    $list = get_posts($args);

    // do_action('logger_u7', ['tt1', $list]);

  }

  /**
  * Cron task restart
  */
  function cron_init()
  {
    if ( ! wp_next_scheduled( 'wooms_cron_clear_old_products_walker' ) ) {
      wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_clear_old_products_walker' );
    }
  }

  /**
  * Starter walker by cron if option enabled
  */
  function cron_starter(){

    $this->walker();

  }
}

new WooMS_Hide_Old_Products;
