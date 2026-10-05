@php $color = is_string($value ?? null) ? $value : ''; @endphp
@if($color === '')
    —
@else
    <span class="sf-color-value"><span class="sf-color-swatch" style="background-color: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#000000' }}"></span><span>{{ $color }}</span></span>
@endif
