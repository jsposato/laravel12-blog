<x-mail::message>
    # New Comment on Your Post

    **{{ $comment->user->name }}** left a comment on your post "**{{ $comment->post->title }}**"

    <x-mail::panel>
        {{ $comment->body }}
    </x-mail::panel>

    @if(!$comment->approved)
        This comment is awaiting your approval.
    @endif

    <x-mail::button :url="route('posts.show', $comment->post)">
        View Comment
    </x-mail::button>

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
