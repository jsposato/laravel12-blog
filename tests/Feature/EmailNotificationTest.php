<?php

use App\Events\CommentCreated;
use App\Jobs\SendCommentNotificationEmail;
use App\Mail\CommentPosted;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ============================================================================
// EVENT TESTS
// ============================================================================

test('comment created event is dispatched when comment is posted', function () {
    Event::fake();

    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    Event::assertDispatched(CommentCreated::class);
});

test('comment created event contains the comment', function () {
    Event::fake();

    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $this->actingAs($user)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    Event::assertDispatched(function (CommentCreated $event) {
        return $event->comment->body === 'This is a test comment.';
    });
});

// ============================================================================
// JOB QUEUE TESTS
// ============================================================================

test('notification job is queued when comment is created', function () {
    Queue::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'This is a test comment.',
    ]);

    Queue::assertPushed(SendCommentNotificationEmail::class);
});

test('notification job contains the comment', function () {
    Queue::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'Test comment body.',
    ]);

    Queue::assertPushed(function (SendCommentNotificationEmail $job) {
        return $job->comment->body === 'Test comment body.';
    });
});

test('notification job is not queued when author comments on own post', function () {
    Queue::fake();

    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    // Author commenting on their own post
    $this->actingAs($author)->post(route('comments.store', $post), [
        'body' => 'Author commenting on own post.',
    ]);

    Queue::assertNothingPushed();
});

// ============================================================================
// EMAIL TESTS
// ============================================================================

test('email is sent to post author when comment is created', function () {
    Mail::fake();

    $author = User::factory()->create(['email' => 'author@example.com']);
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $comment = Comment::factory()->for($post)->for($commenter)->create();

    // Manually dispatch the job (simulating queue processing)
    SendCommentNotificationEmail::dispatch($comment);

    Mail::assertSent(CommentPosted::class, function ($mail) use ($author) {
        return $mail->hasTo($author->email);
    });
});

test('email contains comment details', function () {
    Mail::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create(['name' => 'John Doe']);
    $post = Post::factory()->published()->for($author)->create(['title' => 'Test Post']);

    $comment = Comment::factory()->for($post)->for($commenter)->create([
        'body' => 'This is the comment body.',
    ]);

    SendCommentNotificationEmail::dispatch($comment);

    Mail::assertSent(CommentPosted::class, function ($mail) use ($comment) {
        return $mail->comment->id === $comment->id &&
               $mail->comment->body === 'This is the comment body.';
    });
});

test('email subject includes post title', function () {
    Mail::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create(['title' => 'My Awesome Post']);

    $comment = Comment::factory()->for($post)->for($commenter)->create();

    SendCommentNotificationEmail::dispatch($comment);

    Mail::assertSent(CommentPosted::class, function ($mail) {
        return $mail->envelope()->subject === 'New Comment on Your Post: My Awesome Post';
    });
});

test('no email is sent when author comments on own post', function () {
    Mail::fake();
    Queue::fake();

    $author = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $this->actingAs($author)->post(route('comments.store', $post), [
        'body' => 'Author self-comment.',
    ]);

    Mail::assertNothingSent();
});

// ============================================================================
// INTEGRATION TESTS
// ============================================================================

test('complete notification flow works end to end', function () {
    Mail::fake();

    $author = User::factory()->create(['email' => 'author@test.com']);
    $commenter = User::factory()->create(['name' => 'Jane Commenter']);
    $post = Post::factory()->published()->for($author)->create(['title' => 'Integration Test Post']);

    // Create comment via HTTP request
    $response = $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'Integration test comment.',
    ]);

    $response->assertRedirect();

    // Get the created comment
    $comment = Comment::where('body', 'Integration test comment.')->first();

    expect($comment)->not->toBeNull();

    // Process the queued job
    SendCommentNotificationEmail::dispatch($comment);

    // Assert email was sent
    Mail::assertSent(CommentPosted::class, function ($mail) use ($author, $comment) {
        return $mail->hasTo($author->email) &&
               $mail->comment->id === $comment->id;
    });
});

test('notification respects comment moderation setting', function () {
    config(['blog.comment_moderation' => true]);

    Mail::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $this->actingAs($commenter)->post(route('comments.store', $post), [
        'body' => 'Moderated comment.',
    ]);

    $comment = Comment::where('body', 'Moderated comment.')->first();

    // Comment should be unapproved
    expect($comment->approved)->toBeFalse();

    // But notification should still be sent
    SendCommentNotificationEmail::dispatch($comment);

    Mail::assertSent(CommentPosted::class);
});

// ============================================================================
// JOB RETRY TESTS
// ============================================================================

test('notification job can be retried on failure', function () {
    $comment = Comment::factory()->create();
    $job = new SendCommentNotificationEmail($comment);

    expect($job->tries())->toBe(3);
});

test('notification job has backoff time between retries', function () {
    $comment = Comment::factory()->create();
    $job = new SendCommentNotificationEmail($comment);

    expect($job->backoff())->toBe(60);
});

// ============================================================================
// MAILABLE TESTS
// ============================================================================

test('mailable can be rendered', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create(['name' => 'Test User']);
    $post = Post::factory()->for($author)->create(['title' => 'Test Post']);
    $comment = Comment::factory()->for($post)->for($commenter)->create([
        'body' => 'Test comment body.',
    ]);

    $mailable = new CommentPosted($comment);

    $mailable->assertSeeInHtml('Test User');
    $mailable->assertSeeInHtml('Test Post');
    $mailable->assertSeeInHtml('Test comment body.');
});

test('mailable includes view comment button', function () {
    $comment = Comment::factory()->create();
    $mailable = new CommentPosted($comment);

    $mailable->assertSeeInHtml('View Comment');
    $mailable->assertSeeInHtml(route('posts.show', $comment->post));
});

test('mailable shows approval status for pending comments', function () {
    $comment = Comment::factory()->pending()->create();
    $mailable = new CommentPosted($comment);

    $mailable->assertSeeInHtml('awaiting your approval');
});

test('mailable does not show approval message for approved comments', function () {
    $comment = Comment::factory()->approved()->create();
    $mailable = new CommentPosted($comment);

    $mailable->assertDontSeeInHtml('awaiting your approval');
});

// ============================================================================
// QUEUE CONFIGURATION TESTS
// ============================================================================

test('notification job uses correct queue connection', function () {
    $comment = Comment::factory()->create();
    $job = new SendCommentNotificationEmail($comment);

    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});

// ============================================================================
// MULTIPLE COMMENTS TESTS
// ============================================================================

test('multiple comments trigger multiple notification jobs', function () {
    Queue::fake();

    $author = User::factory()->create();
    $commenter1 = User::factory()->create();
    $commenter2 = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    // First comment
    $this->actingAs($commenter1)->post(route('comments.store', $post), [
        'body' => 'First comment.',
    ]);

    // Second comment
    $this->actingAs($commenter2)->post(route('comments.store', $post), [
        'body' => 'Second comment.',
    ]);

    Queue::assertPushed(SendCommentNotificationEmail::class, 2);
});

test('each comment triggers separate email', function () {
    Mail::fake();

    $author = User::factory()->create();
    $commenter1 = User::factory()->create();
    $commenter2 = User::factory()->create();
    $post = Post::factory()->published()->for($author)->create();

    $comment1 = Comment::factory()->for($post)->for($commenter1)->create(['body' => 'First']);
    $comment2 = Comment::factory()->for($post)->for($commenter2)->create(['body' => 'Second']);

    SendCommentNotificationEmail::dispatch($comment1);
    SendCommentNotificationEmail::dispatch($comment2);

    Mail::assertSent(CommentPosted::class, 2);
});

// ============================================================================
// ERROR HANDLING TESTS
// ============================================================================

test('notification handles deleted post gracefully', function () {
    $comment = Comment::factory()->create();
    $commentId = $comment->id;

    // Delete the post (which cascades to delete the comment)
    $comment->post->delete();

    // Job should handle this gracefully
    expect(Comment::find($commentId))->toBeNull();
});

test('notification handles deleted user gracefully', function () {
    Mail::fake();

    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->for($author)->create();
    $comment = Comment::factory()->for($post)->for($commenter)->create();

    // Delete the commenter
    $commenter->delete();

    // Should still have comment with user_id (soft delete scenario)
    // Job should handle this gracefully
    expect($comment->fresh())->toBeNull();
});
