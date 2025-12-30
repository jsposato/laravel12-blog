<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create New Post') }}
            </h2>
            <a href="{{ route('posts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                ← Back to posts
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-10">
                    <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Title -->
                        <div class="mb-6">
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title"
                                          class="block mt-1 w-full"
                                          type="text"
                                          name="title"
                                          :value="old('title')"
                                          required
                                          autofocus
                                          placeholder="Enter your post title" />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Body -->
                        <div class="mb-6">
                            <x-input-label for="body" :value="__('Content')" />
                            <textarea id="body"
                                      name="body"
                                      rows="12"
                                      class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full"
                                      required
                                      placeholder="Write your post content here...">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum 10 characters required.</p>
                        </div>

                        <!-- Featured Image -->
                        <div class="mb-6">
                            <x-input-label for="featured_image" :value="__('Featured Image (Optional)')" />
                            <input id="featured_image"
                                   type="file"
                                   name="featured_image"
                                   accept="image/*"
                                   class="block mt-1 w-full text-sm text-gray-500 dark:text-gray-400
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100
                                          dark:file:bg-gray-700 dark:file:text-gray-300" />
                            <x-input-error :messages="$errors->get('featured_image')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Max size: {{ config('blog.featured_image.max_size') }}KB.
                                Allowed types: {{ implode(', ', config('blog.featured_image.allowed_types')) }}
                            </p>
                        </div>

                        <!-- Published At -->
                        <div class="mb-6">
                            <x-input-label for="published_at" :value="__('Publish Date (Optional)')" />
                            <x-text-input id="published_at"
                                          class="block mt-1 w-full"
                                          type="datetime-local"
                                          name="published_at"
                                          :value="old('published_at')" />
                            <x-input-error :messages="$errors->get('published_at')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave empty to save as draft. Set a future date to schedule.</p>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('posts.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                Cancel
                            </a>

                            <x-primary-button>
                                {{ __('Create Post') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
