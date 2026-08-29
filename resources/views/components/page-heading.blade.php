@props([
    'eyebrow' => null,
    'title' => 'Admin',
    'description' => null,
    'breadcrumbs' => null,
])

<header class="sf-page-heading">
    <div class="sf-page-heading-main">
        @if($breadcrumbs)
            <nav class="sf-breadcrumbs" aria-label="Breadcrumb">
                {{ $breadcrumbs }}
            </nav>
        @endif

        @if(is_string($eyebrow) && trim($eyebrow) !== '')
            <p class="sf-eyebrow"><span aria-hidden="true"></span>{{ $eyebrow }}</p>
        @endif

        <h1 class="sf-page-title">{{ $title }}</h1>

        @if(is_string($description) && trim($description) !== '')
            <p class="sf-page-subtitle">{{ $description }}</p>
        @endif

        @isset($meta)
            <div class="sf-page-meta">
                {{ $meta }}
            </div>
        @endisset
    </div>

    @isset($actions)
        <div class="sf-page-actions">
            {{ $actions }}
        </div>
    @endisset
</header>
