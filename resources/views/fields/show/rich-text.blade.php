@php
    $safeRichText = $field instanceof \SeviaFlow\Core\Resources\Fields\RichTextField
        ? $field->sanitizeForDisplay($value ?? null)
        : '';
@endphp
@if($safeRichText === '')
    <span class="sf-richtext-empty">—</span>
@else
    <div class="sf-richtext-output" data-sf-richtext-output>{!! $safeRichText !!}</div>
@endif
