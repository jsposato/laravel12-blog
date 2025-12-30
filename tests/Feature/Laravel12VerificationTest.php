<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

test('application is running laravel 12', function () {
    $version = App::version();

    expect($version)->toStartWith('12.');
});

test('database connection works', function () {
    expect(DB::connection()->getDatabaseName())
        ->toBe(config('database.connections.mysql.database'));
});

test('storage link exists', function () {
    expect(file_exists(public_path('storage')))
        ->toBeTrue();
});

test('blog configuration is loaded', function () {
    expect(config('blog.posts_per_page'))
        ->toBe(10)
        ->and(config('blog.featured_image.max_size'))
        ->toBe(2048);
});
