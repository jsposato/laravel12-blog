<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users can search posts by title', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->create(['title' => 'Laravel Testing Best Practices']);
    Post::factory()->published()->for($user)->create(['title' => 'Vue.js Component Guide']);
    Post::factory()->published()->for($user)->create(['title' => 'Laravel Eloquent Tips']);

    $response = $this->get(route('posts.index', ['search' => 'Laravel']));

    $response->assertStatus(200)
             ->assertSee('Laravel Testing Best Practices')
             ->assertSee('Laravel Eloquent Tips')
             ->assertDontSee('Vue.js Component Guide');
});

test('users can search posts by body content', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->create([
        'title' => 'First Post',
        'body' => 'This post discusses Laravel framework in detail',
    ]);

    Post::factory()->published()->for($user)->create([
        'title' => 'Second Post',
        'body' => 'This post is about React components',
    ]);

    $response = $this->get(route('posts.index', ['search' => 'Laravel framework']));

    $response->assertStatus(200)
             ->assertSee('First Post')
             ->assertDontSee('Second Post');
});

test('search is case insensitive', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->create(['title' => 'Laravel Framework']);

    $response = $this->get(route('posts.index', ['search' => 'laravel']));

    $response->assertStatus(200)
             ->assertSee('Laravel Framework');
});

test('search returns no results for non-matching query', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->create(['title' => 'Laravel Tips']);

    $response = $this->get(route('posts.index', ['search' => 'Python']));

    $response->assertStatus(200)
             ->assertSee('No results found')
             ->assertDontSee('Laravel Tips');
});

test('search only includes published posts', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->create(['title' => 'Published Laravel Post']);
    Post::factory()->draft()->for($user)->create(['title' => 'Draft Laravel Post']);

    $response = $this->get(route('posts.index', ['search' => 'Laravel']));

    $response->assertStatus(200)
             ->assertSee('Published Laravel Post')
             ->assertDontSee('Draft Laravel Post');
});

test('empty search returns all posts', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->count(3)->create();

    $response = $this->get(route('posts.index', ['search' => '']));

    $response->assertStatus(200);
    expect($response->getContent())->toContain('Read more');
});

test('search results are paginated', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->count(15)->create([
        'title' => 'Laravel Post',
    ]);

    $response = $this->get(route('posts.index', ['search' => 'Laravel']));

    $response->assertStatus(200);

    // Should show pagination links
    expect($response->getContent())->toContain('Next');
});

test('search query persists across pagination', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->count(15)->create(['title' => 'Laravel Post']);

    $response = $this->get(route('posts.index', ['search' => 'Laravel', 'page' => 2]));

    $response->assertStatus(200);

    // URL should contain search parameter
    expect($response->getContent())->toContain('search=Laravel');
});

test('search displays result count', function () {
    $user = User::factory()->create();

    Post::factory()->published()->for($user)->count(3)->create(['title' => 'Laravel Post']);

    $response = $this->get(route('posts.index', ['search' => 'Laravel']));

    $response->assertStatus(200)
             ->assertSee('Found 3 results');
});

test('search shows clear button when active', function () {
    $user = User::factory()->create();
    Post::factory()->published()->for($user)->create();

    $response = $this->get(route('posts.index', ['search' => 'test']));

    $response->assertStatus(200)
             ->assertSee('Clear');
});

test('search input retains search query', function () {
    $user = User::factory()->create();
    Post::factory()->published()->for($user)->create(['title' => 'Laravel']);

    $response = $this->get(route('posts.index', ['search' => 'Laravel']));

    $response->assertStatus(200);
    expect($response->getContent())->toContain('value="Laravel"');
});
