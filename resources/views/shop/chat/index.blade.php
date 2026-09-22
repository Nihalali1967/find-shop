@extends('layouts.shop')

@section('title', 'Chat')
@section('nav', 'chat')
@section('page-title', 'Chat')

@section('content')
    <div class="card">
        <div class="card-head">
            <h2>Enquiries</h2>
            <span class="muted" style="font-size:.85rem">One thread per buyer and product</span>
        </div>

        @if ($conversations->isEmpty())
            <x-empty-state title="No enquiries yet" glyph="✉"
                message="When a buyer asks about one of your published products, it appears here." />
        @else
            <div>
                @foreach ($conversations as $conversation)
                    @php $count = $unread[$conversation->id] ?? 0; @endphp
                    <a class="chat-list-item" href="{{ route('shop.chat.show', $conversation) }}">
                        <div class="row">
                            <span class="who">
                                @if ($count) <span class="dot" aria-hidden="true"></span> @endif
                                {{ $conversation->client?->name ?? 'Buyer' }}
                            </span>
                            <span class="when">{{ $conversation->last_message_at?->diffForHumans() ?? $conversation->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="prev">
                            {{ $conversation->context_snapshot['product_name'] ?? 'Listing' }}
                            @if ($conversation->latestMessage)
                                · {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 56) }}
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
@endsection
