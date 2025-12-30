<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'body',
        'featured_image',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically generate slug from title when creating
        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $post->slug = static::generateUniqueSlug($post->title);
            }
        });

        // Update slug when title changes (only if slug is empty or not manually set)
//        static::updating(function (Post $post) {
//            // Only auto-generate slug if title changed AND slug wasn't manually set
//            if ($post->isDirty('title') && !$post->isDirty('slug')) {
//                $post->slug = static::generateUniqueSlug($post->title, $post->id);
//            }
//        });
    }

    /**
     * Generate a unique slug from the given title.
     *
     * @param string $title
     * @param int|null $ignoreId The ID to ignore when checking for duplicates (for updates)
     * @return string
     */
    protected static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        // Check if slug exists and append number if necessary
        while (static::slugExists($slug, $ignoreId)) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * Check if a slug exists in the database.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return bool
     */
    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = static::where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Get the user that owns the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the comments for the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get approved comments for the post.
     */
    public function approvedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('approved', true)->latest();
    }

    /**
     * Get pending comments for the post.
     */
    public function pendingComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('approved', false)->latest();
    }

    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include draft posts.
     */
    public function scopeDraft($query)
    {
        return $query->whereNull('published_at')
                     ->orWhere('published_at', '>', now());
    }

    /**
     * Check if the post is published.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Get the post's excerpt (first 150 characters of body).
     */
    public function getExcerptAttribute(): string
    {
        return Str::limit(strip_tags($this->body), 150);
    }
}
