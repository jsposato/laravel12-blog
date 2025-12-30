<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view approved comments
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Comment $comment): bool
    {
        // Anyone can view approved comments
        if ($comment->isApproved()) {
            return true;
        }

        // Only the comment author or post author can view pending comments
        return $user && (
                $user->id === $comment->user_id ||
                $user->id === $comment->post->user_id
            );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create comments
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        // Only the comment author can update their comment
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        // The comment author OR the post author can delete the comment
        return $user->id === $comment->user_id ||
               $user->id === $comment->post->user_id;
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Comment $comment): bool
    {
        // Only the post author can approve comments on their post
        return $user->id === $comment->post->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
