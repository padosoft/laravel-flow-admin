@php
    /**
     * Breadcrumbs partial — Macro 3.2.
     *
     * Renders the design-source `crumbs` shape from `shell.jsx::Topbar`.
     * Each crumb is `['label' => string, 'url' => ?string, 'mono' => bool]`.
     * - When `url` is null/empty → rendered as <b> (current page).
     * - When `url` is set → rendered as muted clickable.
     * - `mono` swaps to the design's monospace stack (used for run IDs).
     */
    $breadcrumbs = $breadcrumbs ?? [];
@endphp
<nav class="crumbs" data-testid="flow-admin-breadcrumbs" aria-label="Breadcrumb">
    <span class="muted">Flow</span>
    @foreach ($breadcrumbs as $crumb)
        <span class="sep" aria-hidden="true">
            <x-flow-admin::icon name="chevron-right" size="12" />
        </span>
        @php
            $label = $crumb['label'] ?? '';
            $url = $crumb['url'] ?? null;
            $isMono = ! empty($crumb['mono']);
        @endphp
        @if ($url)
            <a href="{{ $url }}" class="muted{{ $isMono ? ' mono' : '' }}">{{ $label }}</a>
        @else
            <b @class(['mono' => $isMono]) aria-current="page">{{ $label }}</b>
        @endif
    @endforeach
</nav>
