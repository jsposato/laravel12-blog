@props(['comment', 'canModerate' => false])

<div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
    <div class="flex items-start justify-between mb-2">
        <div class="flex items-center">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ $comment->user->name }}
            </div>
            <span class="mx-2 text-gray-400">•</span>
            <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $comment->created_at->toIso8601String() }}">
                {{ $comment->created_at->diffForHumans() }}
            </time>
            @if(!$comment->isApproved())
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    Pending Approval
                </span>
            @endif
        </div>

        @if($canModerate || auth()->id() === $comment->user_id)
            <div class="flex items-center gap-2">
                @can('approve', $comment)
                    @if(!$comment->isApproved())
                        <form action="{{ route('comments.approve', $comment) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="text-sm text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 font-medium">
                                Approve
                            </button>
                        </form>
                    @endif
                @endcan

                @can('delete', $comment)
                    <form action="{{ route('comments.destroy', $comment) }}"
                          method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this comment?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        @endif
    </div>

    <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">
        {{ $comment->body }}
    </div>
</div>
