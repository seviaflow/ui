@php
    $pageName = $paginator->getPageName();
    $currentPage = $paginator->currentPage();
    $hasLastPage = method_exists($paginator, 'lastPage');
    $lastPage = $hasLastPage ? $paginator->lastPage() : null;
    $firstItem = method_exists($paginator, 'firstItem') ? ($paginator->firstItem() ?? 0) : null;
    $lastItem = method_exists($paginator, 'lastItem') ? ($paginator->lastItem() ?? 0) : null;
    $total = method_exists($paginator, 'total') ? $paginator->total() : null;
    $elements = $elements ?? [];
@endphp

@if($paginator->hasPages() || $total !== null)
    <nav class="sf-pagination-nav" role="navigation" aria-label="{{ __('seviaflow-ui::panel.pagination_navigation') }}">
        @if($total !== null && $firstItem !== null && $lastItem !== null)
            <p class="sf-pagination-summary">
                {{ __('seviaflow-ui::panel.pagination_summary', ['first' => $firstItem, 'last' => $lastItem, 'total' => $total]) }}
            </p>
        @endif

        @if($paginator->hasPages())
            <div class="sf-pagination-mobile" aria-label="{{ __('seviaflow-ui::panel.mobile_pagination') }}">
                @if($paginator->onFirstPage())
                    <button type="button" class="sf-pagination-button" aria-label="{{ __('seviaflow-ui::panel.previous_page') }}" disabled>{{ __('seviaflow-ui::panel.previous') }}</button>
                @else
                    <button type="button" class="sf-pagination-button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="{{ __('seviaflow-ui::panel.previous_page') }}">{{ __('seviaflow-ui::panel.previous') }}</button>
                @endif

                @if($lastPage !== null)
                    <span class="sf-pagination-mobile-status">{{ __('seviaflow-ui::panel.pagination_page_of', ['current' => $currentPage, 'last' => $lastPage]) }}</span>
                @else
                    <span class="sf-pagination-mobile-status">{{ __('seviaflow-ui::panel.pagination_page', ['page' => $currentPage]) }}</span>
                @endif

                @if($paginator->hasMorePages())
                    <button type="button" class="sf-pagination-button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="{{ __('seviaflow-ui::panel.next_page') }}">{{ __('seviaflow-ui::panel.next') }}</button>
                @else
                    <button type="button" class="sf-pagination-button" aria-label="{{ __('seviaflow-ui::panel.next_page') }}" disabled>{{ __('seviaflow-ui::panel.next') }}</button>
                @endif
            </div>

            <div class="sf-pagination-desktop" aria-label="{{ __('seviaflow-ui::panel.page_navigation') }}">
                @if($paginator->onFirstPage())
                    <button type="button" class="sf-pagination-button" aria-label="{{ __('seviaflow-ui::panel.previous_page') }}" disabled>{{ __('seviaflow-ui::panel.previous') }}</button>
                @else
                    <button type="button" class="sf-pagination-button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="{{ __('seviaflow-ui::panel.previous_page') }}">{{ __('seviaflow-ui::panel.previous') }}</button>
                @endif

                @foreach($elements as $element)
                    @if(is_string($element))
                        <span class="sf-pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if(is_array($element))
                        @foreach($element as $page => $url)
                            @if($page === $currentPage)
                                <span class="sf-pagination-page sf-pagination-page-current" aria-current="page">{{ $page }}</span>
                            @else
                                <button
                                    type="button"
                                    class="sf-pagination-page"
                                    wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                                    wire:loading.attr="disabled"
                                    aria-label="{{ __('seviaflow-ui::panel.pagination_go_to', ['page' => $page]) }}"
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <button type="button" class="sf-pagination-button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="{{ __('seviaflow-ui::panel.next_page') }}">{{ __('seviaflow-ui::panel.next') }}</button>
                @else
                    <button type="button" class="sf-pagination-button" aria-label="{{ __('seviaflow-ui::panel.next_page') }}" disabled>{{ __('seviaflow-ui::panel.next') }}</button>
                @endif
            </div>
        @endif
    </nav>
@endif
