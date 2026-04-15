<?php

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {

	WP_CLI::add_command('test:wooms', function ($args, $assoc_args) {
		$plugin_path = dirname(__DIR__ . '..');
		$pest_binary = $plugin_path.'/vendor/bin/pest';

		if (! file_exists($pest_binary)) {
			WP_CLI::error(sprintf('Pest binary was not found at %s.', $pest_binary));
		}

		$php_binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
		$forwarded_args = $args;

		foreach ($assoc_args as $key => $value) {
			$forwarded_args[] = true === $value
				? sprintf('--%s', $key)
				: sprintf('--%s=%s', $key, (string) $value);
		}

		$command_parts = array_merge(
			array(
				escapeshellarg($php_binary),
				escapeshellarg($pest_binary),
				'--colors=always',
			),
			array_map('escapeshellarg', $forwarded_args)
		);

		$command = sprintf(
			'cd %s && %s',
			escapeshellarg($plugin_path),
			implode(' ', $command_parts)
		);

		passthru($command, $exit_code);

		WP_CLI::halt($exit_code);
	}, [
		'shortdesc' => 'Run plugin tests using Pest.',
	]);
}
