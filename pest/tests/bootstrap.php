<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Testu
 */

// $_tests_dir = getenv( 'WP_TESTS_DIR' );

// if ( ! $_tests_dir ) {
// 	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
// }

$_tests_dir = __DIR__;
// var_dump($_tests_dir);
// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
// $_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
// if ( false !== $_phpunit_polyfills_path ) {
// 	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
// }

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
