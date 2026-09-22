@extends('layouts.shop')

@section('title', 'Conversation')
@section('nav', 'chat')
@section('page-title', $conversation->client?->name ?? 'Buyer')
@section('body-class', '')

@section('content')
    <div class="flex between center wrap-gap mb-2">
        <a href="{{ route('shop.chat.index') }}" class="btn btn-ghost btn-sm">← All enquiries</a>
        @if ($conversation->product)
            <a href="{{ route('client.products.show', $conversation->product) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                View public listing
            </a>
        @endif
    </div>

    @include('partials.chat-thread', [
        'conversation' => $conversation,
        'messages' => $messages,
        'myType' => 'shop',
        'myId' => $shop->id,
        'pollUrl' => route('shop.chat.poll', $conversation),
        'sendUrl' => route('shop.chat.send', $conversation),
        'readUrl' => route('shop.chat.read', $conversation),
        'frozen' => false,
    ])
@endsection
