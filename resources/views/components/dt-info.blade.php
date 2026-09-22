@props(['paginator', 'label' => 'entries', 'q' => null])

@php
    $first = $paginator->firstItem();
    $last = $paginator->lastItem();
@endphp

<p class="dt-info">
    Showing {{ $first ? $first.'–'.$last : '0' }} of {{ $paginator->total() }}
    {{ \Illuminate\Support\Str::plural($label, $paginator->total()) }}
    @if (filled($q))
        <span class="dt-info-match">· matching “{{ $q }}”</span>
    @endif
</p>
