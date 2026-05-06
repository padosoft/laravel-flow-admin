{{--
    Inline-SVG lucide-style icon (stroke 1.75, currentColor) ported verbatim
    from `.design-source/project/ui.jsx` `I` map. Usage:
        <x-flow-admin::icon name="home" size="15" />
    `name` is required and matches the keys below; an unknown name renders
    a small visible placeholder rectangle so the missing icon is obvious in
    QA rather than collapsing to empty space.
--}}
@props([
    'name',
    'size' => 16,
    'fill' => 'none',
])
@php
    $paths = [
        'logo'           => '<path d="M5 5h10l4 4v10H5z"/><path d="M9 13l2 2 4-4"/>',
        'home'           => '<path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'runs'           => '<path d="M3 6h18M3 12h18M3 18h12"/>',
        'approvals'      => '<path d="M5 12l4 4 10-10"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'outbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'definitions'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
        'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.16.68.4.92.7"/>',
        'search'         => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'bell'           => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'moon'           => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'refresh'        => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'pause'          => '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>',
        'play'           => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'filter'         => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'plus'           => '<path d="M12 5v14M5 12h14"/>',
        'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
        'chevron-left'   => '<path d="m15 18-6-6 6-6"/>',
        'x'              => '<path d="M18 6 6 18M6 6l12 12"/>',
        'copy'           => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'external'       => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
        'check'          => '<path d="M20 6 9 17l-5-5"/>',
        'alert-triangle' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'activity'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'send'           => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'arrow-up'       => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'arrow-down'     => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'arrow-right'    => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'sparkle'        => '<path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3z"/>',
        'replay'         => '<path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 10 9 10"/>',
        'cancel'         => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'code'           => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'user'           => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'hash'           => '<line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>',
    ];

    $paint = $paths[$name] ?? '<rect x="4" y="4" width="16" height="16" rx="2"/>';
    // The `play` icon ships with a filled-polygon variant in the design;
    // honour an explicit `fill="currentColor"` request without breaking
    // the `none` default the rest of the icons rely on.
    $resolvedFill = $fill === 'currentColor' ? 'currentColor' : 'none';
@endphp
<svg viewBox="0 0 24 24"
     width="{{ (int) $size }}"
     height="{{ (int) $size }}"
     fill="{{ $resolvedFill }}"
     stroke="currentColor"
     stroke-width="1.75"
     stroke-linecap="round"
     stroke-linejoin="round"
     aria-hidden="true"
     data-icon="{{ $name }}"
     {{ $attributes }}>{!! $paint !!}</svg>
