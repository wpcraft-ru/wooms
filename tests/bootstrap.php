<?php

declare(strict_types=1);

$wpLoadPath = '/var/www/html/wp-load.php';

if (! file_exists($wpLoadPath)) {
    throw new RuntimeException("WordPress bootstrap file not found at: {$wpLoadPath}");
}

require_once $wpLoadPath;
