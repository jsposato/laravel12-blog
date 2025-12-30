<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Blog Post') }}
            </h2>
            <a href="{{ route('posts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                ← Back to posts
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <article class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                @if($post->featured_image)
                    <div class="p-6 sm:p-10 pb-0">
                        <div class="max-w-xl mx-auto mb-8">
                            <img src="{{ asset('storage/' . $post->featured_image) }}"
                                 alt="{{ $post->title }}"
                                 class="w-full h-48 sm:h-56 object-cover rounded-lg shadow-md">
                        </div>
                    </div>
                @endif

                <div class="p-6 sm:p-10 {{ $post->featured_image ? 'pt-0' : '' }}">
                    <header class="mb-8">
                        <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            {{ $post->title }}
                        </h1>

                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $post->user->name }}</span>

                            @if($post->published_at)
                                <span class="mx-2">•</span>
                                <time datetime="{{ $post->published_at->toIso8601String() }}">
                                    {{ $post->published_at->format('F d, Y') }}
                                </time>
                            @endif

                            @if(!$post->isPublished())
                                <span class="mx-2">•</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    Draft
                                </span>
                            @endif
                        </div>
                    </header>

                    <div class="prose prose-lg dark:prose-invert max-w-none mb-8">
                        <div class="whitespace-pre-wrap break-words text-gray-900 dark:text-gray-100">
                            {{ $post->body }}
                        </div>
                    </div>

                    @auth
                        @can('update', $post)
                            <div class="mb-8 pb-8 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                                <a href="{{ route('posts.edit', $post) }}"
                                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Edit Post
                                </a>

                                <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Delete Post
                                    </button>
                                </form>
                            </div>
                        @endcan
                    @endauth

                    {{-- Comments Section --}}
                    <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                            Comments ({{ $post->approvedComments()->count() }})
                        </h2>

                        {{-- Comment Form (only for authenticated users on published posts) --}}
                        @auth
                            @if($post->isPublished())
                                <div class="mb-8">
                                    <form action="{{ route('comments.store', $post) }}" method="POST">
                                        @csrf
                                        <div class="mb-4">
                                            <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Leave a comment
                                            </label>
                                            <textarea id="body"
                                                      name="body"
                                                      rows="4"
                                                      class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block w-full"
                                                      placeholder="Write your comment here..."
                                                      required>{{ old('body') }}</textarea>
                                            @error('body')
                                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <x-primary-button>
                                            Post Comment
                                        </x-primary-button>
                                    </form>
                                </div>
                            @endif
                        @else
                            <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <p class="text-gray-600 dark:text-gray-400">
                                    Please <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline">log in</a> to leave a comment.
                                </p>
                            </div>
                        @endauth

                        {{-- Pending Comments (only for post author to moderate) --}}
                        @if(isset($pendingComments) && $pendingComments->count() > 0)
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                    Pending Approval ({{ $pendingComments->count() }})
                                </h3>
                                <div class="space-y-4">
                                    @foreach($pendingComments as $comment)
                                        <x-comment :comment="$comment" :can-moderate="true" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Visible Comments (approved + user's own pending) --}}
                        @if(isset($visibleComments) && $visibleComments->count() > 0)
                            <div class="space-y-4">
                                @foreach($visibleComments as $comment)
                                    <x-comment :comment="$comment" :can-moderate="auth()->check() && auth()->id() === $post->user_id" />
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                                No comments yet. Be the first to comment!
                            </p>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
