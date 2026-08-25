<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\ResourceRegistry;

require_once __DIR__.'/ResourceShowDefinitions.php';

uses(RefreshDatabase::class);

if (! function_exists('resourceShowBootstrap')) {
    function resourceShowBootstrap(): ResourceShowAuthUser
    {
        config()->set('seviaflow.resources', [
            'show-users' => ShowUserResource::class,
            'show-profiles' => ShowProfileResource::class,
            'show-orders' => ShowOrderResource::class,
            'show-roles' => ShowRoleResource::class,
            'show-users-with-orders' => ShowUserWithOrdersResource::class,
            'show-users-with-orders-manage' => ShowUserWithOrdersManageResource::class,
            'show-users-with-profile' => ShowUserWithProfileResource::class,
            'show-users-with-profile-manage' => ShowUserWithProfileManageResource::class,
            'show-comments' => ShowCommentResource::class,
            'show-avatars' => ShowAvatarResource::class,
            'show-users-with-morph-comments' => ShowUserWithMorphCommentsResource::class,
            'show-users-with-morph-comments-readonly' => ShowUserWithMorphCommentsReadOnlyResource::class,
            'show-users-with-morph-avatar' => ShowUserWithMorphAvatarResource::class,
            'show-users-with-morph-avatar-manage' => ShowUserWithMorphAvatarManageResource::class,
            'show-tags' => ShowTagResource::class,
            'show-users-with-morph-tags' => ShowUserWithMorphTagsResource::class,
            'show-users-with-morph-tags-attach-disabled' => ShowUserWithMorphTagsAttachDisabledResource::class,
            'show-users-with-soft-orders-manage' => ShowUserWithSoftOrdersManageResource::class,
            'show-users-invalid-orders' => ShowUserInvalidOrdersResource::class,
            'show-users-with-roles' => ShowUserWithRolesResource::class,
            'show-users-with-roles-no-pivot' => ShowUserWithRolesNoPivotResource::class,
            'show-users-with-roles-attach-disabled' => ShowUserWithRolesAttachDisabledResource::class,
            'show-users-with-roles-detach-disabled' => ShowUserWithRolesDetachDisabledResource::class,
            'show-orders-workflow' => ShowOrderWithWorkflowResource::class,
            'show-orders-workflow-failing' => ShowOrderWithWorkflowFailingResource::class,
            'show-orders-workflow-visibility' => ShowOrderWithWorkflowVisibilityResource::class,
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

        if (! Schema::hasTable('show_users')) {
            Schema::create('show_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(false);
                $table->date('published_at')->nullable();
                $table->json('metadata')->nullable();
                $table->string('show_note')->nullable();
                $table->string('internal_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_orders')) {
            Schema::create('show_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('show_user_id');
                $table->string('number');
                $table->string('status')->default('new');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_soft_orders')) {
            Schema::create('show_soft_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('show_user_id');
                $table->string('number');
                $table->string('status')->default('new');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_profiles')) {
            Schema::create('show_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('show_user_id');
                $table->string('bio')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_comments')) {
            Schema::create('show_comments', function (Blueprint $table): void {
                $table->id();
                $table->morphs('commentable');
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_avatars')) {
            Schema::create('show_avatars', function (Blueprint $table): void {
                $table->id();
                $table->morphs('imageable');
                $table->string('path');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_tags')) {
            Schema::create('show_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_taggables')) {
            Schema::create('show_taggables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tag_id');
                $table->morphs('taggable');
                $table->string('source')->nullable();
                $table->string('internal_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_roles')) {
            Schema::create('show_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('show_role_user')) {
            Schema::create('show_role_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('show_user_id');
                $table->foreignId('show_role_id');
                $table->integer('quantity')->nullable();
                $table->string('status')->nullable();
                $table->string('discount')->nullable();
                $table->string('internal_note')->nullable();
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

        if (! Schema::hasTable('seviaflow_workflow_activities')) {
            Schema::create('seviaflow_workflow_activities', function (Blueprint $table): void {
                $table->id();
                $table->string('resource');
                $table->string('record_type');
                $table->string('record_id');
                $table->string('transition');
                $table->string('from_state')->nullable();
                $table->string('to_state');
                $table->string('user_type')->nullable();
                $table->string('user_id')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        return ResourceShowAuthUser::query()->firstOrCreate([
            'email' => 'resource-show@example.com',
        ], [
            'name' => 'Resource Show Operator',
            'password' => 'secret',
        ]);
    }
}

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

if (! class_exists(ResourceShowAuthUser::class)) {
    final class ResourceShowAuthUser extends AuthenticatableUser
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}
