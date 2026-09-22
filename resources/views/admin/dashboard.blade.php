@extends('layouts.admin')

@section('title', 'Dashboard')
@section('nav', 'dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="stat-grid">
        <div class="stat is-accent">
            <div class="k">Shops</div>
            <div class="v">{{ $stats['shops'] }}</div>
            <div class="sub">{{ $stats['active'] }} active · {{ $stats['inactive'] }} inactive · {{ $stats['suspended'] }} suspended</div>
        </div>
        <div class="stat">
            <div class="k">Products</div>
            <div class="v">{{ $stats['products'] }}</div>
            <div class="sub">{{ $stats['published'] }} published</div>
        </div>
        <div class="stat">
            <div class="k">Taxonomy</div>
            <div class="v">{{ $stats['categories'] }}</div>
            <div class="sub">{{ $stats['subcategories'] }} subcategories</div>
        </div>
        <div class="stat">
            <div class="k">Pending invitations</div>
            <div class="v">{{ $stats['invitations'] }}</div>
            <div class="sub">Awaiting owner claim</div>
        </div>
    </div>

    <div class="grid grid-2" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
        <div class="card">
            <div class="card-head">
                <h2>Recent shop registrations</h2>
                <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost btn-sm">All shops</a>
            </div>
            @if ($recentShops->isEmpty())
                <x-empty-state title="No shops yet" glyph="◧" message="Shops appear here after owners complete registration." />
            @else
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="table">
                        <thead>
                            <tr><th>Shop</th><th>Owner</th><th>Location</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentShops as $shop)
                                <tr>
                                    <td><a href="{{ route('admin.shops.show', $shop) }}"><strong>{{ $shop->name }}</strong></a></td>
                                    <td class="nowrap">{{ $shop->owner?->phone }}</td>
                                    <td>{{ $shop->locality }} · {{ $shop->pincode }}</td>
                                    <td><x-status-badge :status="$shop->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Status changes &amp; security</h2></div>
            <div class="card-body">
                @if ($recentAudit->isEmpty())
                    <p class="muted mb-0">No administrative actions recorded yet.</p>
                @else
                    <ul class="timeline">
                        @foreach ($recentAudit as $log)
                            <li>
                                <div class="flex between wrap-gap">
                                    <strong>{{ str_replace(['.', '_'], [' · ', ' '], $log->action) }}</strong>
                                    <span class="muted" style="font-size:.78rem">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="muted" style="font-size:.78rem">
                                    {{ class_basename($log->actor_type ?? 'system') }} #{{ $log->actor_id ?? '—' }}
                                    @if ($log->subject_type) → {{ $log->subject_type }} #{{ $log->subject_id }} @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
