<?php

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', __DIR__ . '/../../../../' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'storefront' );
/*
 * Test with multisite enabled.
 * Alternatively, use the tests/phpunit/multisite.xml configuration file.
 */
// define( 'WP_TESTS_MULTISITE', true );

/*
 * Force known bugs to be run.
 * Tests with an associated Trac ticket that is still open are normally skipped.
 */
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);


define('DB_NAME', 'local');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'localhost');
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define('AUTH_KEY',         'YsB1aFssQ5++qRWkJbzuvPGe6ujk0qFZq7uaGlyCipH8Ws8u4PCRSUDAmBrGosdFFomiPQNvMRa+0z9pqWtasg==');
define('SECURE_AUTH_KEY',  'LunhK7+383/yq//LAH/qTqKvBkT3Pk/uNG5HOD/gbSChafi2QOJiLMn41IntUPUuoSl66sk3ZG2tjlJk9Q9J5A==');
define('LOGGED_IN_KEY',    '5YpK29900KVTZyRDYPo4BYuoJERPFxGJJh/JaayuqKCIsLxdp5JoWu1bpWMscLZuT+YK6lcKc2NuPaYq8PMvEQ==');
define('NONCE_KEY',        'xXSAN12cPZDW+p8EGj2P2WPN0jpsiRAVbxLRqTGoxW57FEJRwMUsOoJrEskEMzw6WCwjCjVNfi/F00lYGzgS0g==');
define('AUTH_SALT',        'HGBOtmJNd8RWb72tI0Ugizn7vkq123rBjQDH89RsjGcSuOTn6uocE/GaIq+1ZfJYgCIu2v3ud2oEy6Mma8XnMw==');
define('SECURE_AUTH_SALT', 'NYN+mpdjddCvRPeZh4R52MgubsiBc9OL5WNGED8HyYc6Qt+8qzbcXXirf5UCpwgzGmGQOtCsy5zvARrFVEs9AQ==');
define('LOGGED_IN_SALT',   'FqDWeaXSaNGx1ZfgXcY2VDDJ1P/JEyijvE5cMEioj3uqYZeIPst8H5DhcVCzcnlC+Dxkrs3D7TOfWJS3YRkVfg==');
define('NONCE_SALT',       'eNWmV8vkc0SEN29/2pGXocUYqHXHbevuxMezZo+tm+ycxkQ1Ej08cVX6QsbbuJJSkkivk2peJcITDdj28wV8rA==');

$table_prefix = 'wp_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'wooms.local' );
define( 'WP_TESTS_EMAIL', 'uptimizt@gmail.com' );
define( 'WP_TESTS_TITLE', 'Test WooMS' );

define( 'WP_PHP_BINARY', 'php' );

