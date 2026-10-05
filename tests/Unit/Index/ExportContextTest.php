<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Index\Export\ExportContext;

it('resolves export contract capabilities and headings', function (): void {
    $context = new ExportContext;

    expect($context->canExport(ExportContextEnabledResource::class, true))->toBeTrue()
        ->and($context->canExport(ExportContextEnabledResource::class, false))->toBeFalse()
        ->and($context->canExport(ExportContextDisabledResource::class, true))->toBeFalse()
        ->and($context->formats(ExportContextEnabledResource::class))->toBe(['csv'])
        ->and($context->headings(ExportContextEnabledResource::class, ['email', 'id']))->toBe(['Email', 'ID']);
});

final class ExportContextEnabledResource extends Resource
{
    protected static string $model = ExportContextModel::class;

    public static function export(ExportResource $export): ExportResource
    {
        return $export
            ->enabled()
            ->allowedFields([
                ExportField::make('id')->label('ID'),
                ExportField::make('name')->label('Name'),
                ExportField::make('email')->label('Email'),
            ])
            ->formats(['csv']);
    }
}

final class ExportContextDisabledResource extends Resource
{
    protected static string $model = ExportContextModel::class;
}

final class ExportContextModel extends Model
{
    protected $table = 'export_context_models';
}
