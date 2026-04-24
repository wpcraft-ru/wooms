<?php

it('loads wordpress core functions', function (): void {
	expect(function_exists('get_post'))->toBeTrue();

	$post = get_posts();

	expect($post)->toBeArray();
});

it('adds numbers correctly', function (): void {
	expect(2 + 2)->toBe(4);
});

it('contains expected value in array', function (): void {
	$features = ['acorn', 'wordpress', 'pest'];

	expect($features)
		->toHaveCount(3)
		->toContain('pest');
});


