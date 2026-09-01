<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="sf-card-title">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="sf-card-description mt-1">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    <div class="sf-dashboard-chart-frame mt-4">
        <canvas
            class="sf-dashboard-chart-canvas"
            data-sf-chart="1"
            data-chart-type="{{ $chartType }}"
            data-chart-data='@json($chartData)'
            aria-label="{{ $widget['label'] }}"
            role="img"
        ></canvas>
    </div>
</div>
