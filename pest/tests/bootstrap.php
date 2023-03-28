<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Testu
*/

require_once __DIR__ . '/../vendor/autoload.php';

define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/../../../../../wp-config.php');
define( 'WP_TESTS_DOMAIN', 'wooms.local' );
define( 'WP_TESTS_EMAIL', 'uptimizt@gmail.com' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

require_once WP_TESTS_CONFIG_FILE_PATH;

// if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
// 	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
// 	exit( 1 );
// }

// Give access to tests_add_filter() function.
// require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
// function _manually_load_plugin() {
// 	require dirname( dirname( __FILE__ ) ) . '/testu.php';
// }

// tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
// require "{$_tests_dir}/includes/bootstrap.php";
