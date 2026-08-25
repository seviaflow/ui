<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Ui\Index\BulkActionRunner;
use SeviaFlow\Ui\Index\BulkSelectionState;
use SeviaFlow\Ui\Index\RowActionRunner;
use SeviaFlow\Ui\Index\TableValueFormatter;

it('bulk selection normalizes values to current visible records', function (): void {
    $state = new BulkSelectionState;

    $normalized = $state->normalize(['1', '2', '2', '999', '', ['x']], ['1', '2', '3']);

    expect($normalized)->toBe(['1', '2']);
});

it('row action runner executes action and returns success state', function (): void {
    $record = new IndexCollaboratorModel(['name' => 'Before']);

    $result = (new RowActionRunner)->run(
        actions: [
            Action::make('activate')
                ->successMessage('Action done')
                ->handle(function (IndexCollaboratorModel $model): void {
                    $model->setAttribute('name', 'After');
                }),
        ],
        record: $record,
        recordId: '1',
        actionName: 'activate',
        confirmed: true,
        canExecute: true
    );

    expect($result['statusMessage'])->toBe('Action done')
        ->and($result['errorMessage'])->toBeNull()
        ->and($record->getAttribute('name'))->toBe('After');
});

it('bulk action runner executes custom bulk action', function (): void {
    $record = new IndexCollaboratorModel(['name' => 'Before']);
    /** @var Collection<int, Model> $records */
    $records = new Collection([$record]);
    $internalError = null;

    $result = (new BulkActionRunner)->run(
        actions: [
            BulkAction::make('bulk-activate')
                ->successMessage('Bulk done')
                ->handle(function (Collection $selected): void {
                    $selected->each(function (IndexCollaboratorModel $model): void {
                        $model->setAttribute('name', 'After');
                    });
                }),
        ],
        actionName: 'bulk-activate',
        records: $records,
        selectedRecords: ['1'],
        confirmed: true,
        maxByConfig: null,
        canExecuteBulkAction: fn (): bool => true,
        isBuiltInBulkDelete: fn (): bool => false,
        isBuiltInBulkRestore: fn (): bool => false,
        runBuiltInBulkDelete: fn (): null => null,
        runBuiltInBulkRestore: fn (): null => null,
        internalError: $internalError
    );

    expect($result['statusMessage'])->toBe('Bulk done')
        ->and($result['errorMessage'])->toBeNull()
        ->and($record->getAttribute('name'))->toBe('After');
});

it('table value formatter handles relation badge boolean and null values', function (): void {
    $company = new IndexCompanyModel(['name' => 'Acme']);
    $record = new IndexCollaboratorModel([
        'name' => 'Alice',
        'is_active' => 1,
        'status' => 'draft',
    ]);
    $record->setRelation('company', $company);

    $formatter = new TableValueFormatter;

    $relation = BelongsToColumn::make('company_name')->relationship('company', 'name');
    $boolean = BooleanColumn::make('is_active');
    $text = TextColumn::make('name');
    $badge = BadgeColumn::make('status')->labels([
        'draft' => 'Draft',
    ])->colors([
        'draft' => 'gray',
    ]);

    expect($formatter->format($record, $relation))->toBe('Acme')
        ->and($formatter->format($record, $boolean))->toBe('Yes')
        ->and($formatter->format($record, $text))->toBe('Alice')
        ->and($formatter->format($record, $badge))->toContain('Draft');

    $record->setAttribute('name', null);
    expect($formatter->format($record, $text))->toBe('—');
});

final class IndexCollaboratorModel extends Model
{
    protected $guarded = [];
}

final class IndexCompanyModel extends Model
{
    protected $guarded = [];
}
