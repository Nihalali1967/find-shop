@props([
    'paginator',
    'placeholder' => 'Search…',
    'perPageOptions' => [10, 15, 25, 50],
    'searchName' => 'q',
    'searchLabel' => null,
])

@php
    $currentPerPage = (int) $paginator->perPage();
    $options = $perPageOptions;

    if (! in_array($currentPerPage, $options, true)) {
        $options[] = $currentPerPage;
        sort($options);
    }

    $currentSearch = request($searchName);
@endphp

<form method="GET" action="{{ request()->url() }}" data-dt-form class="dt-toolbar" role="search">
    {{ $slot }}

    <div class="dt-field dt-search">
        <label class="sr-only" for="dt-search-{{ $searchName }}">{{ $searchLabel ?? $placeholder }}</label>
        <input id="dt-search-{{ $searchName }}" type="search" name="{{ $searchName }}"
               value="{{ $currentSearch }}" placeholder="{{ $placeholder }}"
               data-dt-search autocomplete="off">
    </div>

    <div class="dt-field dt-perpage">
        <label class="sr-only" for="dt-perpage-{{ $searchName }}">Rows per page</label>
        <select id="dt-perpage-{{ $searchName }}" name="per_page" data-autosubmit>
            @foreach ($options as $option)
                <option value="{{ $option }}" @selected($currentPerPage === (int) $option)>{{ $option }} / page</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="btn btn-primary btn-sm dt-apply">Apply</button>
</form>
