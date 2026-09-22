@props(['col'])

@php
    $active = request('sort') === $col;
    $direction = request('dir') === 'desc' ? 'desc' : 'asc';
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';

    $query = request()->query();
    $query['sort'] = $col;
    $query['dir'] = $next;
    unset($query['page']);
@endphp

<th {{ $attributes->merge(['class' => 'dt-sortable'.($active ? ' is-sorted' : '')]) }} scope="col"
    aria-sort="{{ $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a href="{{ request()->url().'?'.http_build_query($query) }}" rel="nofollow">
        <span>{{ $slot }}</span>
        <span class="dt-sort-ind" aria-hidden="true">{{ $active ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </a>
</th>
