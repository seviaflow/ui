<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Ui\Livewire\ResourceIndex;

require_once __DIR__.'/../Support/ResourceIndexDefinitions.php';

beforeEach(function (): void {
    config()->set('seviaflow-ui.admin_middleware', ['web']);
    config()->set('seviaflow-ui.middleware', ['web']);
    config()->set('seviaflow.resources', [
        'import-enabled-users' => ImportEnabledUserResource::class,
        'import-event-users' => ImportEventUserResource::class,
        'import-disabled-users' => ImportDisabledUserResource::class,
        'import-limited-fields-users' => ImportLimitedFieldsUserResource::class,
        'import-typed-users' => ImportTypedUserResource::class,
        'import-form-rules-users' => ImportFormRulesUserResource::class,
    ]);
    app()->instance(ResourceRegistry::class, new ResourceRegistry((array) config('seviaflow.resources', [])));

    Schema::create('test_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->foreignId('company_id')->nullable();
        $table->string('status')->default('draft');
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

    if (! is_dir(storage_path('framework/livewire-tmp'))) {
        mkdir(storage_path('framework/livewire-tmp'), 0755, true);
    }

    ImportEventTracker::reset();
});

it('shows import button only when import is enabled', function (): void {
    ensureImportAuthUsersTable();
    $user = ImportAuthUser::query()->create(['name' => 'Import Listing User']);
    pest_test_case()->actingAs($user);
    expect((array) config('seviaflow.resources', []))->toHaveKey('import-enabled-users')
        ->and(app(ResourceRegistry::class)->resolve('import-enabled-users'))->toBe(ImportEnabledUserResource::class);

    pest_test_case()->get(route('seviaflow.resources.index', ['resource' => 'import-enabled-users']))
        ->assertOk()
        ->assertSee('wire:click="openImportModal"', false);

    pest_test_case()->get(route('seviaflow.resources.index', ['resource' => 'import-disabled-users']))
        ->assertOk()
        ->assertDontSee('wire:click="openImportModal"', false);
});

it('downloads an import template with allowed headings', function (): void {
    Carbon::setTestNow('2026-09-15 14:30:00');

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('downloadImportTemplate')
        ->assertFileDownloaded('import-enabled-users-import-template-20260915-143000.csv');

    Carbon::setTestNow();
});

it('parses csv headers and preview rows without writing records', function (): void {
    $csv = "name,email,status\nAlice,alice@example.com,published\nBob,bob@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    expect(TestUser::query()->count())->toBe(0);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSet('importPreviewHeaders', ['name', 'email', 'status'])
        ->assertSet('importPreviewErrors', [])
        ->assertSee('Detected headers')
        ->assertSee('Alice')
        ->assertSee('Bob');

    expect(TestUser::query()->count())->toBe(0);
});

it('uses configured import preview row limit', function (): void {
    config()->set('seviaflow.import.preview_rows', 2);

    $csv = "name,email,status\nAlice,alice@example.com,published\nBob,bob@example.com,draft\nCarol,carol@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->assertDontSee('Carol');
});

it('fails validation for invalid file type', function (): void {
    $file = UploadedFile::fake()->create('users.pdf', 10, 'application/pdf');

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertHasErrors('importFile');
});

it('reports unknown headers and missing required fields', function (): void {
    $csv = "name,phone\nAlice,123\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSee('Unknown fields are not allowed: phone.')
        ->assertSee('Missing required fields: email.');
});

it('reports row-level required field errors in preview', function (): void {
    $csv = "name,email,status\nAlice,,published\n,Bob@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSee('Field [email] is required.')
        ->assertSee('Field [name] is required.');
});

it('does not allow preview when import is disabled', function (): void {
    $csv = "name,email,status\nAlice,alice@example.com,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-disabled-users'])
        ->call('openImportModal')
        ->assertSet('showImportModal', false)
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSee('Import is not available for this resource.');
});

it('imports valid csv rows successfully', function (): void {
    $csv = "name,email,status\nAlice,alice@example.com,published\nBob,bob@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import completed. Imported: 2. Failed: 0.');

    expect(TestUser::query()->count())->toBe(2)
        ->and(TestUser::query()->where('email', 'alice@example.com')->exists())->toBeTrue();
});

it('rejects import with unknown fields and writes nothing', function (): void {
    $csv = "name,email,phone\nAlice,alice@example.com,123\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import failed. Imported: 0. Failed: 0.')
        ->assertSee('Unknown fields are not allowed: phone.');

    expect(TestUser::query()->count())->toBe(0);
});

it('rejects import with missing required fields and writes nothing', function (): void {
    $csv = "name,status\nAlice,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Missing required fields: email.');

    expect(TestUser::query()->count())->toBe(0);
});

it('rolls back import when any row is invalid', function (): void {
    $csv = "name,email,status\nAlice,alice@example.com,published\nBob,,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import failed. Imported: 0. Failed: 1.')
        ->assertSee('Row 2: The email field is required.');

    expect(TestUser::query()->count())->toBe(0);
});

it('persists only allowed import fields', function (): void {
    $csv = "name,email\nLimited,limited@example.com\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-limited-fields-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import completed. Imported: 1. Failed: 0.');

    $record = TestUser::query()->firstOrFail();
    expect($record->name)->toBe('Limited')
        ->and($record->email)->toBe('limited@example.com')
        ->and($record->status)->toBe('draft');
});

it('rejects typed field values that fail basic cast-aware validation', function (): void {
    $csv = "name,email,is_active\nTyped,typed@example.com,not-bool\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-typed-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import failed. Imported: 0. Failed: 1.');

    expect(TestUser::query()->count())->toBe(0);
});

it('does not use form field rules for import validation by default', function (): void {
    $csv = "name,email,status\nTyped,not-an-email,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import completed. Imported: 1. Failed: 0.');

    expect(TestUser::query()->count())->toBe(1);
});

it('can opt in to use form field rules for import validation', function (): void {
    $csv = "name,email,status\nTyped,not-an-email,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-form-rules-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import failed. Imported: 0. Failed: 1.');

    expect(TestUser::query()->count())->toBe(0);
});

it('validates only import allowed fields when form-field-rules opt-in is enabled', function (): void {
    $csv = "name,email,status\nTyped,typed@example.com,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-form-rules-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import completed. Imported: 1. Failed: 0.');

    expect(TestUser::query()->count())->toBe(1);
});

it('rejects import when max rows is exceeded', function (): void {
    config()->set('seviaflow.import.max_rows', 1);

    $csv = "name,email,status\nAlice,alice@example.com,published\nBob,bob@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import exceeds the maximum allowed rows (1).');

    expect(TestUser::query()->count())->toBe(0);
});

it('hides import and blocks execution when create authorization is denied', function (): void {
    ensureImportAuthUsersTable();

    Gate::policy(TestUser::class, ImportDenyCreatePolicy::class);
    $user = ImportAuthUser::query()->create(['name' => 'Denied']);
    pest_test_case()->actingAs($user);

    $csv = "name,email,status\nAlice,alice@example.com,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-enabled-users'])
        ->assertDontSee('Import')
        ->call('openImportModal')
        ->assertSet('showImportModal', false)
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import is not available for this resource.');
});

it('import fires creating and created with source import', function (): void {
    Gate::policy(TestUser::class, ImportAllowCreatePolicy::class);
    ensureImportAuthUsersTable();
    $user = ImportAuthUser::query()->create(['name' => 'Event Policy Allowed']);
    pest_test_case()->actingAs($user);

    $csv = "name,email,status\nEvent One,event1@example.com,published\nEvent Two,event2@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-event-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport');

    expect(ImportEventTracker::$events)->toHaveCount(4)
        ->and(ImportEventTracker::$events[0]['event'])->toBe('creating')
        ->and(ImportEventTracker::$events[1]['event'])->toBe('creating')
        ->and(ImportEventTracker::$events[0]['source'])->toBe('import')
        ->and(ImportEventTracker::$events[0]['row'])->toBe(1)
        ->and(ImportEventTracker::$events[1]['row'])->toBe(2)
        ->and(ImportEventTracker::$events[2]['event'])->toBe('created')
        ->and(ImportEventTracker::$events[3]['event'])->toBe('created');
});

it('imports through event callbacks without closure scope runtime errors', function (): void {
    Gate::policy(TestUser::class, ImportAllowCreatePolicy::class);
    ensureImportAuthUsersTable();
    $user = ImportAuthUser::query()->create(['name' => 'Scope Policy Allowed']);
    pest_test_case()->actingAs($user);

    $csv = "name,email,status\nScope One,scope1@example.com,published\nScope Two,scope2@example.com,draft\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

    Livewire::test(ResourceIndex::class, ['resource' => 'import-event-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport');

    expect(ImportEventTracker::$events)->toHaveCount(4)
        ->and(TestUser::query()->where('email', 'scope1@example.com')->exists())->toBeTrue()
        ->and(TestUser::query()->where('email', 'scope2@example.com')->exists())->toBeTrue();
});

it('import event exception rolls back import', function (): void {
    Gate::policy(TestUser::class, ImportAllowCreatePolicy::class);
    ensureImportAuthUsersTable();
    $user = ImportAuthUser::query()->create(['name' => 'Exception Policy Allowed']);
    pest_test_case()->actingAs($user);

    $csv = "name,email,status\nBlocked,blocked-import@example.com,published\n";
    $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
    ImportEventTracker::$throwOnCreating = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'import-event-users'])
        ->call('openImportModal')
        ->set('importFile', $file)
        ->call('executeImport')
        ->assertSee('Import blocked by event.');

    expect(TestUser::query()->where('email', 'blocked-import@example.com')->exists())->toBeFalse();
});

final class ImportAuthUser extends AuthenticatableUser
{
    protected $table = 'import_auth_users';

    protected $guarded = [];
}

final class ImportDenyCreatePolicy
{
    public function viewAny(ImportAuthUser $user): bool
    {
        return true;
    }

    public function create(ImportAuthUser $user): bool
    {
        return false;
    }
}

final class ImportAllowCreatePolicy
{
    public function viewAny(ImportAuthUser $user): bool
    {
        return true;
    }

    public function create(ImportAuthUser $user): bool
    {
        return true;
    }
}

function ensureImportAuthUsersTable(): void
{
    if (Schema::hasTable('import_auth_users')) {
        return;
    }

    Schema::create('import_auth_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
}
