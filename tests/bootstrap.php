<?php

namespace WooMS\Tests;

use function Testeroid\{test, transaction_query, ddcli};

require_once __DIR__ . '/functions.php';

foreach(glob(__DIR__ . '/includes/*.php') as $php_include) {
  require_once($php_include);
}
