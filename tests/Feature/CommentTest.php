<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// COMMENT CREATION TESTS
// ============================================================================

test('guests cannot post comments', function () {
    $post = Post::factory()->published()->create();

    $response = $this->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    $response->assertRedirect(route('login'));
    expect(Comment::count())->toBe(0);
});

test('authenticated users can post comments on published posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $response = $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    $response->assertRedirect(route('posts.show', $post));

    expect(Comment::count())->toBe(1);

    $comment = Comment::first();
    expect($comment->body)->toBe('This is a test comment.')
                          ->and($comment->user_id)->toBe($user->id)
                          ->and($comment->post_id)->toBe($post->id);
});

test('comments require moderation by default', function () {
    config(['blog.comment_moderation' => true]);

    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    $comment = Comment::first();
    expect($comment->approved)->toBeFalse();
});

test('comments are auto-approved when moderation is disabled', function () {
    config(['blog.comment_moderation' => false]);

    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    $comment = Comment::first();
    expect($comment->approved)->toBeTrue();
});

test('comment requires body', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $response = $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => '',
    ]);

    $response->assertSessionHasErrors('body');
    expect(Comment::count())->toBe(0);
});

test('comment body must be at least 3 characters', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $response = $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'Hi',
    ]);

    $response->assertSessionHasErrors('body');
});

test('comment body cannot exceed 1000 characters', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $response = $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => str_repeat('a', 1001),
    ]);

    $response->assertSessionHasErrors('body');
});

test('users can comment on other users posts', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $response = $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'Great post!',
    ]);

    $response->assertRedirect(route('posts.show', $post));
    expect(Comment::count())->toBe(1);
});

// ============================================================================
// COMMENT APPROVAL TESTS
// ============================================================================

test('post author can approve comments on their post', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $comment = Comment::factory()->pending()->for($post)->for($commenter)->create();

    $response = $this->actingAs($author)->patch(route('comments.approve', $comment));

    $response->assertRedirect(route('posts.show', $post));

    $comment->refresh();
    expect($comment->approved)->toBeTrue();
});

test('non-post-authors cannot approve comments', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $comment = Comment::factory()->pending()->for($post)->for($commenter)->create();

    $response = $this->actingAs($otherUser)->patch(route('comments.approve', $comment));

    $response->assertStatus(403);

    $comment->refresh();
    expect($comment->approved)->toBeFalse();
});

test('comment author cannot approve their own comment', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $comment = Comment::factory()->pending()->for($post)->for($commenter)->create();

    $response = $this->actingAs($commenter)->patch(route('comments.approve', $comment));

    $response->assertStatus(403);

    $comment->refresh();
    expect($comment->approved)->toBeFalse();
});

test('guests cannot approve comments', function () {
    $comment = Comment::factory()->pending()->create();

    $response = $this->patch(route('comments.approve', $comment));

    $response->assertRedirect(route('login'));
});

// ============================================================================
// COMMENT DELETION TESTS
// ============================================================================

test('guests cannot delete comments', function () {
    $comment = Comment::factory()->create();

    $response = $this->delete(route('comments.destroy', $comment));

    $response->assertRedirect(route('login'));
    expect(Comment::find($comment->id))->not->toBeNull();
});

test('comment author can delete their own comment', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->for($post)->for($user)->create();

    $response = $this->actingAs($user)->delete(route('comments.destroy', $comment));

    $response->assertRedirect(route('posts.show', $post));
    expect(Comment::find($comment->id))->toBeNull();
});

test('post author can delete comments on their post', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->for($post)->for($commenter)->create();

    $response = $this->actingAs($author)->delete(route('comments.destroy', $comment));

    $response->assertRedirect(route('posts.show', $post));
    expect(Comment::find($comment->id))->toBeNull();
});

test('users cannot delete other users comments on other posts', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->for($post)->for($commenter)->create();

    $response = $this->actingAs($otherUser)->delete(route('comments.destroy', $comment));

    $response->assertStatus(403);
    expect(Comment::find($comment->id))->not->toBeNull();
});

// ============================================================================
// COMMENT VISIBILITY TESTS
// ============================================================================

test('approved comments are visible to everyone', function () {
    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->approved()->for($post)->create();

    $response = $this->get(route('posts.show', $post));

    $response->assertSee($comment->body);
});

test('pending comments are not visible to guests', function () {
    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->pending()->for($post)->create();

    $response = $this->get(route('posts.show', $post));

    $response->assertDontSee($comment->body);
});

test('pending comments are visible to post author', function () {
    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->pending()->for($post)->create();

    $response = $this->actingAs($author)->get(route('posts.show', $post));

    $response->assertSee($comment->body)
             ->assertSee('Pending Approval');
});

test('pending comments are visible to comment author', function () {
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->pending()->for($post)->for($commenter)->create();

    $response = $this->actingAs($commenter)->get(route('posts.show', $post));

    $response->assertSee($comment->body)
             ->assertSee('Pending Approval'); // Also check for the badge
});

test('post shows correct comment count', function () {
    $post = Post::factory()->published()->create();
    Comment::factory()->approved()->for($post)->count(3)->create();
    Comment::factory()->pending()->for($post)->count(2)->create();

    $response = $this->get(route('posts.show', $post));

    // Should only count approved comments
    $response->assertSee('Comments (3)');
});

// ============================================================================
// COMMENT RELATIONSHIPS TESTS
// ============================================================================

test('comment belongs to a post', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create();

    expect($comment->post)->toBeInstanceOf(Post::class)
                          ->and($comment->post->id)->toBe($post->id);
});

test('comment belongs to a user', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->for($user)->create();

    expect($comment->user)->toBeInstanceOf(User::class)
                          ->and($comment->user->id)->toBe($user->id);
});

test('post has many comments', function () {
    $post = Post::factory()->create();
    Comment::factory()->for($post)->count(3)->create();

    expect($post->comments)->toHaveCount(3);
});

test('deleting post deletes all comments', function () {
    $post = Post::factory()->create();
    Comment::factory()->for($post)->count(5)->create();

    expect(Comment::count())->toBe(5);

    $post->delete();

    expect(Comment::count())->toBe(0);
});

test('deleting user deletes all their comments', function () {
    $user = User::factory()->create();
    Comment::factory()->for($user)->count(3)->create();

    expect(Comment::count())->toBe(3);

    $user->delete();

    expect(Comment::count())->toBe(0);
});

// ============================================================================
// COMMENT SCOPES TESTS
// ============================================================================

test('approved scope returns only approved comments', function () {
    $post = Post::factory()->create();
    Comment::factory()->approved()->for($post)->count(3)->create();
    Comment::factory()->pending()->for($post)->count(2)->create();

    $approvedComments = Comment::approved()->get();

    expect($approvedComments)->toHaveCount(3);

    foreach ($approvedComments as $comment) {
        expect($comment->approved)->toBeTrue();
    }
});

test('pending scope returns only pending comments', function () {
    $post = Post::factory()->create();
    Comment::factory()->approved()->for($post)->count(3)->create();
    Comment::factory()->pending()->for($post)->count(2)->create();

    $pendingComments = Comment::pending()->get();

    expect($pendingComments)->toHaveCount(2);

    foreach ($pendingComments as $comment) {
        expect($comment->approved)->toBeFalse();
    }
});

// ============================================================================
// COMMENT MODEL METHODS TESTS
// ============================================================================

test('isApproved method returns correct value', function () {
    $approvedComment = Comment::factory()->approved()->create();
    $pendingComment = Comment::factory()->pending()->create();

    expect($approvedComment->isApproved())->toBeTrue()
                                          ->and($pendingComment->isApproved())->toBeFalse();
});

test('approve method approves a comment', function () {
    $comment = Comment::factory()->pending()->create();

    expect($comment->isApproved())->toBeFalse();

    $comment->approve();

    expect($comment->isApproved())->toBeTrue();
});

// ============================================================================
// COMMENT FORM TESTS
// ============================================================================

test('comment form is visible to authenticated users on published posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $response = $this->actingAs($user)->get(route('posts.show', $post));

    $response->assertSee('Leave a comment')
             ->assertSee('Post Comment');
});

test('comment form shows login prompt for guests', function () {
    $post = Post::factory()->published()->create();

    $response = $this->get(route('posts.show', $post));

    $response->assertSee('log in')
             ->assertDontSee('Post Comment');
});

test('post author sees pending comments section', function () {
    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    Comment::factory()->pending()->for($post)->count(2)->create();

    $response = $this->actingAs($author)->get(route('posts.show', $post));

    $response->assertSee('Pending Approval (2)');
});

test('non-post-authors do not see pending comments section', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();
    Comment::factory()->pending()->for($post)->count(2)->create();

    $response = $this->actingAs($otherUser)->get(route('posts.show', $post));

    $response->assertDontSee('Pending Approval');
});

// ============================================================================
// COMMENT MODERATION WORKFLOW TESTS
// ============================================================================

test('complete comment moderation workflow', function () {
    $author = User::factory()->create(['name' => 'Post Author']);
    $commenter = User::factory()->create(['name' => 'Commenter']);
    $otherUser = User::factory()->create(['name' => 'Other User']);
    $post = Post::factory()->published()->for($author)->create();

    // Step 1: Commenter posts a comment
    $response = $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    $response->assertRedirect(route('posts.show', $post))
             ->assertSessionHas('success');

    $comment = Comment::first();
    expect($comment)
        ->not->toBeNull()
             ->and($comment->body)->toBe('This is a test comment.')
             ->and($comment->approved)->toBeFalse()
             ->and($comment->user_id)->toBe($commenter->id);

    // Step 2: Commenter can see their own pending comment
    $response = $this->actingAs($commenter)->get(route('posts.show', $post));
    $response->assertStatus(200)
             ->assertSee('This is a test comment.')
             ->assertSee('Pending Approval');

    // Step 3: Other authenticated users cannot see the pending comment
    $response = $this->actingAs($otherUser)->get(route('posts.show', $post));
    $response->assertStatus(200)
             ->assertDontSee('This is a test comment.');

    // Step 4: Unauthenticated guests cannot see the pending comment
    // Flush session to become a true guest
    auth()->logout();
    $this->app['auth']->forgetGuards();

    $response = $this->get(route('posts.show', $post));
    $response->assertStatus(200)
             ->assertDontSee('This is a test comment.');

    // Step 5: Post author can see pending comment in moderation section
    $response = $this->actingAs($author)->get(route('posts.show', $post));
    $response->assertStatus(200)
             ->assertSee('This is a test comment.')
             ->assertSee('Pending Approval (1)');

    // Step 6: Post author approves comment
    $response = $this->actingAs($author)->patch(route('comments.approve', $comment));
    $response->assertRedirect(route('posts.show', $post))
             ->assertSessionHas('success', 'Comment approved successfully!');

    $comment->refresh();
    expect($comment->approved)->toBeTrue();

    // Step 7: Now everyone can see the approved comment

    // Commenter sees it (no longer pending)
    $response = $this->actingAs($commenter)->get(route('posts.show', $post));
    $response->assertSee('This is a test comment.')
             ->assertDontSee('Pending Approval'); // Badge should be gone

    // Other users see it
    $response = $this->actingAs($otherUser)->get(route('posts.show', $post));
    $response->assertSee('This is a test comment.');

    // Guests see it
    auth()->logout();
    $this->app['auth']->forgetGuards();

    $response = $this->get(route('posts.show', $post));
    $response->assertSee('This is a test comment.')
             ->assertSee('Comments (1)'); // Count should now be 1

    // Post author sees it (no longer in pending section)
    $response = $this->actingAs($author)->get(route('posts.show', $post));
    $response->assertSee('This is a test comment.')
             ->assertDontSee('Pending Approval (1)'); // Pending section should be gone
});

test('multiple comments can be posted and moderated', function () {
    $author = User::factory()->create();
    $commenter1 = User::factory()->create();
    $commenter2 = User::factory()->create();
    $guest = User::factory()->create(); // Another user who isn't involved
    $post = Post::factory()->published()->for($author)->create();

    // Post multiple comments
    $this->actingAs($commenter1)->post(route('comments.store', $post), [
        'body' => 'First comment',
    ]);

    $this->actingAs($commenter2)->post(route('comments.store', $post), [
        'body' => 'Second comment',
    ]);

    expect(Comment::count())->toBe(2);

    // Approve first comment only
    $firstComment = Comment::where('body', 'First comment')->first();
    $this->actingAs($author)->patch(route('comments.approve', $firstComment));

    // Check visibility for unauthenticated guests
    auth()->logout();
    $this->app['auth']->forgetGuards();

    $response = $this->get(route('posts.show', $post));
    $response->assertSee('First comment')
             ->assertDontSee('Second comment');

    // Check visibility for unrelated authenticated user
    $response = $this->actingAs($guest)->get(route('posts.show', $post));
    $response->assertSee('First comment')
             ->assertDontSee('Second comment');

    // Commenter1 can see approved first comment
    $response = $this->actingAs($commenter1)->get(route('posts.show', $post));
    $response->assertSee('First comment')
             ->assertDontSee('Second comment');

    // Commenter2 can see both: approved first comment AND their own pending second comment
    $response = $this->actingAs($commenter2)->get(route('posts.show', $post));
    $response->assertSee('First comment') // Approved comment is visible
             ->assertSee('Second comment') // Their own pending comment is visible
             ->assertSee('Pending Approval'); // Badge is shown for their comment

    // Post author can see both comments
    $response = $this->actingAs($author)->get(route('posts.show', $post));
    $response->assertSee('First comment')
             ->assertSee('Second comment')
             ->assertSee('Pending Approval (1)'); // One pending comment in moderation section

    // Comments count shows only approved
    $response->assertSee('Comments (1)');

    // Approve second comment
    $secondComment = Comment::where('body', 'Second comment')->first();
    $this->actingAs($author)->patch(route('comments.approve', $secondComment));

    // Now both comments are visible to everyone
    auth()->logout();
    $this->app['auth']->forgetGuards();

    $response = $this->get(route('posts.show', $post));
    $response->assertSee('First comment')
             ->assertSee('Second comment')
             ->assertSee('Comments (2)'); // Count should now be 2
});

test('users can only see their own pending comments', function () {
    // Create SEPARATE users - none of them should be the post author
    $author = User::factory()->create(['name' => 'Post Author']);
    $user1 = User::factory()->create(['name' => 'Alice Smith']);
    $user2 = User::factory()->create(['name' => 'Bob Jones']);
    $user3 = User::factory()->create(['name' => 'Carol White']);

    // Create post by the author (not any of the commenters)
    $post = Post::factory()->published()->for($author)->create();

    // Three DIFFERENT users (not the author) post comments
    $this->actingAs($user1)->post(route('comments.store', $post), [
        'body' => 'Unique comment from user one about widgets',
    ]);

    $this->actingAs($user2)->post(route('comments.store', $post), [
        'body' => 'Unique comment from user two about gadgets',
    ]);

    $this->actingAs($user3)->post(route('comments.store', $post), [
        'body' => 'Unique comment from user three about tools',
    ]);

    expect(Comment::count())->toBe(3);

    // User 1 sees only their comment (widgets)
    $response = $this->actingAs($user1)->get(route('posts.show', $post));
    expect($response->getContent())
        ->toContain('widgets')
        ->not->toContain('gadgets')
        ->not->toContain('tools');

    // User 2 sees only their comment (gadgets)
    $response = $this->actingAs($user2)->get(route('posts.show', $post));
    expect($response->getContent())
        ->toContain('gadgets')
        ->not->toContain('widgets')
        ->not->toContain('tools');

    // User 3 sees only their comment (tools)
    $response = $this->actingAs($user3)->get(route('posts.show', $post));
    expect($response->getContent())
        ->toContain('tools')
        ->not->toContain('widgets')
        ->not->toContain('gadgets');

    // Post author (different from all commenters) sees all comments
    $response = $this->actingAs($author)->get(route('posts.show', $post));
    expect($response->getContent())
        ->toContain('widgets')
        ->toContain('gadgets')
        ->toContain('tools')
        ->toContain('Pending Approval (3)');

    // IMPORTANT: Flush the authenticated session before checking guest view
    auth()->logout();
    $this->app['auth']->forgetGuards();

    // Unauthenticated guests see no comments
    $response = $this->get(route('posts.show', $post));
    expect($response->getContent())
        ->not->toContain('widgets')
        ->not->toContain('gadgets')
        ->not->toContain('tools');

    // Verify comment count display shows 0 (no approved comments)
    $response->assertSee('Comments (0)');
});
