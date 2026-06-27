@php
    $enabled = config('ecoute.enabled') &&
               in_array(app()->environment(), config('ecoute.environments', []));
@endphp

@if ($enabled && auth()->user() && \Illuminate\Support\Facades\Gate::forUser(auth()->user())->check('ecoute-admin'))
    <script
        src="{{ asset('vendor/ecoute/overlay.js') }}?v={{ file_exists(public_path('vendor/ecoute/overlay.js')) ? filemtime(public_path('vendor/ecoute/overlay.js')) : time() }}"
        data-endpoint="{{ route('ecoute.capture') }}"
        data-preview="{{ route('ecoute.preview') }}"
        data-templates="{{ route('ecoute.templates') }}"
        data-csrf="{{ csrf_token() }}"
        data-shortcut="{{ config('ecoute.shortcut', 'ctrl+shift+e') }}"
        data-diagnostics="{{ config('ecoute.diagnostics.enabled', false) ? 'true' : 'false' }}"
        @if(config('ecoute.csp.nonce')) nonce="{{ csp_nonce() }}" @endif
        defer
    ></script>
@endif
