@extends('layouts.public')

@section('title', 'My chats')

@section('content')
    <div class="wrap" style="padding:34px 0 64px">
        <h1>My chats</h1>
        <p class="muted">One conversation per shop and product. Reopening an enquiry returns to the same thread.</p>

        <div class="card mt-2">
            <div class="card-head">
                <h2>Conversations</h2>
                <a href="{{ route('client.home') }}" class="btn btn-ghost btn-sm">Browse more materials</a>
            </div>

            @if ($conversations->isEmpty())
                <x-empty-state title="No conversations yet" glyph="✉"
                    message="Open a product and start a chat with the shop.">
                    <a href="{{ route('client.home') }}" class="btn btn-primary btn-sm mt-2">Browse the catalog</a>
                </x-empty-state>
            @else
                <div>
                    @foreach ($conversations as $conversation)
                        @php $count = $unread[$conversation->id] ?? 0; @endphp
                        <a class="chat-list-item" href="{{ route('client.chat.show', $conversation) }}">
                            <div class="row">
                                <span class="who">
                                    @if ($count) <span class="dot" aria-hidden="true"></span> @endif
                                    {{ $conversation->shop?->name }}
                                </span>
                                <span class="when">{{ $conversation->last_message_at?->diffForHumans() ?? $conversation->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="prev">
                                {{ $conversation->context_snapshot['product_name'] ?? 'Listing' }}
                                @if ($conversation->latestMessage)
                                    · {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 64) }}
                                @endif
                            </div>
                            @if ($count)
                                <span class="badge badge-offer mt-1">{{ $count }} unread</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="card-foot">{{ $conversations->links('components.pagination') }}</div>
            @endif
        </div>
    </div>
@endsection
