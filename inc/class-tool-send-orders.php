<?php

/**
 * Send orders to MoySklad
 */
class woomss_tool_send_orders_to_moysklad extends woomss_import {

  function __construct(){
    parent::__construct();
    $this->section_title = __('Передача заказов');
    $this->section_exerpt = __('Берем 5 новых заказов и передаем в МойСклад');
    $this->slug = 'woomss-order-to-moysklad';
    $this->slug_action = 'woomss-order-to-moysklad-do';

  }


  function load_data(){
    echo '<p>load data start...</p>';

    
  }


} new woomss_tool_send_orders_to_moysklad;
