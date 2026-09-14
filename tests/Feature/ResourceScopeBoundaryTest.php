<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceForm;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'scope-parents' => ScopeBoundaryParentResource::class,
        'scope-children' => ScopeBoundaryChildResource::class,
    ]);
    app()->instance(ResourceRegistry::class, new ResourceRegistry((array) config('seviaflow.resources', [])));

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('scope_boundary_parents', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('owner_id');
        $table->foreignId('child_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('scope_boundary_children', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('owner_id');
        $table->foreignId('parent_id');
        $table->string('name');
        $table->boolean('allowed')->default(true);
        $table->timestamps();
    });
});

it('does not resolve an out-of-scope record on show or edit', function (): void {
    $user = ScopeBoundaryAuthUser::query()->create(['name' => 'Operator', 'email' => 'operator@example.com']);
    $foreignUser = ScopeBoundaryAuthUser::query()->create(['name' => 'Foreign', 'email' => 'foreign@example.com']);
    $foreignParent = ScopeBoundaryParent::query()->create([
        'owner_id' => $foreignUser->getKey(),
        'name' => 'Foreign parent',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/scope-parents/'.$foreignParent->getKey())
        ->assertNotFound()
        ->assertDontSee('Foreign parent');
    pest_test_case()->get('/admin/resources/scope-parents/'.$foreignParent->getKey().'/edit')
        ->assertNotFound()
        ->assertDontSee('Foreign parent');
});

it('applies the related resource scope to relation records and belongs-to options', function (): void {
    $user = ScopeBoundaryAuthUser::query()->create(['name' => 'Operator', 'email' => 'operator@example.com']);
    $foreignUser = ScopeBoundaryAuthUser::query()->create(['name' => 'Foreign', 'email' => 'foreign@example.com']);
    $parent = ScopeBoundaryParent::query()->create([
        'owner_id' => $user->getKey(),
        'name' => 'Own parent',
    ]);
    $allowed = ScopeBoundaryChild::query()->create([
        'owner_id' => $user->getKey(),
        'parent_id' => $parent->getKey(),
        'name' => 'Visible child',
        'allowed' => true,
    ]);
    ScopeBoundaryChild::query()->create([
        'owner_id' => $user->getKey(),
        'parent_id' => $parent->getKey(),
        'name' => 'Hidden child',
        'allowed' => false,
    ]);
    ScopeBoundaryChild::query()->create([
        'owner_id' => $foreignUser->getKey(),
        'parent_id' => $parent->getKey(),
        'name' => 'Foreign child',
        'allowed' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/scope-parents/'.$parent->getKey())
        ->assertOk()
        ->assertSee('Visible child')
        ->assertDontSee('Hidden child')
        ->assertDontSee('Foreign child');

    Livewire::test(ResourceForm::class, ['resource' => 'scope-parents'])
        ->assertSet('fieldValues.child_id', null)
        ->set('fieldValues.name', 'New parent')
        ->set('fieldValues.child_id', $allowed->getKey())
        ->call('save')
        ->assertSee('Record saved successfully.');

    expect(ScopeBoundaryParent::query()->where('name', 'New parent')->value('child_id'))
        ->toBe($allowed->getKey());
});

it('rejects a foreign belongs-to assignment during server validation', function (): void {
    $user = ScopeBoundaryAuthUser::query()->create(['name' => 'Operator', 'email' => 'operator@example.com']);
    $foreignUser = ScopeBoundaryAuthUser::query()->create(['name' => 'Foreign', 'email' => 'foreign@example.com']);
    $foreignChild = ScopeBoundaryChild::query()->create([
        'owner_id' => $foreignUser->getKey(),
        'parent_id' => 1,
        'name' => 'Foreign child',
        'allowed' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceForm::class, ['resource' => 'scope-parents'])
        ->set('fieldValues.name', 'Invalid parent')
        ->set('fieldValues.child_id', $foreignChild->getKey())
        ->call('save')
        ->assertHasErrors(['fieldValues.child_id']);

    expect(ScopeBoundaryParent::query()->where('name', 'Invalid parent')->exists())->toBeFalse();
});

it('does not allow Livewire to replace authoritative resource metadata', function (): void {
    $user = ScopeBoundaryAuthUser::query()->create(['name' => 'Operator', 'email' => 'operator@example.com']);
    pest_test_case()->actingAs($user, 'web');

    $component = Livewire::test(ResourceForm::class, ['resource' => 'scope-parents']);

    expect(fn () => $component->set('resource', ScopeBoundaryChildResource::class))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

final class ScopeBoundaryAuthUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class ScopeBoundaryParent extends Model
{
    protected $table = 'scope_boundary_parents';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(static function (self $parent): void {
            if ($parent->getAttribute('owner_id') === null) {
                $parent->setAttribute('owner_id', auth()->id());
            }
        });
    }

    public function children(): HasMany
    {
        return $this->hasMany(ScopeBoundaryChild::class, 'parent_id');
    }

    public function selectedChild(): BelongsTo
    {
        return $this->belongsTo(ScopeBoundaryChild::class, 'child_id');
    }
}

final class ScopeBoundaryChild extends Model
{
    protected $table = 'scope_boundary_children';

    protected $guarded = [];
}

final class ScopeBoundaryParentResource extends Resource
{
    protected static string $model = ScopeBoundaryParent::class;

    public static function query(Builder $query): Builder
    {
        return $query->where('owner_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name'),
            BelongsToField::make('child_id')
                ->relationship('selectedChild', 'name')
                ->resource(ScopeBoundaryChildResource::class),
        ]);
    }

    public static function relations(): array
    {
        return [HasManyRelation::make('children')->resource(ScopeBoundaryChildResource::class)];
    }
}

final class ScopeBoundaryChildResource extends Resource
{
    protected static string $model = ScopeBoundaryChild::class;

    public static function query(Builder $query): Builder
    {
        return $query->where('owner_id', auth()->id())->where('allowed', true);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}
