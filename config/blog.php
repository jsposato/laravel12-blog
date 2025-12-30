<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blog Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration values for the personal blog application.
    |
    */

    'posts_per_page' => env('BLOG_POSTS_PER_PAGE', 10),

    'featured_image' => [
        'max_size' => env('BLOG_IMAGE_MAX_SIZE', 2048), // KB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'disk' => env('BLOG_IMAGE_DISK', 'public'),
        'path' => 'featured-images',
    ],

    'comment_moderation' => env('BLOG_COMMENT_MODERATION', true),

    'search' => [
        'min_length' => 3,
        'max_results' => 50,
    ],
];
