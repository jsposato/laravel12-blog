<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Blog Posts') }}
            </h2>
            @auth
                <a href="{{ route('posts.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Create Post') }}
                </a>
            @endauth
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Search Form --}}
            <div class="mb-6">
                <form action="{{ route('posts.index') }}" method="GET" class="flex gap-2">
                    <div class="flex-1">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search posts by title or content..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300">
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Search
                    </button>
                    @if(request('search'))
                        <a href="{{ route('posts.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Clear
                        </a>
                    @endif
                </form>

                {{-- Search Results Info --}}
                @if(request('search'))
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        @if($posts->total() > 0)
                            Found {{ $posts->total() }} {{ Str::plural('result', $posts->total()) }} for "<strong class="text-gray-900 dark:text-gray-100">{{ request('search') }}</strong>"
                        @else
                            No results found for "<strong class="text-gray-900 dark:text-gray-100">{{ request('search') }}</strong>"
                        @endif
                    </div>
                @endif
            </div>

            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if($posts->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                            @if(request('search'))
                                No posts match your search
                            @else
                                No posts yet
                            @endif
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if(request('search'))
                                Try adjusting your search terms.
                            @else
                                Get started by creating a new post.
                            @endif
                        </p>
                        @auth
                            @if(!request('search'))
                                <div class="mt-6">
                                    <a href="{{ route('posts.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                        {{ __('Create Post') }}
                                    </a>
                                </div>
                            @endif
                        @endauth
                    </div>
                </div>
            @else
                <div class="space-y-6">
                    @foreach($posts as $post)
                        <article class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-300">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-6">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $post->user->name }}</span>
                                            <span class="mx-2">•</span>
                                            <time datetime="{{ $post->published_at->toIso8601String() }}">
                                                {{ $post->published_at->format('M d, Y') }}
                                            </time>
                                        </div>

                                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                                            <a href="{{ route('posts.show', $post) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                {{ $post->title }}
                                            </a>
                                        </h2>

                                        <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3">
                                            {{ $post->excerpt }}
                                        </p>

                                        <div class="flex items-center gap-4">
                                            <a href="{{ route('posts.show', $post) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium text-sm">
                                                Read more →
                                            </a>

                                            @auth
                                                @if($post->user_id === auth()->id())
                                                    <a href="{{ route('posts.edit', $post) }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium text-sm">
                                                        Edit
                                                    </a>
                                                @endif
                                            @endauth
                                        </div>
                                    </div>

                                    @if($post->featured_image)
                                        <div class="flex-shrink-0 max-w-xl">

                                            <img src="{{ asset('storage/' . $post->featured_image) }}"
                                                 alt="{{ $post->title }}"
                                                 class="h-32 w-32 sm:h-36 sm:w-36 md:h-40 md:w-40 object-cover rounded-lg shadow-sm">
                                        </div>
                                    @else
                                        <div class="flex-shrink-0 text-xs text-red-500">
                                            No image
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
