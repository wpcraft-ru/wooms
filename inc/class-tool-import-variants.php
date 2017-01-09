<?php

/**
 * Import variants from MoySklad
 */
class woomss_tool_import_variaints extends woomss_import {

  function __construct(){
      parent::__construct();
      $this->section_title = __('Импорт вариаций');
      $this->section_exerpt = __('Импортируем вариации и привязываем их к продуктам WooCommerce');
      $this->slug = 'woomss_tool_import_variaints';
      $this->slug_action = 'woomss_tool_import_variaints_do';
  }

  function load_data(){

    echo '<p>load data start...</p>';

    $offset = 0;

    if( ! empty($_REQUEST['offset'])){
      $offset = intval($_REQUEST['offset']);
    }

    $url_get = add_query_arg(
                  array(
                    'offset' => $offset,
                    'limit' => 25
                  ),
                  'https://online.moysklad.ru/api/remap/1.1/entity/variant/');



    $data = $this->get_data_by_url( $url_get );
    $rows = $data['rows'];

    printf('<p>Объем записей: %s</p>', $data['meta']['size']);

    foreach ($rows as $key => $row) {
      printf('<h2>%s</h2>', $row['name']);


      $product_data = $this->get_data_by_url($row['product']['meta']['href']);

      if(empty($product_data['article'])){
        continue;
      } else {
        $article = $product_data['article'];
      }

      $product_id = intval(wc_get_product_id_by_sku($article));

      if(empty($product_id)){
        continue;
      }

      $product = wc_get_product($product_id);

      //@todo: added code for create variations of product




      printf('<pre>%s</pre>', print_r($product, true));
      printf('<pre>%s</pre>', print_r($row, true));
    }

  }


} new woomss_tool_import_variaints;
