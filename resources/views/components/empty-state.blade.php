@props(['title', 'message' => null, 'glyph' => '◌'])

<div class="empty">
    <div class="glyph" aria-hidden="true">{{ $glyph }}</div>
    <h3>{{ $title }}</h3>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
