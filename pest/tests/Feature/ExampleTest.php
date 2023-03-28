<?php

test('example', function () {
    expect(true)->toBeTrue();
});

test('example2', function () {
    $posts = get_posts();
    // var_dump($posts);
    expect($posts)->toBeArray();
});
