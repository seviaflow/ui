<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

require_once __DIR__.'/ResourceFormDefinitions.php';

uses(RefreshDatabase::class);

if (! function_exists('resourceFormBootstrap')) {
    function resourceFormBootstrap(): ResourceFormAuthUser
    {
        config()->set('seviaflow.resources', [
            'form-users' => FormUserResource::class,
            'flat-form-users' => FlatFormUserResource::class,
            'context-form-users' => ContextFormUserResource::class,
            'belongs-to-users' => BelongsToFormUserResource::class,
            'belongs-to-context-users' => BelongsToContextFormUserResource::class,
            'belongs-to-inline-users' => BelongsToInlineFormUserResource::class,
            'belongs-to-inline-users-denied' => BelongsToInlineDeniedFormUserResource::class,
            'belongs-to-inline-companies' => BelongsToInlineCompanyResource::class,
            'location-users' => LocationUserResource::class,
            'password-form-users' => PasswordFormUserResource::class,
            'password-context-form-users' => PasswordContextFormUserResource::class,
            'money-form-users' => MoneyFormUserResource::class,
            'money-context-form-users' => MoneyContextFormUserResource::class,
            'schedule-form-users' => ScheduleFormUserResource::class,
            'schedule-context-form-users' => ScheduleContextFormUserResource::class,
            'contact-form-users' => ContactFormUserResource::class,
            'slug-form-users' => SlugFormUserResource::class,
            'multiselect-form-users' => MultiSelectFormUserResource::class,
            'multiselect-context-form-users' => MultiSelectContextFormUserResource::class,
            'upload-form-users' => UploadFormUserResource::class,
            'upload-context-form-users' => UploadContextFormUserResource::class,
            'resource-event-form-users' => ResourceEventFormUserResource::class,
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

        if (! Schema::hasTable('form_users')) {
            Schema::create('form_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->integer('age')->nullable();
                $table->text('bio')->nullable();
                $table->boolean('is_active')->default(false);
                $table->string('status')->nullable();
                $table->date('published_at')->nullable();
                $table->string('password')->nullable();
                $table->string('slug')->nullable();
                $table->text('internal_notes')->nullable();
                $table->string('price')->nullable();
                $table->string('starts_at')->nullable();
                $table->string('opening_time')->nullable();
                $table->string('website')->nullable();
                $table->string('phone')->nullable();
                $table->text('channels')->nullable();
                $table->string('document_path')->nullable();
                $table->string('avatar_path')->nullable();
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

        return ResourceFormAuthUser::query()->firstOrCreate([
            'email' => 'resource-form@example.com',
        ], [
            'name' => 'Resource Form Operator',
            'password' => 'secret',
        ]);
    }
}

if (! function_exists('resourceFormReloadRoutes')) {
    function resourceFormReloadRoutes(): void
    {
        app()->register(SeviaFlowUiServiceProvider::class, force: true);
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }
}

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

if (! class_exists(ResourceFormAuthUser::class)) {
    final class ResourceFormAuthUser extends AuthenticatableUser
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}
