@extends('layouts.shop')

@section('title', 'Notifications')
@section('nav', 'notifications')
@section('page-title', 'Notifications')

@section('content')
    <div class="card">
        <div class="card-head">
            <h2>Inbox</h2>
            <form method="POST" action="{{ route('shop.notifications.readAll') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Mark all as read</button>
            </form>
        </div>

        @if ($notifications->isEmpty())
            <x-empty-state title="No notifications yet" glyph="◔"
                message="New enquiries and shop status changes appear here." />
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
                        <form method="POST" action="{{ route('shop.notifications.read', $notification) }}" class="mt-1">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-ghost">
                                {{ $notification->read_at ? 'Open' : 'Mark read & open' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
            <div class="card-foot">{{ $notifications->links('components.pagination') }}</div>
        @endif
    </div>
@endsection
