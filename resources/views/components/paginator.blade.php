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
    <nav class="sf-pagination-nav" role="navigation" aria-label="Pagination navigation">
        @if($total !== null && $firstItem !== null && $lastItem !== null)
            <p class="sf-pagination-summary">
                Showing {{ $firstItem }}-{{ $lastItem }} of {{ $total }}
            </p>
        @endif

        @if($paginator->hasPages())
            <div class="sf-pagination-mobile" aria-label="Mobile pagination">
                @if($paginator->onFirstPage())
                    <button type="button" class="sf-pagination-button" aria-label="Previous page" disabled>Previous</button>
                @else
                    <button type="button" class="sf-pagination-button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Previous page">Previous</button>
                @endif

                @if($lastPage !== null)
                    <span class="sf-pagination-mobile-status">Page {{ $currentPage }} of {{ $lastPage }}</span>
                @else
                    <span class="sf-pagination-mobile-status">Page {{ $currentPage }}</span>
                @endif

                @if($paginator->hasMorePages())
                    <button type="button" class="sf-pagination-button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Next page">Next</button>
                @else
                    <button type="button" class="sf-pagination-button" aria-label="Next page" disabled>Next</button>
                @endif
            </div>

            <div class="sf-pagination-desktop" aria-label="Page navigation">
                @if($paginator->onFirstPage())
                    <button type="button" class="sf-pagination-button" aria-label="Previous page" disabled>Previous</button>
                @else
                    <button type="button" class="sf-pagination-button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Previous page">Previous</button>
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
                                    aria-label="Go to page {{ $page }}"
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <button type="button" class="sf-pagination-button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Next page">Next</button>
                @else
                    <button type="button" class="sf-pagination-button" aria-label="Next page" disabled>Next</button>
                @endif
            </div>
        @endif
    </nav>
@endif
