<?php

test('get posts', function () {
  $posts = get_posts();
  expect($posts)->toBeArray();
});
