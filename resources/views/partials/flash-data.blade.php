@if (session('status'))
    <div data-flash="{{ session('status') }}" hidden></div>
@endif
@if (session('error'))
    <div data-flash="{{ session('error') }}" data-flash-error="1" hidden></div>
@endif
