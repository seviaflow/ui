<div class="sf-card-body">
    <h3 class="text-base font-semibold text-slate-900">{{ $widget['label'] }}</h3>
    @if(is_string($widget['description']) && trim($widget['description']) !== '')
        <p class="mt-1 text-sm text-slate-500">{{ $widget['description'] }}</p>
    @endif

    <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">
        Widget placeholder for <span class="font-medium text-slate-700">{{ $widget['name'] }}</span>.
    </div>

    @if($widget['cacheTtl'] !== null)
        <p class="mt-2 text-xs text-slate-400">Cache TTL: {{ $widget['cacheTtl'] }}s</p>
    @endif
</div>

