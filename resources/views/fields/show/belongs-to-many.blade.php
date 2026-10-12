@php
    $selected = ($record ?? null) instanceof \Illuminate\Database\Eloquent\Model ? $field->valueForRecord($record) : [];
    $labels = ($record ?? null) instanceof \Illuminate\Database\Eloquent\Model ? $field->resolveOptions($record, '', $selected) : [];
@endphp
@forelse($selected as $id)
    <span class="sf-multiselect-chip">{{ $labels[$id] ?? $id }}</span>
@empty
    <span>—</span>
@endforelse
