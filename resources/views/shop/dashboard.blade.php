@extends('layouts.shop')

@section('title', 'Dashboard')
@section('nav', 'dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="stat-grid">
        <div class="stat">
            <div class="k">Products</div>
            <div class="v">{{ $counts['products'] }}</div>
            <div class="sub">Non-deleted listings</div>
        </div>
        <div class="stat">
            <div class="k">Published</div>
            <div class="v">{{ $counts['published'] }}</div>
            <div class="sub">Visible to buyers</div>
        </div>
        <div class="stat">
            <div class="k">Drafts</div>
            <div class="v">{{ $counts['draft'] }}</div>
            <div class="sub">Hidden until published</div>
        </div>
        <div class="stat is-accent">
            <div class="k">Conversations</div>
            <div class="v">{{ $counts['conversations'] }}</div>
            <div class="sub">{{ $counts['unread_conversations'] }} with unread messages</div>
        </div>
    </div>

    <div class="grid grid-2" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:start">
        <div class="card">
            <div class="card-head">
                <h2>Recent products</h2>
                <div class="btn-row">
                    <a href="{{ route('shop.products.create') }}" class="btn btn-sm btn-accent">Add product</a>
                    <a href="{{ route('shop.products.index') }}" class="btn btn-sm btn-ghost">View all</a>
                </div>
            </div>

            @if ($recentProducts->isEmpty())
                <x-empty-state title="No products yet" glyph="▦"
                    message="Add your first listing to appear in the public catalog.">
                    <a href="{{ route('shop.products.create') }}" class="btn btn-primary btn-sm mt-2">Create a product</a>
                </x-empty-state>
            @else
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentProducts as $product)
                                <tr>
                                    <td>
                                        <div class="flex center gap-1">
                                            @php $cover = $product->images->first(); @endphp
                                            @if ($cover)
                                                <img src="{{ $cover->thumbUrl() }}"
                                                     alt="" width="46" height="38" style="border-radius:8px;object-fit:cover">
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                            <div style="min-width:0">
                                                <a href="{{ route('shop.products.edit', $product) }}"><strong>{{ $product->name }}</strong></a>
                                                <div class="muted" style="font-size:.78rem">{{ $product->subcategory?->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="nowrap">₹{{ \App\Support\Money::format($product->effectivePricePaise(), false) }}</td>
                                    <td><x-status-badge :status="$product->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h2>Recent enquiries</h2>
                <a href="{{ route('shop.chat.index') }}" class="btn btn-sm btn-ghost">Open inbox</a>
            </div>

            @if ($recentConversations->isEmpty())
                <x-empty-state title="No enquiries yet" glyph="✉"
                    message="Buyer questions about your listings will appear here." />
            @else
                <div>
                    @foreach ($recentConversations as $conversation)
                        <a class="chat-list-item" href="{{ route('shop.chat.show', $conversation) }}">
                            <div class="row">
                                <span class="who">{{ $conversation->client?->name ?? 'Buyer' }}</span>
                                <span class="when">{{ $conversation->last_message_at?->diffForHumans() ?? 'new' }}</span>
                            </div>
                            <div class="prev">
                                {{ $conversation->context_snapshot['product_name'] ?? 'Listing' }}
                                @if ($conversation->latestMessage)
                                    · {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 48) }}
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <p class="hint mt-3">
        This release has no checkout, so revenue figures would be misleading. Aggregate listings and enquiry activity instead.
    </p>
@endsection
