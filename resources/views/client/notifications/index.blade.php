@extends('layouts.public')

@section('title', 'Notifications')

@section('content')
    <div class="wrap" style="padding:34px 0 64px;max-width:820px">
        <div class="flex between center wrap-gap">
            <h1 class="mb-0">Notifications</h1>
            <form method="POST" action="{{ route('client.notifications.readAll') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Mark all as read</button>
            </form>
        </div>

        <div class="card mt-2">
            @if ($notifications->isEmpty())
                <x-empty-state title="Nothing here yet" glyph="◔"
                    message="New shop replies and status updates appear here." />
            @else
                <div>
                    @foreach ($notifications as $notification)
                        <div class="chat-list-item" style="border-left:3px solid {{ $notification->read_at ? 'transparent' : 'var(--forest)' }}">
                            <div class="row">
                                <span class="who">
                                    @if (! $notification->read_at) <span class="dot" aria-hidden="true"></span> @endif
                                    {{ $notification->title }}
                                </span>
                                <span class="when">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($notification->body)
                                <div class="prev" style="white-space:normal">{{ $notification->body }}</div>
                            @endif
                            <div class="table-actions mt-1">
                                <form method="POST" action="{{ route('client.notifications.read', $notification) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-ghost">
                                        {{ $notification->read_at ? 'Open' : 'Mark read & open' }}
                                    </button>
                                </form>
                                <span class="badge badge-info">{{ str_replace('_', ' ', $notification->type) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-foot">{{ $notifications->links('components.pagination') }}</div>
            @endif
        </div>
    </div>
@endsection
