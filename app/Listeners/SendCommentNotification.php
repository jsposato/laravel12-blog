<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Jobs\SendCommentNotificationEmail;

class SendCommentNotification
{
    /**
     * Handle the event.
     */
    public function handle(CommentCreated $event): void
    {
        // Don't send notification if the commenter is the post author
        if ($event->comment->user_id === $event->comment->post->user_id) {
            return;
        }

        // Dispatch the job to send the email
        SendCommentNotificationEmail::dispatch($event->comment);
    }
}
