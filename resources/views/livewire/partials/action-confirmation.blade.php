@php
    $variant = $variant ?? 'safe';
    $isDanger = $variant === 'danger';
    $panelClass = $isDanger ? 'sf-confirm-panel sf-confirm-panel-danger' : 'sf-confirm-panel sf-confirm-panel-safe';
    $iconClass = $isDanger ? 'sf-confirm-icon sf-confirm-icon-danger' : 'sf-confirm-icon sf-confirm-icon-safe';
    $icon = $isDanger ? '!' : 'i';
    $consequences = $consequences ?? [];
    $confirmationId = (string) ($id ?? 'sf-confirmation');
@endphp

<div id="{{ $confirmationId }}" class="{{ $panelClass }}" role="alertdialog" aria-live="assertive" aria-atomic="true" aria-labelledby="{{ $confirmationId }}-title" aria-describedby="{{ $confirmationId }}-message">
    <div class="sf-confirm-header">
        <span class="{{ $iconClass }}" aria-hidden="true">{{ $icon }}</span>
        <div>
            <p id="{{ $confirmationId }}-title" class="sf-confirm-title">{{ $title }}</p>
            <p id="{{ $confirmationId }}-message" class="sf-confirm-message">{{ $message }}</p>
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
