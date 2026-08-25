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
use Illuminate\Database\Eloquent\SoftDeletes;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Section;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;

final class ShowUserResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->sections([
            Section::make('Account')
                ->fields([
                    TextField::make('name')->label('Name'),
                    EmailField::make('email')->label('Email address'),
                    ToggleField::make('is_active')->label('Active'),
                    DateField::make('published_at')->label('Published at'),
                    TextField::make('metadata')->label('Metadata'),
                    TextField::make('show_note')->label('Show note')->visibleOnShow()->hiddenOnEdit()->hiddenOnCreate(),
                    TextField::make('internal_notes')->label('Internal notes')->hiddenOnShow(),
                ]),
        ]);
    }
}

final class ShowUserWithOrdersResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasManyRelation::make('orders')
                ->label('Orders')
                ->resource(ShowOrderResource::class)
                ->perPage(2),
        ];
    }
}

final class ShowUserWithOrdersManageResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasManyRelation::make('orders')
                ->label('Orders')
                ->resource(ShowOrderResource::class)
                ->perPage(2)
                ->allowCreate()
                ->allowEdit()
                ->createLabel('Add order')
                ->editLabel('Edit order'),
        ];
    }
}

final class ShowUserWithSoftOrdersManageResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasManyRelation::make('softOrders')
                ->label('Soft orders')
                ->resource(ShowSoftOrderResource::class)
                ->perPage(2)
                ->allowDelete()
                ->allowRestore(),
        ];
    }
}

final class ShowUserInvalidOrdersResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasManyRelation::make('name')
                ->label('Invalid relation')
                ->resource(ShowOrderResource::class)
                ->perPage(2),
        ];
    }
}

final class ShowOrderResource extends Resource
{
    protected static string $model = ShowOrder::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Order number'),
            TextColumn::make('status')->label('Status'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('number')->label('Order number'),
            TextField::make('status')->label('Status'),
        ]);
    }
}

final class ShowOrderWithWorkflowResource extends Resource
{
    protected static string $model = ShowOrder::class;

    public static function table(Table $table): Table
    {
        return ShowOrderResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('number')->label('Order number'),
            TextField::make('status')->label('Status'),
        ]);
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft')->label('Draft')->color('gray'),
                State::make('pending')->label('Pending')->color('yellow'),
                State::make('cancelled')->label('Cancelled')->color('red')->final(),
                State::make('shipped')->label('Shipped')->color('green'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->label('Submit order'),
                Transition::make('cancel')
                    ->from(['draft', 'pending'])
                    ->to('cancelled')
                    ->label('Cancel order')
                    ->danger()
                    ->requiresConfirmation()
                    ->requiresComment(),
                Transition::make('ship')
                    ->from('pending')
                    ->to('shipped')
                    ->label('Ship order'),
            ]);
    }
}

final class ShowOrderWithWorkflowFailingResource extends Resource
{
    protected static string $model = ShowOrder::class;

    public static function table(Table $table): Table
    {
        return ShowOrderResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowOrderWithWorkflowResource::form($form);
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return ShowOrderWithWorkflowResource::workflow($workflow)
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->label('Submit order')
                    ->handle(static function (): void {
                        throw new RuntimeException('fail');
                    }),
            ]);
    }
}

final class ShowOrderWithWorkflowVisibilityResource extends Resource
{
    protected static string $model = ShowOrder::class;

    public static function table(Table $table): Table
    {
        return ShowOrderResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowOrderWithWorkflowResource::form($form);
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft')->label('Draft')->color('gray'),
                State::make('pending')->label('Pending')->color('yellow'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->label('Submit order')
                    ->visibleIf(static fn (ShowOrder $order): bool => str_starts_with((string) $order->number, 'SHOW-')),
            ]);
    }
}

final class ShowRoleResource extends Resource
{
    protected static string $model = ShowRole::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Role name'),
        ]);
    }
}

final class ShowSoftOrderResource extends Resource
{
    protected static string $model = ShowSoftOrder::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Order number'),
            TextColumn::make('status')->label('Status'),
        ]);
    }
}

final class ShowUserWithRolesResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            BelongsToManyRelation::make('roles')
                ->label('Roles')
                ->resource(ShowRoleResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2)
                ->pivotColumns([
                    TextColumn::make('quantity')->label('Quantity'),
                    TextColumn::make('discount')->label('Discount'),
                ])
                ->pivotFields([
                    NumberField::make('quantity')->label('Quantity')->required(),
                    SelectField::make('status')->label('Status')->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                    ]),
                    TextField::make('internal_note')->label('Internal note')->hiddenOn([ResourceContext::Create, ResourceContext::Edit]),
                ]),
        ];
    }
}

final class ShowUserWithRolesNoPivotResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            BelongsToManyRelation::make('roles')
                ->label('Roles')
                ->resource(ShowRoleResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2),
        ];
    }
}

final class ShowUserWithRolesAttachDisabledResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            BelongsToManyRelation::make('roles')
                ->label('Roles')
                ->resource(ShowRoleResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2)
                ->allowAttach(false),
        ];
    }
}

final class ShowUserWithRolesDetachDisabledResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            BelongsToManyRelation::make('roles')
                ->label('Roles')
                ->resource(ShowRoleResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2)
                ->allowDetach(false),
        ];
    }
}

final class ShowProfileResource extends Resource
{
    protected static string $model = ShowProfile::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('bio')->label('Bio'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('bio')->label('Bio')->required(),
        ]);
    }
}

final class ShowUserWithProfileResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasOneRelation::make('profile')
                ->label('Profile')
                ->resource(ShowProfileResource::class),
        ];
    }
}

final class ShowUserWithProfileManageResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            HasOneRelation::make('profile')
                ->label('Profile')
                ->resource(ShowProfileResource::class)
                ->allowCreate()
                ->allowEdit()
                ->allowDelete()
                ->createLabel('Add profile')
                ->editLabel('Edit profile'),
        ];
    }
}

final class ShowCommentResource extends Resource
{
    protected static string $model = ShowComment::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('body')->label('Body'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('body')->label('Body')->required(),
        ]);
    }
}

final class ShowAvatarResource extends Resource
{
    protected static string $model = ShowAvatar::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('path')->label('Path'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('path')->label('Path')->required(),
        ]);
    }
}

final class ShowUserWithMorphCommentsResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphManyRelation::make('comments')
                ->label('Comments')
                ->resource(ShowCommentResource::class)
                ->perPage(2)
                ->allowCreate()
                ->allowEdit()
                ->allowDelete(),
        ];
    }
}

final class ShowUserWithMorphCommentsReadOnlyResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphManyRelation::make('comments')
                ->label('Comments')
                ->resource(ShowCommentResource::class)
                ->perPage(2),
        ];
    }
}

final class ShowUserWithMorphAvatarResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphOneRelation::make('avatar')
                ->label('Avatar')
                ->resource(ShowAvatarResource::class),
        ];
    }
}

final class ShowUserWithMorphAvatarManageResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphOneRelation::make('avatar')
                ->label('Avatar')
                ->resource(ShowAvatarResource::class)
                ->allowCreate()
                ->allowEdit()
                ->allowDelete()
                ->createLabel('Add avatar')
                ->editLabel('Edit avatar'),
        ];
    }
}

final class ShowTagResource extends Resource
{
    protected static string $model = ShowTag::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Tag name'),
        ]);
    }
}

final class ShowUserWithMorphTagsResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphToManyRelation::make('tags')
                ->label('Tags')
                ->resource(ShowTagResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2)
                ->pivotColumns([
                    TextColumn::make('source')->label('Source'),
                ]),
        ];
    }
}

final class ShowUserWithMorphTagsAttachDisabledResource extends Resource
{
    protected static string $model = ShowUser::class;

    public static function table(Table $table): Table
    {
        return ShowUserResource::table($table);
    }

    public static function form(Form $form): Form
    {
        return ShowUserResource::form($form);
    }

    public static function relations(): array
    {
        return [
            MorphToManyRelation::make('tags')
                ->label('Tags')
                ->resource(ShowTagResource::class)
                ->titleAttribute('name')
                ->perPage(2)
                ->optionsLimit(2)
                ->allowAttach(false)
                ->allowDetach(false)
                ->pivotColumns([
                    TextColumn::make('source')->label('Source'),
                ])
                ->pivotFields([
                    TextField::make('source')->label('Source'),
                ]),
        ];
    }
}

/**
 * @property string $name
 * @property string|null $email
 * @property bool $is_active
 * @property string|null $published_at
 * @property array<string, mixed>|null $metadata
 * @property string|null $show_note
 * @property string|null $internal_notes
 */
final class ShowUser extends Model
{
    protected $table = 'show_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'date',
        'metadata' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(ShowOrder::class, 'show_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ShowRole::class, 'show_role_user', 'show_user_id', 'show_role_id');
    }

    public function softOrders(): HasMany
    {
        return $this->hasMany(ShowSoftOrder::class, 'show_user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ShowProfile::class, 'show_user_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(ShowComment::class, 'commentable');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(ShowAvatar::class, 'imageable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(ShowTag::class, 'taggable', 'show_taggables', 'taggable_id', 'tag_id');
    }
}

/**
 * @property string|null $show_user_id
 * @property string $number
 * @property string $status
 */
final class ShowOrder extends Model
{
    protected $table = 'show_orders';

    protected $guarded = [];
}

/**
 * @property string $name
 */
final class ShowRole extends Model
{
    protected $table = 'show_roles';

    protected $guarded = [];
}

/**
 * @property int|null $quantity
 * @property string|null $status
 * @property string|null $discount
 * @property string|null $internal_note
 */
final class ShowRoleUserPivot extends Model
{
    protected $table = 'show_role_user';

    protected $guarded = [];
}

/**
 * @property string|null $show_user_id
 * @property string $number
 * @property string $status
 */
final class ShowSoftOrder extends Model
{
    use SoftDeletes;

    protected $table = 'show_soft_orders';

    protected $guarded = [];
}

/**
 * @property string|null $show_user_id
 * @property string|null $bio
 */
final class ShowProfile extends Model
{
    protected $table = 'show_profiles';

    protected $guarded = [];
}

/**
 * @property string $body
 */
final class ShowComment extends Model
{
    protected $table = 'show_comments';

    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * @property string $path
 */
final class ShowAvatar extends Model
{
    protected $table = 'show_avatars';

    protected $guarded = [];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * @property string $name
 */
final class ShowTag extends Model
{
    protected $table = 'show_tags';

    protected $guarded = [];
}

/**
 * @property int|null $tag_id
 * @property string|null $source
 * @property string|null $internal_note
 */
final class ShowTaggablePivot extends Model
{
    protected $table = 'show_taggables';

    protected $guarded = [];
}

final class DenyShowRolePolicy
{
    public function update(mixed $user, ShowRole $role): bool
    {
        return false;
    }
}

final class DenyShowOrderPolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, ShowOrder $order): bool
    {
        return false;
    }
}

final class DenyShowOrderViewPolicy
{
    public function viewAny(mixed $user): bool
    {
        return false;
    }

    public function view(mixed $user, ShowOrder $order): bool
    {
        return false;
    }

    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, ShowOrder $order): bool
    {
        return false;
    }

    public function delete(mixed $user, ShowOrder $order): bool
    {
        return false;
    }

    public function restore(mixed $user, ShowOrder $order): bool
    {
        return false;
    }
}

final class DenyShowSoftOrderPolicy
{
    public function delete(mixed $user, ShowSoftOrder $order): bool
    {
        return false;
    }

    public function restore(mixed $user, ShowSoftOrder $order): bool
    {
        return false;
    }
}

final class DenyShowProfilePolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, ShowProfile $profile): bool
    {
        return false;
    }

    public function delete(mixed $user, ShowProfile $profile): bool
    {
        return false;
    }
}

final class DenyShowCommentPolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, ShowComment $comment): bool
    {
        return false;
    }

    public function delete(mixed $user, ShowComment $comment): bool
    {
        return false;
    }
}

final class DenyShowAvatarPolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, ShowAvatar $avatar): bool
    {
        return false;
    }

    public function delete(mixed $user, ShowAvatar $avatar): bool
    {
        return false;
    }
}

final class DenyShowTagPolicy
{
    public function update(mixed $user, ShowTag $tag): bool
    {
        return false;
    }
}
