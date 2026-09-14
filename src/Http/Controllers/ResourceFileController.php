<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Files\FilePathGuard;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\ResourceScopedQuery;
use Symfony\Component\HttpFoundation\Response;

final class ResourceFileController
{
    public function __construct(private readonly ResourceRegistry $resourceRegistry) {}

    public function download(string $resource, int|string $record, string $field): Response
    {
        try {
            $resourceClass = $this->resourceRegistry->resolve($resource);
        } catch (ResourceSlugNotRegisteredException) {
            abort(404);
        }

        $found = ResourceScopedQuery::find($resourceClass, $record);
        if ($found === null) {
            abort(404);
        }

        $user = auth()->user();
        if (! ResourceAuthorization::for($resourceClass)->canView($user, $found)) {
            abort(403);
        }

        $fileField = $this->fileField($resourceClass, $field);
        if (! $fileField instanceof FileField || ! $fileField->canRead($user, $found)) {
            abort(403);
        }

        $path = $found->getAttribute($field);
        if (! is_string($path) || ! FilePathGuard::isManaged($fileField, $path)) {
            abort(404);
        }

        $disk = Storage::disk($fileField->getDisk());
        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path);
    }

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    private function fileField(string $resourceClass, string $name): ?FileField
    {
        $fields = (new FormFields)->visible(
            $resourceClass::show(new Form)->getAllFields(),
            ResourceContext::Show,
        );

        foreach ($fields as $field) {
            if ($field instanceof FileField && $field->name() === $name) {
                return $field;
            }
        }

        return null;
    }
}
