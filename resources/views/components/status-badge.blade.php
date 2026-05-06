@props([
    'status',
    'label' => null,
])

@php
    $statusClass = in_array($status, ['running', 'success', 'failed', 'paused', 'pending', 'compensated'], true)
        ? $status
        : 'pending';

    $resolvedLabel = $label ?? match ($status) {
        'running' => 'Running',
        'success' => 'Succeeded',
        'failed' => 'Failed',
        'paused' => 'Paused',
        'pending' => 'Pending',
        'compensated' => 'Compensated',
        default => ucfirst((string) $status),
    };
@endphp

<span class="badge {{ $statusClass }}">
    <span class="dot"></span>
    <span>{{ $resolvedLabel }}</span>
</span>
