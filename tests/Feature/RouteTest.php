<?php

test('public routes are accessible', function () {
    $routes = [
        '/',
        '/posts',
        '/login',
        '/register',
    ];

    foreach ($routes as $route) {
        $response = $this->get($route);
        expect($response->status())->not->toBe(404, "Route {$route} should exist");
    }
});

test('authenticated routes require login', function () {
    $routes = [
        '/dashboard',
        '/posts/create',
        '/profile',
    ];

    foreach ($routes as $route) {
        $response = $this->get($route);
        expect($response->status())->toBe(302, "Route {$route} should redirect to login");
        $response->assertRedirect('/login');
    }
});

test('post routes are registered correctly', function () {
    expect(route('posts.index'))->toBe(url('/posts'))
                                ->and(route('posts.create'))->toBe(url('/posts/create'))
                                ->and(route('posts.store'))->toBe(url('/posts'));
});
