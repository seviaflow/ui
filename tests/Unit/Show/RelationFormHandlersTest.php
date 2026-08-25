<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Ui\Show\Relations\BelongsToManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasOneRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphManyRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphOneRelationManager;
use SeviaFlow\Ui\Show\Relations\PivotFormHandler;
use SeviaFlow\Ui\Show\Relations\RelationBoundaryGuard;
use SeviaFlow\Ui\Show\Relations\RelationModalFormHandler;

beforeEach(function (): void {
    Schema::create('relation_form_handler_parents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('relation_form_handler_children', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('relation_form_handler_parent_id');
        $table->string('name')->nullable();
        $table->integer('quantity')->nullable();
        $table->boolean('is_active')->default(false);
        $table->string('internal_note')->nullable();
        $table->timestamps();
    });
});

it('pivot handler persists only defined and persistable pivot fields', function (): void {
    $fields = [
        NumberField::make('quantity'),
        ToggleField::make('is_active'),
        TextField::make('name'),
        TextField::make('internal_note')->hiddenOnCreate(),
        TextField::make('readonly_note')->readonlyOnCreate(),
        TextField::make('disabled_note')->disabledOnCreate(),
    ];

    $values = [
        'quantity' => '4',
        'is_active' => '1',
        'name' => 'Assigned',
        'internal_note' => 'hidden',
        'readonly_note' => 'readonly',
        'disabled_note' => 'disabled',
    ];

    $safe = (new PivotFormHandler)->valuesForPersistence($fields, $values, ResourceContext::Create);

    expect($safe)->toBe([
        'quantity' => 4,
        'is_active' => true,
        'name' => 'Assigned',
    ]);
});

it('pivot handler initializes values from pivot model relation', function (): void {
    $record = new RelationFormHandlerChild;
    $record->setRelation('pivot', new RelationFormHandlerPivot([
        'quantity' => 8,
        'is_active' => true,
        'name' => 'From Pivot',
    ]));

    $values = (new PivotFormHandler)->initializeFromPivotRecord($record, [
        NumberField::make('quantity'),
        ToggleField::make('is_active'),
        TextField::make('name'),
    ]);

    expect($values)->toBe([
        'quantity' => '8',
        'is_active' => true,
        'name' => 'From Pivot',
    ]);
});

it('relation modal form handler persists only persistable fields', function (): void {
    $model = new RelationFormHandlerChild;
    $fields = [
        TextField::make('name'),
        NumberField::make('quantity'),
        ToggleField::make('is_active'),
        TextField::make('internal_note')->disabledOnEdit(),
    ];

    (new RelationModalFormHandler)->fillModelWithPersistableValues($model, $fields, [
        'name' => 'Updated Name',
        'quantity' => '9',
        'is_active' => '1',
        'internal_note' => 'blocked',
    ], ResourceContext::Edit);

    expect($model->getAttribute('name'))->toBe('Updated Name')
        ->and($model->getAttribute('quantity'))->toBe(9)
        ->and($model->getAttribute('is_active'))->toBeTrue()
        ->and($model->getAttribute('internal_note'))->toBeNull();
});

it('relation modal form handler creates related child through parent relation', function (): void {
    $parent = RelationFormHandlerParent::query()->create(['name' => 'Parent']);

    (new RelationModalFormHandler)->createRelatedRecord(
        $parent->children(),
        RelationFormHandlerChild::class,
        [
            TextField::make('name'),
            NumberField::make('quantity'),
        ],
        [
            'name' => 'Child A',
            'quantity' => '3',
        ]
    );

    $child = RelationFormHandlerChild::query()->where('name', 'Child A')->first();

    expect($child)->not->toBeNull()
        ->and($child?->getAttribute('relation_form_handler_parent_id'))->toBe($parent->getKey())
        ->and($child?->getAttribute('quantity'))->toBe(3);
});

it('relation boundary guard prevents editing unrelated hasMany record', function (): void {
    $first = RelationFormHandlerParent::query()->create(['name' => 'First']);
    $second = RelationFormHandlerParent::query()->create(['name' => 'Second']);
    $foreign = RelationFormHandlerChild::query()->create([
        'relation_form_handler_parent_id' => $second->getKey(),
        'name' => 'Foreign',
    ]);

    $relation = HasManyRelation::make('children')->resource(RelationFormHandlerChildResource::class);
    $guard = new RelationBoundaryGuard(
        new HasOneRelationManager,
        new HasManyRelationManager,
        new MorphOneRelationManager,
        new MorphManyRelationManager,
        new BelongsToManyRelationManager
    );

    expect($guard->resolveHasManyRecord($first, $relation, (string) $foreign->getKey()))->toBeNull();
});

final class RelationFormHandlerChildResource extends Resource
{
    protected static string $model = RelationFormHandlerChild::class;
}

final class RelationFormHandlerParent extends Model
{
    protected $table = 'relation_form_handler_parents';

    protected $guarded = [];

    public function children(): HasMany
    {
        return $this->hasMany(RelationFormHandlerChild::class, 'relation_form_handler_parent_id');
    }
}

final class RelationFormHandlerChild extends Model
{
    protected $table = 'relation_form_handler_children';

    protected $guarded = [];
}

final class RelationFormHandlerPivot extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
