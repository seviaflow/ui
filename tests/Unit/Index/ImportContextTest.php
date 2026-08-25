<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Index\Import\ImportContext;

it('resolves import contract capabilities and allowed fields', function (): void {
    $context = new ImportContext;

    expect($context->canImport(ImportContextEnabledResource::class, true))->toBeTrue()
        ->and($context->fields(ImportContextEnabledResource::class))->toBe(['name', 'email'])
        ->and($context->requiredFields(ImportContextEnabledResource::class))->toBe(['name'])
        ->and($context->canImport(ImportContextDisabledResource::class, true))->toBeFalse()
        ->and($context->canImport(ImportContextEnabledResource::class, false))->toBeFalse();
});

final class ImportContextEnabledResource extends Resource
{
    protected static string $model = ImportContextModel::class;

    public static function import(ImportResource $import): ImportResource
    {
        return $import->enabled()->allowedFields(['name', 'email'])->requiredFields(['name']);
    }
}

final class ImportContextDisabledResource extends Resource
{
    protected static string $model = ImportContextModel::class;
}

final class ImportContextModel extends Model
{
    protected $table = 'import_context_models';
}
