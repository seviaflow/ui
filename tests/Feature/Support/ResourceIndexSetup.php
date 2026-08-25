<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\ResourceRegistry;

require_once __DIR__.'/ResourceIndexDefinitions.php';

uses(RefreshDatabase::class);

if (! function_exists('resourceIndexBootstrap')) {
    function resourceIndexBootstrap(): ResourceIndexAuthUser
    {
        config()->set('seviaflow-ui.admin_middleware', ['web']);
        config()->set('seviaflow-ui.middleware', ['web']);

        config()->set('seviaflow.resources', [
            'test-users' => TestUserResource::class,
            'search-sort-users' => SearchSortUserResource::class,
            'filter-users' => FilterUserResource::class,
            'soft-delete-users' => SoftDeleteUserResource::class,
            'soft-delete-event-users' => SoftDeleteEventUserResource::class,
            'form-users' => FormEnabledUserResource::class,
            'visibility-users' => VisibilityUserResource::class,
            'belongs-to-column-users' => BelongsToColumnUserResource::class,
            'badge-column-users' => BadgeColumnUserResource::class,
            'status-column-users' => StatusColumnUserResource::class,
            'export-enabled-users' => ExportEnabledUserResource::class,
            'export-disabled-users' => ExportDisabledUserResource::class,
            'export-no-fields-users' => ExportNoFieldsUserResource::class,
            'import-enabled-users' => ImportEnabledUserResource::class,
            'import-event-users' => ImportEventUserResource::class,
            'import-disabled-users' => ImportDisabledUserResource::class,
            'import-limited-fields-users' => ImportLimitedFieldsUserResource::class,
            'import-typed-users' => ImportTypedUserResource::class,
            'import-form-rules-users' => ImportFormRulesUserResource::class,
        ]);

        app()->instance(ResourceRegistry::class, new ResourceRegistry((array) config('seviaflow.resources', [])));

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('test_users')) {
            Schema::create('test_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->foreignId('company_id')->nullable();
                $table->string('status')->default('draft');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('index_companies')) {
            Schema::create('index_companies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('soft_delete_users')) {
            Schema::create('soft_delete_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('status')->default('draft');
                $table->boolean('is_active')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seviaflow_saved_views')) {
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
        }

        SoftDeleteEventTracker::reset();
        ImportEventTracker::reset();

        return ResourceIndexAuthUser::query()->firstOrCreate([
            'email' => 'resource-index@example.com',
        ], [
            'name' => 'Resource Index Operator',
            'password' => 'secret',
        ]);
    }
}

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

if (! class_exists(ResourceIndexAuthUser::class)) {
    final class ResourceIndexAuthUser extends AuthenticatableUser
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}
