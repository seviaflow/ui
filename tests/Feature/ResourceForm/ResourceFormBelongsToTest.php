<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'belongs-to-users' => BelongsToFormUserResource::class,
        'belongs-to-context-users' => BelongsToContextFormUserResource::class,
        'belongs-to-inline-users' => BelongsToInlineFormUserResource::class,
        'belongs-to-inline-users-denied' => BelongsToInlineDeniedFormUserResource::class,
        'belongs-to-inline-companies' => BelongsToInlineCompanyResource::class,
        'location-users' => LocationUserResource::class,
    ]);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('form_companies')) {
        Schema::create('form_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('belongs_to_form_users')) {
        Schema::create('belongs_to_form_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('location_countries')) {
        Schema::create('location_countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('location_cities')) {
        Schema::create('location_cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    pest_test_case()->actingAs(BelongsToFormAuthUser::query()->create([
        'name' => 'BelongsTo Operator',
        'email' => 'belongs-to@example.com',
        'password' => 'secret',
    ]), 'web');
});

it('belongsToField renders on create form', function (): void {
    BelongsToFormCompany::query()->create(['name' => 'Acme Inc']);

    pest_test_case()->get('/admin/resources/belongs-to-users/create')
        ->assertOk()
        ->assertSee('Company')
        ->assertSee('Select company')
        ->assertSee('Acme Inc');
});

it('belongsToField renders on edit form with current value selected', function (): void {
    $companyA = BelongsToFormCompany::query()->create(['name' => 'Alpha']);
    $companyB = BelongsToFormCompany::query()->create(['name' => 'Beta']);

    $user = BelongsToFormUser::query()->create([
        'name' => 'Existing BelongsTo User',
        'company_id' => $companyB->getKey(),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users', 'record' => $user->getKey()])
        ->assertSet('fieldValues.company_id', (string) $companyB->getKey());
});

it('belongsToField options are limited', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        BelongsToFormCompany::query()->create(['name' => 'Company '.$i]);
    }

    pest_test_case()->get('/admin/resources/belongs-to-users/create')
        ->assertOk()
        ->assertSee('Company 1')
        ->assertSee('Company 2')
        ->assertDontSee('Company 3')
        ->assertDontSee('Company 4')
        ->assertDontSee('Company 5');
});

it('belongsToField query callback filters options', function (): void {
    BelongsToFormCompany::query()->create(['name' => 'Active Company', 'active' => true, 'tenant_id' => 1]);
    BelongsToFormCompany::query()->create(['name' => 'Inactive Company', 'active' => false, 'tenant_id' => 1]);

    pest_test_case()->get('/admin/resources/belongs-to-users/create')
        ->assertOk()
        ->assertSee('Active Company')
        ->assertDontSee('Inactive Company');
});

it('belongsToField search matches configured columns only', function (): void {
    BelongsToFormCompany::query()->create([
        'name' => 'Name Match',
        'email' => 'name-match@example.com',
        'active' => true,
        'tenant_id' => 1,
    ]);
    BelongsToFormCompany::query()->create([
        'name' => 'Email Match',
        'email' => 'email-match@example.com',
        'active' => true,
        'tenant_id' => 1,
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users'])
        ->set('belongsToSearch.company_id', 'email-match@example.com')
        ->assertSee('Email Match')
        ->assertDontSee('Name Match')
        ->set('belongsToSearch.company_id', '1')
        ->assertDontSee('Email Match');
});

it('inline create trigger is hidden by default', function (): void {
    BelongsToFormCompany::query()->create(['name' => 'Default Company', 'active' => true, 'tenant_id' => 1]);

    pest_test_case()->get('/admin/resources/belongs-to-users/create')
        ->assertOk()
        ->assertDontSee('Create Company');
});

it('inline create trigger appears when enabled', function (): void {
    BelongsToFormCompany::query()->create(['name' => 'Inline Company', 'active' => true, 'tenant_id' => 1]);

    pest_test_case()->get('/admin/resources/belongs-to-inline-users/create')
        ->assertOk()
        ->assertSee('Create Company');
});

it('unauthorized user cannot use inline create', function (): void {
    Gate::policy(BelongsToFormCompany::class, DenyBelongsToInlineCompanyPolicy::class);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-inline-users-denied'])
        ->call('beginInlineCreate', 'company_id')
        ->assertForbidden();
});

it('inline create renders related form and validates errors', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-inline-users'])
        ->call('beginInlineCreate', 'company_id')
        ->assertSee('Create Company')
        ->set('inlineCreateValues.name', '')
        ->call('saveInlineCreate')
        ->assertHasErrors(['inlineCreateValues.name' => 'required']);
});

it('inline create creates related record and selects it without saving parent', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-inline-users'])
        ->set('fieldValues.name', 'Parent Not Saved')
        ->call('beginInlineCreate', 'company_id')
        ->set('inlineCreateValues.name', 'Created Inline Co')
        ->set('inlineCreateValues.email', 'inline@example.com')
        ->call('saveInlineCreate')
        ->assertHasNoErrors()
        ->assertSet('fieldValues.company_id', '1');

    $created = BelongsToFormCompany::query()->where('name', 'Created Inline Co')->first();

    expect($created)->not->toBeNull()
        ->and(BelongsToInlineFormUser::query()->where('name', 'Parent Not Saved')->exists())->toBeFalse();
});

it('dependent belongsTo field filters options by parent field', function (): void {
    $countryA = LocationCountry::query()->create(['name' => 'Poland']);
    $countryB = LocationCountry::query()->create(['name' => 'Germany']);
    LocationCity::query()->create(['country_id' => $countryA->getKey(), 'name' => 'Warsaw']);
    LocationCity::query()->create(['country_id' => $countryB->getKey(), 'name' => 'Berlin']);

    Livewire::test(ResourceForm::class, ['resource' => 'location-users'])
        ->set('fieldValues.country_id', (string) $countryA->getKey())
        ->assertSee('Warsaw')
        ->assertDontSee('Berlin');
});

it('dependent belongsTo field clears stale child value when parent changes', function (): void {
    $countryA = LocationCountry::query()->create(['name' => 'Poland']);
    $countryB = LocationCountry::query()->create(['name' => 'Germany']);
    $warsaw = LocationCity::query()->create(['country_id' => $countryA->getKey(), 'name' => 'Warsaw']);
    LocationCity::query()->create(['country_id' => $countryB->getKey(), 'name' => 'Berlin']);

    Livewire::test(ResourceForm::class, ['resource' => 'location-users'])
        ->set('fieldValues.country_id', (string) $countryA->getKey())
        ->set('fieldValues.city_id', (string) $warsaw->getKey())
        ->set('fieldValues.country_id', (string) $countryB->getKey())
        ->assertSet('fieldValues.city_id', '');
});

it('dependent belongsTo field with empty parent does not load unsafe full options', function (): void {
    $country = LocationCountry::query()->create(['name' => 'Poland']);
    LocationCity::query()->create(['country_id' => $country->getKey(), 'name' => 'Warsaw']);

    pest_test_case()->get('/admin/resources/location-users/create')
        ->assertOk()
        ->assertSee('Select country first')
        ->assertDontSee('Warsaw');
});

it('dependent belongsTo field still enforces options limit', function (): void {
    $country = LocationCountry::query()->create(['name' => 'Poland']);

    foreach (range(1, 4) as $index) {
        LocationCity::query()->create(['country_id' => $country->getKey(), 'name' => 'City '.$index]);
    }

    Livewire::test(ResourceForm::class, ['resource' => 'location-users'])
        ->set('fieldValues.country_id', (string) $country->getKey())
        ->assertSee('City 1')
        ->assertSee('City 2')
        ->assertDontSee('City 3')
        ->assertDontSee('City 4');
});

it('dependent belongsTo field rejects invalid tampered value', function (): void {
    $countryA = LocationCountry::query()->create(['name' => 'Poland']);
    $countryB = LocationCountry::query()->create(['name' => 'Germany']);
    $berlin = LocationCity::query()->create(['country_id' => $countryB->getKey(), 'name' => 'Berlin']);

    Livewire::test(ResourceForm::class, ['resource' => 'location-users'])
        ->set('fieldValues.name', 'Dependent User')
        ->set('fieldValues.country_id', (string) $countryA->getKey())
        ->set('fieldValues.city_id', (string) $berlin->getKey())
        ->call('save')
        ->assertHasErrors(['fieldValues.city_id']);
});

it('selected foreign key is saved on create', function (): void {
    $company = BelongsToFormCompany::query()->create(['name' => 'Create Co']);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users'])
        ->set('fieldValues.name', 'BelongsTo Create')
        ->set('fieldValues.company_id', (string) $company->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $created = BelongsToFormUser::query()->where('name', 'BelongsTo Create')->first();

    expect($created)->not->toBeNull()
        ->and($created?->company_id)->toBe($company->getKey());
});

it('selected foreign key is updated on edit', function (): void {
    $companyA = BelongsToFormCompany::query()->create(['name' => 'First Co']);
    $companyB = BelongsToFormCompany::query()->create(['name' => 'Second Co']);

    $user = BelongsToFormUser::query()->create([
        'name' => 'BelongsTo Edit',
        'company_id' => $companyA->getKey(),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users', 'record' => $user->getKey()])
        ->set('fieldValues.company_id', (string) $companyB->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->company_id)->toBe($companyB->getKey());
});

it('required belongsToField validates required', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users'])
        ->set('fieldValues.name', 'Required BelongsTo')
        ->set('fieldValues.company_id', '')
        ->call('save')
        ->assertHasErrors(['fieldValues.company_id' => 'required']);
});

it('invalid related id fails validation', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users'])
        ->set('fieldValues.name', 'Invalid Relation')
        ->set('fieldValues.company_id', '999999')
        ->call('save')
        ->assertHasErrors(['fieldValues.company_id' => 'exists']);
});

it('hidden readonly and disabled belongsToField are not persisted when tampered', function (): void {
    $companyA = BelongsToFormCompany::query()->create(['name' => 'Original Co']);
    $companyB = BelongsToFormCompany::query()->create(['name' => 'Tampered Co']);

    $user = BelongsToContextFormUser::query()->create([
        'name' => 'BelongsTo Context',
        'company_id' => $companyA->getKey(),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-context-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_company_id', (string) $companyB->getKey())
        ->set('fieldValues.readonly_company_id', (string) $companyB->getKey())
        ->set('fieldValues.disabled_company_id', (string) $companyB->getKey())
        ->set('fieldValues.name', 'BelongsTo Context Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->company_id)->toBe($companyA->getKey())
        ->and($user->name)->toBe('BelongsTo Context Updated');
});

final class BelongsToFormAuthUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
