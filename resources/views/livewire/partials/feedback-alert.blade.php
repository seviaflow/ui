@php
    $type = $type ?? 'info';
    $icon = match ($type) {
        'success' => 'check-circle',
        'danger' => 'x-circle',
        'warning' => 'exclamation-triangle',
        default => 'information-circle',
    };
    $classes = match ($type) {
        'success' => 'sf-alert sf-alert-success',
        'danger' => 'sf-alert sf-alert-danger',
        'warning' => 'sf-alert sf-alert-warning',
        default => 'sf-alert sf-alert-info',
    };
@endphp

<div class="{{ $classes }}" role="{{ $type === 'danger' ? 'alert' : 'status' }}" aria-live="{{ $type === 'danger' ? 'assertive' : 'polite' }}" aria-atomic="true">
    <div class="flex items-start gap-2">
        <span class="sf-alert-icon" aria-hidden="true"><x-seviaflow-ui::icon :name="$icon" /></span>
        <p>{{ $message }}</p>
    </div>
</div>
