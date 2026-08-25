<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Show\Relations\BelongsToManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasOneRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphManyRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphOneRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphToManyRelationManager;
use SeviaFlow\Ui\Show\Relations\RelationManagerResolver;
use SeviaFlow\Ui\Show\Relations\RelationQueryPaginator;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'relation-manager-users' => RelationManagerUserResource::class,
    ]);

    Schema::create('relation_manager_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('relation_manager_orders', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('relation_manager_user_id');
        $table->string('number');
        $table->timestamps();
    });

    Schema::create('relation_manager_profiles', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('relation_manager_user_id');
        $table->string('bio')->nullable();
        $table->timestamps();
    });

    Schema::create('relation_manager_comments', function (Blueprint $table): void {
        $table->id();
        $table->morphs('commentable');
        $table->text('body');
        $table->timestamps();
    });

    Schema::create('relation_manager_avatars', function (Blueprint $table): void {
        $table->id();
        $table->morphs('imageable');
        $table->string('path');
        $table->timestamps();
    });

    Schema::create('relation_manager_tags', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('relation_manager_taggables', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tag_id');
        $table->morphs('taggable');
        $table->string('source')->nullable();
        $table->timestamps();
    });

    Schema::create('relation_manager_roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('relation_manager_role_user', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('relation_manager_user_id');
        $table->foreignId('relation_manager_role_id');
        $table->timestamps();
    });
});

it('hasMany manager loads related records and enforces parent boundary', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);

    $own = RelationManagerOrder::query()->create(['relation_manager_user_id' => $first->getKey(), 'number' => 'OWN']);
    $foreign = RelationManagerOrder::query()->create(['relation_manager_user_id' => $second->getKey(), 'number' => 'FOREIGN']);

    $relation = HasManyRelation::make('orders')->resource(RelationManagerOrderResource::class)->perPage(2);
    $manager = new HasManyRelationManager;

    expect($manager->resolveRecord($first, $relation, (string) $own->getKey()))->not->toBeNull()
        ->and($manager->resolveRecord($first, $relation, (string) $foreign->getKey()))->toBeNull();
});

it('hasOne manager loads related record and enforces parent boundary', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);

    $own = RelationManagerProfile::query()->create(['relation_manager_user_id' => $first->getKey(), 'bio' => 'Own']);
    $foreign = RelationManagerProfile::query()->create(['relation_manager_user_id' => $second->getKey(), 'bio' => 'Foreign']);

    $relation = HasOneRelation::make('profile')->resource(RelationManagerProfileResource::class);
    $manager = new HasOneRelationManager;

    expect($manager->resolveRecord($first, $relation)?->getKey())->toBe($own->getKey())
        ->and($manager->resolveRecordById($first, $relation, (string) $foreign->getKey()))->toBeNull();
});

it('morphOne manager loads related record and enforces parent boundary', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);

    $own = RelationManagerAvatar::query()->create([
        'imageable_type' => RelationManagerUser::class,
        'imageable_id' => $first->getKey(),
        'path' => '/img/own.png',
    ]);
    $foreign = RelationManagerAvatar::query()->create([
        'imageable_type' => RelationManagerUser::class,
        'imageable_id' => $second->getKey(),
        'path' => '/img/foreign.png',
    ]);

    $relation = MorphOneRelation::make('avatar')->resource(RelationManagerAvatarResource::class);
    $manager = new MorphOneRelationManager;

    expect($manager->resolveRecord($first, $relation)?->getKey())->toBe($own->getKey())
        ->and($manager->resolveRecordById($first, $relation, (string) $foreign->getKey()))->toBeNull();
});

it('relation query paginator paginates hasMany records', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'Paged']);

    foreach (range(1, 4) as $i) {
        RelationManagerOrder::query()->create([
            'relation_manager_user_id' => $user->getKey(),
            'number' => 'ORD-'.$i,
        ]);
    }

    $relation = HasManyRelation::make('orders')->resource(RelationManagerOrderResource::class)->perPage(2);
    $paginator = (new RelationQueryPaginator)->paginateHasMany($user, $relation, RelationManagerOrderResource::class);

    expect($paginator->items())->toHaveCount(2)
        ->and($paginator->total())->toBe(4);
});

it('relation query paginator paginates morphMany records', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'Paged Morph']);

    foreach (range(1, 4) as $i) {
        RelationManagerComment::query()->create([
            'commentable_type' => RelationManagerUser::class,
            'commentable_id' => $user->getKey(),
            'body' => 'Comment '.$i,
        ]);
    }

    $relation = MorphManyRelation::make('comments')->resource(RelationManagerCommentResource::class)->perPage(2);
    $paginator = (new RelationQueryPaginator)->paginateMorphMany($user, $relation, RelationManagerCommentResource::class);

    expect($paginator->items())->toHaveCount(2)
        ->and($paginator->total())->toBe(4);
});

it('morphMany manager loads related records and enforces parent boundary', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);

    $own = RelationManagerComment::query()->create([
        'commentable_type' => RelationManagerUser::class,
        'commentable_id' => $first->getKey(),
        'body' => 'Own',
    ]);
    $foreign = RelationManagerComment::query()->create([
        'commentable_type' => RelationManagerUser::class,
        'commentable_id' => $second->getKey(),
        'body' => 'Foreign',
    ]);

    $relation = MorphManyRelation::make('comments')->resource(RelationManagerCommentResource::class)->perPage(2);
    $manager = new MorphManyRelationManager;

    expect($manager->resolveRecord($first, $relation, (string) $own->getKey()))->not->toBeNull()
        ->and($manager->resolveRecord($first, $relation, (string) $foreign->getKey()))->toBeNull();
});

it('relation query paginator paginates morphToMany records', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'Paged Morph Tags']);

    foreach (range(1, 4) as $i) {
        $tag = RelationManagerTag::query()->create(['name' => 'Tag '.$i]);
        $user->tags()->attach($tag->getKey(), ['source' => 'seed']);
    }

    $relation = MorphToManyRelation::make('tags')
        ->resource(RelationManagerTagResource::class)
        ->titleAttribute('name')
        ->perPage(2);

    $paginator = (new RelationQueryPaginator)->paginateMorphToMany($user, $relation, RelationManagerTagResource::class);

    expect($paginator->items())->toHaveCount(2)
        ->and($paginator->total())->toBe(4);
});

it('morphToMany manager attach and detach work with relation boundary', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);
    $tag = RelationManagerTag::query()->create(['name' => 'Scoped']);

    $relation = MorphToManyRelation::make('tags')
        ->resource(RelationManagerTagResource::class)
        ->titleAttribute('name');

    $manager = new MorphToManyRelationManager;
    $manager->attach($second, $relation, (string) $tag->getKey());

    expect($manager->resolveAttachedRecord($first, $relation, (string) $tag->getKey()))->toBeNull();

    $manager->attach($first, $relation, (string) $tag->getKey());

    expect($manager->isAttached($first, $relation, (string) $tag->getKey()))->toBeTrue();

    $manager->detach($first, $relation, (string) $tag->getKey());

    expect($manager->isAttached($first, $relation, (string) $tag->getKey()))->toBeFalse();
});

it('relation query paginator paginates belongsToMany records', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'Paged Roles']);

    foreach (range(1, 4) as $i) {
        $role = RelationManagerRole::query()->create(['name' => 'Role '.$i]);
        $user->roles()->attach($role->getKey());
    }

    $relation = BelongsToManyRelation::make('roles')
        ->resource(RelationManagerRoleResource::class)
        ->titleAttribute('name')
        ->perPage(2);

    $paginator = (new RelationQueryPaginator)->paginateBelongsToMany($user, $relation, RelationManagerRoleResource::class);

    expect($paginator->items())->toHaveCount(2)
        ->and($paginator->total())->toBe(4);
});

it('belongsToMany manager loads attached records and limited attach options', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'Roles']);

    $roles = collect(range(1, 4))->map(fn (int $i) => RelationManagerRole::query()->create(['name' => 'Role '.$i]));

    $user->roles()->attach($roles[0]->getKey());

    $relation = BelongsToManyRelation::make('roles')
        ->resource(RelationManagerRoleResource::class)
        ->titleAttribute('name')
        ->optionsLimit(2);

    $manager = new BelongsToManyRelationManager;

    $attached = $manager->resolveAttachedRecord($user, $relation, (string) $roles[0]->getKey());
    $options = $manager->attachOptions($user, $relation, RelationManagerRoleResource::class);

    expect($attached)->not->toBeNull()
        ->and($options)->toHaveCount(2)
        ->and(array_key_exists((string) $roles[0]->getKey(), $options))->toBeFalse();
});

it('belongsToMany manager attach and detach work', function (): void {
    $user = RelationManagerUser::query()->create(['name' => 'AttachDetach']);
    $role = RelationManagerRole::query()->create(['name' => 'Role']);

    $relation = BelongsToManyRelation::make('roles')
        ->resource(RelationManagerRoleResource::class)
        ->titleAttribute('name');

    $manager = new BelongsToManyRelationManager;

    $manager->attach($user, $relation, (string) $role->getKey());

    expect($manager->isAttached($user, $relation, (string) $role->getKey()))->toBeTrue();

    $manager->detach($user, $relation, (string) $role->getKey());

    expect($manager->isAttached($user, $relation, (string) $role->getKey()))->toBeFalse();
});

it('belongsToMany manager enforces relation boundary for attached records', function (): void {
    $first = RelationManagerUser::query()->create(['name' => 'First']);
    $second = RelationManagerUser::query()->create(['name' => 'Second']);
    $role = RelationManagerRole::query()->create(['name' => 'Scoped']);

    $second->roles()->attach($role->getKey());

    $relation = BelongsToManyRelation::make('roles')
        ->resource(RelationManagerRoleResource::class)
        ->titleAttribute('name');

    $manager = new BelongsToManyRelationManager;

    expect($manager->resolveAttachedRecord($first, $relation, (string) $role->getKey()))->toBeNull();
});

it('relation manager resolver finds configured relations safely', function (): void {
    $resolver = new RelationManagerResolver;

    expect($resolver->relations(RelationManagerUserResource::class))->toHaveCount(6)
        ->and($resolver->findHasOne(RelationManagerUserResource::class, 'profile'))->toBeInstanceOf(HasOneRelation::class)
        ->and($resolver->findHasMany(RelationManagerUserResource::class, 'orders'))->toBeInstanceOf(HasManyRelation::class)
        ->and($resolver->findBelongsToMany(RelationManagerUserResource::class, 'roles'))->toBeInstanceOf(BelongsToManyRelation::class)
        ->and($resolver->findMorphOne(RelationManagerUserResource::class, 'avatar'))->toBeInstanceOf(MorphOneRelation::class)
        ->and($resolver->findMorphMany(RelationManagerUserResource::class, 'comments'))->toBeInstanceOf(MorphManyRelation::class)
        ->and($resolver->findMorphToMany(RelationManagerUserResource::class, 'tags'))->toBeInstanceOf(MorphToManyRelation::class)
        ->and($resolver->findHasMany(RelationManagerUserResource::class, 'missing'))->toBeNull();
});

final class RelationManagerUserResource extends Resource
{
    protected static string $model = RelationManagerUser::class;

    public static function relations(): array
    {
        return [
            HasOneRelation::make('profile')
                ->label('Profile')
                ->resource(RelationManagerProfileResource::class),
            HasManyRelation::make('orders')
                ->label('Orders')
                ->resource(RelationManagerOrderResource::class)
                ->perPage(2),
            MorphManyRelation::make('comments')
                ->label('Comments')
                ->resource(RelationManagerCommentResource::class)
                ->perPage(2),
            MorphOneRelation::make('avatar')
                ->label('Avatar')
                ->resource(RelationManagerAvatarResource::class),
            MorphToManyRelation::make('tags')
                ->label('Tags')
                ->resource(RelationManagerTagResource::class)
                ->titleAttribute('name')
                ->optionsLimit(2)
                ->pivotColumns([
                    TextColumn::make('source')->label('Source'),
                ]),
            BelongsToManyRelation::make('roles')
                ->label('Roles')
                ->resource(RelationManagerRoleResource::class)
                ->titleAttribute('name')
                ->optionsLimit(2),
        ];
    }
}

final class RelationManagerOrderResource extends Resource
{
    protected static string $model = RelationManagerOrder::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Number'),
        ]);
    }
}

final class RelationManagerProfileResource extends Resource
{
    protected static string $model = RelationManagerProfile::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('bio')->label('Bio'),
        ]);
    }
}

final class RelationManagerRoleResource extends Resource
{
    protected static string $model = RelationManagerRole::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class RelationManagerCommentResource extends Resource
{
    protected static string $model = RelationManagerComment::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('body')->label('Body'),
        ]);
    }
}

final class RelationManagerAvatarResource extends Resource
{
    protected static string $model = RelationManagerAvatar::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('path')->label('Path'),
        ]);
    }
}

final class RelationManagerTagResource extends Resource
{
    protected static string $model = RelationManagerTag::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class RelationManagerUser extends Model
{
    protected $table = 'relation_manager_users';

    protected $guarded = [];

    public function orders(): HasMany
    {
        return $this->hasMany(RelationManagerOrder::class, 'relation_manager_user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(RelationManagerProfile::class, 'relation_manager_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RelationManagerRole::class, 'relation_manager_role_user', 'relation_manager_user_id', 'relation_manager_role_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(RelationManagerComment::class, 'commentable');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(RelationManagerAvatar::class, 'imageable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(RelationManagerTag::class, 'taggable', 'relation_manager_taggables', 'taggable_id', 'tag_id');
    }
}

final class RelationManagerOrder extends Model
{
    protected $table = 'relation_manager_orders';

    protected $guarded = [];
}

final class RelationManagerProfile extends Model
{
    protected $table = 'relation_manager_profiles';

    protected $guarded = [];
}

final class RelationManagerRole extends Model
{
    protected $table = 'relation_manager_roles';

    protected $guarded = [];
}

final class RelationManagerComment extends Model
{
    protected $table = 'relation_manager_comments';

    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

final class RelationManagerAvatar extends Model
{
    protected $table = 'relation_manager_avatars';

    protected $guarded = [];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}

final class RelationManagerTag extends Model
{
    protected $table = 'relation_manager_tags';

    protected $guarded = [];
}
