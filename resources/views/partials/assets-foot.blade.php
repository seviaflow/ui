<script src="{{ asset(config('seviaflow-ui.assets.path', 'vendor/seviaflow/ui').'/seviaflow.js') }}" defer></script>
@if (($includeLivewire ?? true) === true)
    @livewireScripts
@endif
