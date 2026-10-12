<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\BelongsToManyField;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TextareaField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Livewire\ResourceForm;

require_once __DIR__.'/../Support/ResourceFormSetup.php';

beforeEach(function (): void {
    resourceFormBootstrap();
    Schema::create('advanced_choice_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('country')->nullable();
        $table->string('city')->nullable();
        $table->json('contacts')->nullable();
        $table->timestamps();
    });
    Schema::create('advanced_choice_labels', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('allowed')->default(true);
    });
    Schema::create('advanced_choice_pivot', function (Blueprint $table): void {
        $table->foreignId('record_id');
        $table->foreignId('label_id');
    });
    config()->set('seviaflow.resources.advanced-choices', AdvancedChoiceResource::class);
});

it('renders grouped searchable choices and clears invalid dependent selections', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'advanced-choices'])
        ->assertSee('optgroup label="Europe"', false)
        ->assertSee('type="search"', false)
        ->set('fieldValues.country', 'PL')
        ->set('fieldValues.city', 'byd')
        ->set('fieldValues.country', 'NO')
        ->assertSet('fieldValues.city', '')
        ->assertSee('Oslo');
});

it('persists relation selections from the form and rejects forged IDs', function (): void {
    $allowed = AdvancedChoiceLabel::query()->create(['name' => 'Allowed']);
    $hidden = AdvancedChoiceLabel::query()->create(['name' => 'Hidden', 'allowed' => false]);
    $component = Livewire::test(ResourceForm::class, ['resource' => 'advanced-choices'])
        ->set('fieldValues.name', 'Product')
        ->call('toggleMultiSelectOption', 'labels', $allowed->getKey())
        ->call('toggleMultiSelectOption', 'labels', $hidden->getKey())
        ->assertSet('fieldValues.labels', [(string) $allowed->getKey()])
        ->call('save')->assertHasNoErrors();
    $record = AdvancedChoiceRecord::query()->firstOrFail();
    expect($record->labels()->pluck('advanced_choice_labels.id')->all())->toBe([$allowed->getKey()]);
    Livewire::test(ResourceForm::class, ['resource' => 'advanced-choices', 'record' => $record->getKey()])
        ->assertSet('fieldValues.labels', [(string) $allowed->getKey()])
        ->set('fieldValues.labels', [(string) $hidden->getKey()])
        ->call('save')->assertHasErrors('fieldValues.labels.0');
    expect($record->fresh()->labels()->count())->toBe(1);
});

it('renders and validates additional repeater field types with row-specific errors', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'advanced-choices'])
        ->call('addRepeaterItem', 'contacts')
        ->assertSee('type="date"', false)
        ->assertSee('type="email"', false)
        ->assertSee('textarea', false)
        ->set('fieldValues.name', 'Contact')
        ->set('fieldValues.contacts.0.email', 'invalid')
        ->call('save')->assertHasErrors('fieldValues.contacts.0.email')
        ->set('fieldValues.contacts.0.email', 'person@example.com')
        ->set('fieldValues.contacts.0.date', '2026-10-12')
        ->call('save')->assertHasNoErrors();
});

final class AdvancedChoiceRecord extends Model
{
    protected $table = 'advanced_choice_records';

    protected $guarded = [];

    protected $casts = ['contacts' => 'array'];

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(AdvancedChoiceLabel::class, 'advanced_choice_pivot', 'record_id', 'label_id');
    }
}

final class AdvancedChoiceLabel extends Model
{
    protected $table = 'advanced_choice_labels';

    protected $guarded = [];

    public $timestamps = false;
}

final class AdvancedChoiceResource extends Resource
{
    protected static string $model = AdvancedChoiceRecord::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->required(),
            SelectField::make('country')->options(['Europe' => ['PL' => 'Poland', 'NO' => 'Norway']])->searchable(),
            SelectField::make('city')->dependsOn('country')->options(static fn (FormState $state): array => $state->value('country') === 'PL' ? ['byd' => 'Bydgoszcz'] : ['osl' => 'Oslo']
            ),
            BelongsToManyField::make('labels')->relationship('labels', 'name')->query(static fn ($query) => $query->where('allowed', true)),
            RepeaterField::make('contacts')->fields([TextareaField::make('notes'), DateField::make('date'), EmailField::make('email')]),
        ]);
    }
}
