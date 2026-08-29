@props([
    'name' => null,
])

{!! \SeviaFlow\Ui\Icons\IconRenderer::render(is_string($name) ? $name : null, (string) $attributes->get('class', '')) !!}
