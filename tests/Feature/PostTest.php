<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ============================================================================
// VIEW TESTS
// ============================================================================

test('guests can view posts index', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->published()->for($user)->create();

    $response = $this->get(route('posts.index'));

    $response->assertStatus(200)
             ->assertViewIs('posts.index')
             ->assertViewHas('posts');
});

test('posts index shows only published posts', function () {
    $user = User::factory()->create();
    $publishedPost = Post::factory()->published()->for($user)->create(['title' => 'Published Post']);
    $draftPost = Post::factory()->draft()->for($user)->create(['title' => 'Draft Post']);

    $response = $this->get(route('posts.index'));

    $response->assertSee('Published Post')
             ->assertDontSee('Draft Post');
});

test('posts index is paginated', function () {
    $user = User::factory()->create();
    Post::factory()->count(15)->published()->for($user)->create();

    $response = $this->get(route('posts.index'));

    $response->assertViewHas('posts', function ($posts) {
        return $posts->count() === 10; // Default per page
    });
});

test('guests can view published post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->for($user)->create();

    $response = $this->get(route('posts.show', $post));

    $response->assertStatus(200)
             ->assertViewIs('posts.show')
             ->assertSee($post->title)
             ->assertSee($post->body);
});

test('guests cannot view draft posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->draft()->for($user)->create();

    $response = $this->get(route('posts.show', $post));

    $response->assertStatus(403);
});

test('post author can view their own draft posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->draft()->for($user)->create();

    $response = $this->actingAs($user)->get(route('posts.show', $post));

    $response->assertStatus(200)
             ->assertSee($post->title);
});

// ============================================================================
// CREATE TESTS
// ============================================================================

test('guests cannot access create post page', function () {
    $response = $this->get(route('posts.create'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can access create post page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('posts.create'));

    $response->assertStatus(200)
             ->assertViewIs('posts.create');
});

test('authenticated users can create a post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Test Post Title',
        'body' => 'This is the test post body with enough content.',
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $response->assertRedirect();

    expect(Post::where('title', 'Test Post Title')->exists())->toBeTrue();

    $post = Post::where('title', 'Test Post Title')->first();
    expect($post->user_id)->toBe($user->id)
                          ->and($post->slug)->toBe('test-post-title');
});

test('post creation generates unique slug', function () {
    $user = User::factory()->create();

    // Create first post with the title "Test Post"
    $firstPost = Post::factory()->for($user)->create([
        'title' => 'Test Post',
        'slug' => 'test-post', // Explicitly set the first slug
    ]);

    dump('First post slug: ' . $firstPost->slug);
    dump('First post ID: ' . $firstPost->id);

    // Create second post with the same title via the controller
    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Test Post',
        'body' => 'This is another test post with the same title.',
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $response->assertRedirect();

    // Get the second post (latest one)
    $secondPost = Post::where('title', 'Test Post')->latest('id')->first();

    dump('Second post slug: ' . $secondPost->slug);
    dump('Second post ID: ' . $secondPost->id);
    dump('All posts: ' . Post::all()->pluck('slug', 'id'));

    expect($secondPost->slug)->toBe('test-post-1')
                             ->and($firstPost->slug)->toBe('test-post')
                             ->and($secondPost->id)->not->toBe($firstPost->id);
});

test('post creation requires title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => '',
        'body' => 'This is the test post body.',
    ]);

    $response->assertSessionHasErrors('title');
});

test('post creation requires body with minimum length', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Test Post',
        'body' => 'Short',
    ]);

    $response->assertSessionHasErrors('body');
});

test('post can be created with featured image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('test.jpg', 600, 400);

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Post with Image',
        'body' => 'This post has a featured image.',
        'featured_image' => $file,
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $post = Post::where('title', 'Post with Image')->first();

    expect($post->featured_image)->not->toBeNull();
    Storage::disk('public')->assertExists($post->featured_image);
});

test('post creation validates image type', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('test.pdf', 100);

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Post with Invalid File',
        'body' => 'This post has an invalid file.',
        'featured_image' => $file,
    ]);

    $response->assertSessionHasErrors('featured_image');
});

test('post can be created as draft', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'Draft Post',
        'body' => 'This is a draft post.',
        'published_at' => null,
    ]);

    $post = Post::where('title', 'Draft Post')->first();

    expect($post->published_at)->toBeNull()
                               ->and($post->isPublished())->toBeFalse();
});

// ============================================================================
// UPDATE TESTS
// ============================================================================

test('guests cannot access edit post page', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $response = $this->get(route('posts.edit', $post));

    $response->assertRedirect(route('login'));
});

test('post author can access edit page', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('posts.edit', $post));

    $response->assertStatus(200)
             ->assertViewIs('posts.edit')
             ->assertSee($post->title);
});

test('users cannot edit other users posts', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $response = $this->actingAs($otherUser)->get(route('posts.edit', $post));

    $response->assertStatus(403);
});

test('post author can update their post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create(['title' => 'Original Title']);

    $response = $this->actingAs($user)->put(route('posts.update', $post), [
        'title' => 'Updated Title',
        'body' => 'Updated body content with enough characters.',
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $response->assertRedirect(route('posts.show', $post));

    $post->refresh();
    expect($post->title)->toBe('Updated Title');
});

test('post update validates required fields', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $response = $this->actingAs($user)->put(route('posts.update', $post), [
        'title' => '',
        'body' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'body']);
});

test('post can update featured image', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $newFile = UploadedFile::fake()->image('new.jpg');

    $response = $this->actingAs($user)->put(route('posts.update', $post), [
        'title' => $post->title,
        'body' => $post->body,
        'featured_image' => $newFile,
    ]);

    $post->refresh();
    expect($post->featured_image)->not->toBeNull();
    Storage::disk('public')->assertExists($post->featured_image);
});

test('post can remove featured image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('test.jpg');
    $path = $file->store('featured-images', 'public');

    $post = Post::factory()->for($user)->create(['featured_image' => $path]);

    $response = $this->actingAs($user)->put(route('posts.update', $post), [
        'title' => $post->title,
        'body' => $post->body,
        'remove_featured_image' => true,
    ]);

    $post->refresh();
    expect($post->featured_image)->toBeNull();
});

// ============================================================================
// DELETE TESTS
// ============================================================================

test('guests cannot delete posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $response = $this->delete(route('posts.destroy', $post));

    $response->assertRedirect(route('login'));
    expect(Post::find($post->id))->not->toBeNull();
});

test('post author can delete their post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete(route('posts.destroy', $post));

    $response->assertRedirect(route('posts.index'));
    expect(Post::find($post->id))->toBeNull();
});

test('users cannot delete other users posts', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $response = $this->actingAs($otherUser)->delete(route('posts.destroy', $post));

    $response->assertStatus(403);
    expect(Post::find($post->id))->not->toBeNull();
});

test('deleting post also deletes featured image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('test.jpg');
    $path = $file->store('featured-images', 'public');

    $post = Post::factory()->for($user)->create(['featured_image' => $path]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)->delete(route('posts.destroy', $post));

    Storage::disk('public')->assertMissing($path);
});

// ============================================================================
// SLUG TESTS
// ============================================================================

test('post can be accessed by slug', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->for($user)->create([
        'title' => 'My Awesome Post',
        'slug' => 'my-awesome-post',
    ]);

    $response = $this->get('/posts/my-awesome-post');

    $response->assertStatus(200)
             ->assertSee('My Awesome Post');
});

test('post slug is automatically generated from title', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('posts.store'), [
        'title' => 'This is My Title',
        'body' => 'Post body content here.',
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $post = Post::where('title', 'This is My Title')->first();
    expect($post->slug)->toBe('this-is-my-title');
});
