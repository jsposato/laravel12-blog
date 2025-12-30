<?php

namespace App\Jobs;

use App\Mail\CommentPosted;
use App\Models\Comment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCommentNotificationEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comment $comment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Send email to the post author
        Mail::to($this->comment->post->user->email)
            ->send(new CommentPosted($this->comment));
    }

    /**
     * Get the number of times the job may be attempted.
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     */
    public function backoff(): int
    {
        return 60; // Wait 60 seconds between retries
    }
}
