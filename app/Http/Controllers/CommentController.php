<?php

namespace App\Http\Controllers;

use App\Events\CommentCreated;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Store a newly created comment.
     */
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('create', Comment::class);

        $comment = $post->comments()->create([
            'user_id' => Auth::id(),
            'body' => $request->validated()['body'],
            'approved' => config('blog.comment_moderation') ? false : true,
        ]);

        // Fire the event
        CommentCreated::dispatch($comment);

        $message = config('blog.comment_moderation')
            ? 'Your comment has been submitted and is awaiting approval.'
            : 'Your comment has been posted successfully!';

        return redirect()
            ->route('posts.show', $post)
            ->with('success', $message);
    }

    /**
     * Approve a comment.
     */
    public function approve(Comment $comment): RedirectResponse
    {
        $this->authorize('approve', $comment);

        $comment->approve();

        return redirect()
            ->route('posts.show', $comment->post)
            ->with('success', 'Comment approved successfully!');
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $post = $comment->post;
        $comment->delete();

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Comment deleted successfully!');
    }
}
