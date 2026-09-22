@extends('layouts.public')

@section('title', 'Chat with '.$conversation->shop->name)

@section('content')
    <div class="wrap" style="padding:26px 0 40px">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('client.chat.index') }}">My chats</a>
            <span aria-hidden="true">/</span>
            <span>{{ $conversation->shop->name }}</span>
        </nav>

        <div class="chat-panel-shell" style="max-width:900px">
            @include('partials.chat-thread', [
                'conversation' => $conversation,
                'messages' => $messages,
                'myType' => 'user',
                'myId' => auth()->id(),
                'pollUrl' => route('client.chat.poll', $conversation),
                'sendUrl' => route('client.chat.send', $conversation),
                'readUrl' => route('client.chat.read', $conversation),
                'frozen' => false,
            ])
        </div>

        <p class="hint mt-2">
            This thread keeps its original product context even if the listing changes. The shop cannot see your details beyond what you share here.
        </p>
    </div>
@endsection
