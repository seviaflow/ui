<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceIndex;
use SeviaFlow\Ui\Livewire\ResourceShow;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'action-users' => ActionUserResource::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('action_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
        $table->id();
        $table->string('resource');
        $table->string('name');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->string('visibility')->default('private');
        $table->json('state');
        $table->timestamps();
    });

    pest_test_case()->actingAs(ResourceActionsAuthUser::query()->create([
        'name' => 'Resource Actions Operator',
        'email' => 'resource-actions-operator@example.com',
        'password' => 'secret',
    ]), 'web');
});

it('renders row actions on resource index', function (): void {
    ActionUser::query()->create([
        'name' => 'Row Action User',
        'is_active' => false,
    ]);

    $user = ResourceActionsAuthUser::query()->create([
        'name' => 'Resource Actions User',
        'email' => 'resource-actions@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/action-users')
        ->assertOk()
        ->assertSee('Activate')
        ->assertSee('Deactivate account')
        ->assertSee('Fail action');
});

it('renders actions on resource show page', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Show Action User',
        'is_active' => false,
    ]);

    $viewer = ResourceActionsAuthUser::query()->create([
        'name' => 'Resource Actions Viewer',
        'email' => 'resource-actions-viewer@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($viewer, 'web');

    pest_test_case()->get('/admin/resources/action-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Activate')
        ->assertSee('Deactivate account');
});

it('executes row action handler and shows success message', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Execute Row Action',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'action-users'])
        ->call('executeAction', (string) $user->getKey(), 'activate', true)
        ->assertSee('Account activated successfully.');

    expect($user->fresh()?->is_active)->toBeTrue();
});

it('executes show action handler and shows success message', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Execute Show Action',
        'is_active' => false,
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'action-users', 'record' => $user->getKey()])
        ->call('executeAction', 'activate', true)
        ->assertSee('Account activated successfully.');

    expect($user->fresh()?->is_active)->toBeTrue();
});

it('fails safely for missing action', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Missing Action User',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'action-users'])
        ->call('executeAction', (string) $user->getKey(), 'unknown-action', true)
        ->assertSee('The selected action is not available.');
});

it('fails safely for missing record', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'action-users'])
        ->call('executeAction', '999999', 'activate', true)
        ->assertSee('The selected record could not be found.');
});

it('requires confirmation for danger action before execution', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Danger User',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'action-users'])
        ->call('executeAction', (string) $user->getKey(), 'deactivate')
        ->assertSee('Please confirm this action before continuing.')
        ->assertSee('Confirm dangerous action')
        ->assertSee('sf-confirm-consequences', false)
        ->assertSee('Cancel');

    expect($user->fresh()?->is_active)->toBeTrue();
});

it('shows safe error message when action handler fails', function (): void {
    $user = ActionUser::query()->create([
        'name' => 'Fail Action User',
        'is_active' => false,
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'action-users', 'record' => $user->getKey()])
        ->call('executeAction', 'fail-action', true)
        ->assertSee('Action failed. Please try again.')
        ->assertSee('sf-alert-danger', false)
        ->assertDontSee('RuntimeException');
});

final class ActionUserResource extends Resource
{
    protected static string $model = ActionUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name'),
        ]);
    }

    public static function actions(): array
    {
        return [
            Action::make('activate')
                ->label('Activate')
                ->successMessage('Account activated successfully.')
                ->handle(function (ActionUser $record): void {
                    $record->is_active = true;
                    $record->save();
                }),
            Action::make('deactivate')
                ->label('Deactivate account')
                ->danger()
                ->requiresConfirmation()
                ->confirmButton('Deactivate account')
                ->cancelButton('Cancel')
                ->successMessage('Account deactivated successfully.')
                ->handle(function (ActionUser $record): void {
                    $record->is_active = false;
                    $record->save();
                }),
            Action::make('fail-action')
                ->label('Fail action')
                ->errorMessage('Action failed. Please try again.')
                ->handle(function (): void {
                    throw new RuntimeException('internal failure');
                }),
        ];
    }
}

/**
 * @property bool $is_active
 */
final class ActionUser extends Model
{
    protected $table = 'action_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
    ];
}

final class ResourceActionsAuthUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
