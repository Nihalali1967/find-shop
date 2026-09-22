@php
    $latestId = $messages->max('id') ?? 0;
    $cover = $conversation->product?->images->first();
    $frozen = $frozen ?? false;
@endphp

<div class="chat-panel"
     data-chat
     data-my-type="{{ $myType }}"
     data-my-id="{{ $myId }}"
     data-after="{{ $latestId }}"
     data-interval="{{ config('marketplace.chat.poll_interval_ms') }}"
     data-poll-url="{{ $pollUrl }}"
     data-send-url="{{ $sendUrl }}"
     data-read-url="{{ $readUrl }}"
     data-frozen="{{ $frozen ? '1' : '0' }}">

    <div class="chat-context">
        @if ($cover)
            <img src="{{ $cover->thumbUrl() }}" alt="">
        @else
            <span class="no-img" aria-hidden="true">No image</span>
        @endif
        <div style="min-width:0">
            <strong>{{ $conversation->product?->name ?? 'Listing removed' }}</strong>
            <div class="muted" style="font-size:.83rem">
                {{ $conversation->product?->trashed() ? 'This listing is no longer available' : ($conversation->product?->title ?? 'Context kept from the original enquiry') }}
            </div>
        </div>
        <div class="spacer" style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <span class="conn-state" data-conn-state><span class="led" aria-hidden="true"></span><span data-conn-label>{{ $frozen ? 'Read only' : 'Connected' }}</span></span>
        </div>
    </div>

    <div class="chat-scroll" data-chat-scroll>
        <div data-chat-messages style="display:contents">
            @foreach ($messages as $message)
                @php $mine = $message->sender_type === $myType && (int) $message->sender_id === (int) $myId; @endphp
                <div class="msg {{ $mine ? 'msg-out' : 'msg-in' }}" data-message-id="{{ $message->id }}">
                    <div class="msg-body">{{ trim($message->body) }}</div>
                    <div class="meta">{{ $message->created_at->format('d M Y, H:i') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($frozen)
        <div class="chat-compose">
            <div class="alert alert-warn" style="flex:1">
                <div>This conversation is frozen. You can read the history but cannot send new messages while the shop is restricted.</div>
            </div>
            <button type="button" class="btn btn-primary" disabled>Send</button>
        </div>
    @else
        <form class="chat-compose" data-chat-form>
            @csrf
            <label class="sr-only" for="chat-body">Message</label>
            <textarea id="chat-body" name="body" rows="1" maxlength="{{ config('marketplace.chat.max_message_length') }}"
                      placeholder="Write a message… (Enter to send, Shift+Enter for a new line)"></textarea>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    @endif
</div>
