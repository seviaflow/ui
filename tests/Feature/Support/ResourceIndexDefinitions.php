<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\StatusColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEvents;
use SeviaFlow\Core\Resources\Table;

final class TestUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email'),
            BooleanColumn::make('is_active')->label('Active'),
        ]);
    }
}

final class SearchSortUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->label('Email'),
            BooleanColumn::make('is_active')->label('Active'),
        ]);
    }
}

final class FilterUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('status')->label('Status'),
                BooleanColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                BooleanFilter::make('is_active')->label('Active'),
                SelectFilter::make('status')->label('Status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),
            ]);
    }
}

final class SoftDeleteUserResource extends Resource
{
    protected static string $model = SoftDeleteUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email'),
        ]);
    }
}

final class SoftDeleteEventTracker
{
    /**
     * @var array<int, array{
     *   event:string,
     *   source:string,
     *   operation:string,
     *   exists:bool,
     *   original:array<string,mixed>,
     *   changes:array<string,mixed>
     * }>
     */
    public static array $events = [];

    public static bool $throwOnDeleting = false;

    public static bool $throwOnRestoring = false;

    public static bool $throwUnexpectedOnDeleted = false;

    public static function reset(): void
    {
        self::$events = [];
        self::$throwOnDeleting = false;
        self::$throwOnRestoring = false;
        self::$throwUnexpectedOnDeleted = false;
    }
}

final class SoftDeleteEventUserResource extends Resource
{
    protected static string $model = SoftDeleteUser::class;

    public static function table(Table $table): Table
    {
        return SoftDeleteUserResource::table($table);
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events
            ->onDeleting(function (SoftDeleteUser $record, ResourceEventContext $context): void {
                SoftDeleteEventTracker::$events[] = [
                    'event' => 'deleting',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'exists' => $record->exists,
                    'original' => $context->original(),
                    'changes' => $context->changes(),
                ];

                if (SoftDeleteEventTracker::$throwOnDeleting) {
                    throw new LogicException('Delete blocked by event.');
                }
            })
            ->onDeleted(function (SoftDeleteUser $record, ResourceEventContext $context): void {
                SoftDeleteEventTracker::$events[] = [
                    'event' => 'deleted',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'exists' => $record->exists,
                    'original' => $context->original(),
                    'changes' => $context->changes(),
                ];

                if (SoftDeleteEventTracker::$throwUnexpectedOnDeleted) {
                    throw new RuntimeException('Unexpected delete event failure.');
                }
            })
            ->onRestoring(function (SoftDeleteUser $record, ResourceEventContext $context): void {
                SoftDeleteEventTracker::$events[] = [
                    'event' => 'restoring',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'exists' => $record->exists,
                    'original' => $context->original(),
                    'changes' => $context->changes(),
                ];

                if (SoftDeleteEventTracker::$throwOnRestoring) {
                    throw new LogicException('Restore blocked by event.');
                }
            })
            ->onRestored(function (SoftDeleteUser $record, ResourceEventContext $context): void {
                SoftDeleteEventTracker::$events[] = [
                    'event' => 'restored',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'exists' => $record->exists,
                    'original' => $context->original(),
                    'changes' => $context->changes(),
                ];
            });
    }
}

final class FormEnabledUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
        ]);
    }
}

final class VisibilityUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email')->hiddenOnIndex(),
        ]);
    }
}

final class BelongsToColumnUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            BelongsToColumn::make('company')
                ->label('Company')
                ->relationship('company', 'name'),
        ]);
    }
}

final class BadgeColumnUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            BadgeColumn::make('status')
                ->label('Status')
                ->labels([
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'active' => 'Active',
                    'blocked' => 'Blocked',
                ])
                ->colors([
                    'draft' => 'gray',
                    'pending' => 'yellow',
                    'active' => 'green',
                    'blocked' => 'javascript:alert(1)',
                ]),
        ]);
    }
}

final class StatusColumnUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            StatusColumn::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'active' => 'Active',
                    'blocked' => 'Blocked',
                ])
                ->colors([
                    'draft' => 'gray',
                    'pending' => 'yellow',
                    'active' => 'green',
                    'blocked' => 'red',
                ]),
        ]);
    }
}

final class ExportEnabledUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->label('Email'),
            TextColumn::make('status')->label('Status'),
        ]);
    }

    public static function export(ExportResource $export): ExportResource
    {
        return $export
            ->enabled()
            ->allowedFields([
                ExportField::make('id')->label('ID'),
                ExportField::make('name')->label('Name'),
                ExportField::make('email')->label('Email address'),
            ])
            ->formats(['csv']);
    }
}

final class ExportDisabledUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class ExportNoFieldsUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }

    public static function export(ExportResource $export): ExportResource
    {
        return $export->enabled()->allowedFields([]);
    }
}

final class ImportEnabledUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('email')->label('Email'),
            TextColumn::make('status')->label('Status'),
        ]);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import
            ->enabled()
            ->allowedFields(['name', 'email', 'status'])
            ->requiredFields(['name', 'email']);
    }
}

final class ImportEventTracker
{
    /**
     * @var array<int, array{event:string,source:string,row:int|null}>
     */
    public static array $events = [];

    public static bool $throwOnCreating = false;

    public static function reset(): void
    {
        self::$events = [];
        self::$throwOnCreating = false;
    }
}

final class ImportEventUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return ImportEnabledUserResource::table($table);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return ImportEnabledUserResource::import($import);
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events
            ->onCreating(function (TestUser $record, ResourceEventContext $context): void {
                ImportEventTracker::$events[] = [
                    'event' => 'creating',
                    'source' => $context->source(),
                    'row' => is_int($context->metadata()['row'] ?? null) ? $context->metadata()['row'] : null,
                ];

                if (ImportEventTracker::$throwOnCreating) {
                    throw new InvalidArgumentException('Import blocked by event.');
                }
            })
            ->onCreated(function (TestUser $record, ResourceEventContext $context): void {
                ImportEventTracker::$events[] = [
                    'event' => 'created',
                    'source' => $context->source(),
                    'row' => is_int($context->metadata()['row'] ?? null) ? $context->metadata()['row'] : null,
                ];
            });
    }
}

final class ImportDisabledUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class ImportLimitedFieldsUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import
            ->enabled()
            ->allowedFields(['name', 'email'])
            ->requiredFields(['name', 'email']);
    }
}

final class ImportTypedUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import
            ->enabled()
            ->allowedFields(['name', 'email', 'is_active'])
            ->requiredFields(['name', 'email']);
    }
}

final class ImportFormRulesUserResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            EmailField::make('email')->label('Email address')->required(),
            TextField::make('status')->label('Status')->required(),
            PasswordField::make('password')->label('Password')->requiredOnCreate(),
        ]);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import
            ->enabled()
            ->allowedFields(['name', 'email', 'status'])
            ->requiredFields(['name', 'email'])
            ->useFormFieldRules();
    }
}

/**
 * @property string $name
 * @property string $email
 * @property string $status
 * @property bool $is_active
 */
final class TestUser extends Model
{
    protected $table = 'test_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(IndexCompany::class, 'company_id');
    }
}

/**
 * @property string $name
 * @property string $email
 * @property string $status
 * @property bool $is_active
 */
final class SoftDeleteUser extends Model
{
    use SoftDeletes;

    protected $table = 'soft_delete_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

final class IndexCompany extends Model
{
    protected $table = 'index_companies';

    protected $guarded = [];
}
