<?php

test('is_plugin_active wooms', function(){
  $res = is_plugin_active('wooms/wooms.php');
  expect($res)->toBeTrue;
});

test('is_plugin_active woocommerce', function(){
  $res = is_plugin_active('woocommerce/woocommerce.php');
  expect($res)->toBeTrue;
});
