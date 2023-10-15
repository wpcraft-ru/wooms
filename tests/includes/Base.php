<?php

namespace WooMS\Tests\Base;

use function Testeroid\{test, transaction_query, ddcli};

test('wooms active?', function(){
  $can_start = wooms_can_start();
  return $can_start;

});
