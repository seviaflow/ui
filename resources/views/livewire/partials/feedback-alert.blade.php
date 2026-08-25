@php
    $type = $type ?? 'info';
    $icon = match ($type) {
        'success' => '✓',
        'danger' => '!',
        'warning' => '!',
        default => 'i',
    };
    $classes = match ($type) {
        'success' => 'sf-alert sf-alert-success',
        'danger' => 'sf-alert sf-alert-danger',
        'warning' => 'sf-alert sf-alert-warning',
        default => 'sf-alert sf-alert-info',
    };
@endphp

<div class="{{ $classes }}" role="status">
    <div class="flex items-start gap-2">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/70 text-xs font-bold">{{ $icon }}</span>
        <p>{{ $message }}</p>
    </div>
</div>
