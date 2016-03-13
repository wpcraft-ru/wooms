<?php

/*
Plugin Name: WooCommerce и МойСклад
Plugin URI: http://systemo.biz/
Description: This plugin integrates WooCommerce and MoySklad. This plugin can update balances in woocommerce from moysklad and update orders in moysklad from woocommerce.
Author: Systemo
Version: 1.0
Author URI: http://systemo.biz/
*/

require_once('inc/settings/select_warehouse.php');
require_once('inc/settings/select_my_company.php');
require_once('inc/settings/select_client.php');

require_once('inc/menu.php');
require_once('inc/xml-sync.php');
require_once('inc/api-crud-uuid-product-category.php');
require_once('inc/product-category-import.php');
require_once('inc/products-import.php');
require_once('inc/products-images-import.php');
require_once('inc/variation-import.php');
require_once('inc/order-export.php');
require_once('inc/products-delete-all.php');



//require_once('inc/export-products.php');
