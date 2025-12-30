<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Post::with('user')->published();

        // Handle search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $posts = $query->latest('published_at')
                       ->paginate(config('blog.posts_per_page'))
                       ->withQueryString(); // Preserve search query in pagination links

        return view('posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Post::class);

        return view('posts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store(
                config('blog.featured_image.path'),
                config('blog.featured_image.disk')
            );
            $validated['featured_image'] = $path;
        }

        $post = Post::create($validated);

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Post created successfully!');
    }

    /**
     * Display the specified resource.
     */
    /**
     * Display the specified resource.
     */
    public function show(Post $post): View
    {
        Gate::authorize('view', $post);

        $post->load('user');

        // Get approved comments (visible to everyone)
        $approvedComments = $post->approvedComments()->with('user')->get();

        // Get pending comments for moderation (post author only)
        $pendingComments = collect();
        if (auth()->check() && auth()->user()->can('update', $post)) {
            $pendingComments = $post->pendingComments()->with('user')->get();
        }

        // Get user's own pending comments (if not the post author)
        $userPendingComments = collect();
        if (auth()->check() && auth()->id() !== $post->user_id) {
            $userPendingComments = $post->pendingComments()
                                        ->where('user_id', auth()->id())
                                        ->with('user')
                                        ->get();
        }

        // Merge approved and user's pending comments for display
        $visibleComments = $approvedComments->merge($userPendingComments)->sortByDesc('created_at');

        return view('posts.show', compact('post', 'visibleComments', 'pendingComments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validated();

        // Handle featured image removal
        if ($request->boolean('remove_featured_image') && $post->featured_image) {
            Storage::disk(config('blog.featured_image.disk'))->delete($post->featured_image);
            $validated['featured_image'] = null;
        }

        // Handle new featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($post->featured_image) {
                Storage::disk(config('blog.featured_image.disk'))->delete($post->featured_image);
            }

            $path = $request->file('featured_image')->store(
                config('blog.featured_image.path'),
                config('blog.featured_image.disk')
            );
            $validated['featured_image'] = $path;
        }

        $post->update($validated);

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Post updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        // Delete featured image if exists
        if ($post->featured_image) {
            Storage::disk(config('blog.featured_image.disk'))->delete($post->featured_image);
        }

        $post->delete();

        return redirect()
            ->route('posts.index')
            ->with('success', 'Post deleted successfully!');
    }
}
