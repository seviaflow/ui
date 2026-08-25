@php
    $variant = $variant ?? 'safe';
    $isDanger = $variant === 'danger';
    $panelClass = $isDanger ? 'sf-confirm-panel sf-confirm-panel-danger' : 'sf-confirm-panel sf-confirm-panel-safe';
    $iconClass = $isDanger ? 'sf-confirm-icon sf-confirm-icon-danger' : 'sf-confirm-icon sf-confirm-icon-safe';
    $icon = $isDanger ? '!' : 'i';
    $consequences = $consequences ?? [];
@endphp

<div class="{{ $panelClass }}">
    <div class="sf-confirm-header">
        <span class="{{ $iconClass }}" aria-hidden="true">{{ $icon }}</span>
        <div>
            <p class="sf-confirm-title">{{ $title }}</p>
            <p class="sf-confirm-message">{{ $message }}</p>
        </div>
    </div>

    @if($consequences !== [])
        <div class="sf-confirm-consequences">
            @foreach($consequences as $line)
                <p>• {{ $line }}</p>
            @endforeach
        </div>
    @endif

    <div class="sf-confirm-actions">
        {{ $slot }}
    </div>
</div>
