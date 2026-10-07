<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\EmailField;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\ImageField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\PhoneField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\SlugField;
use SeviaFlow\Core\Resources\Fields\TextareaField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\TimeField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Fields\UrlField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEvents;
use SeviaFlow\Core\Resources\Section;

final class FormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->sections([
            Section::make('Basic information')
                ->description('Main information visible to operators.')
                ->fields([
                    TextField::make('name')->label('Name')->required(),
                    EmailField::make('email')->label('Email address'),
                    NumberField::make('age')->label('Age')->min(0)->max(120),
                    TextareaField::make('bio')->label('Biography')->rows(4),
                    DateField::make('published_at')->label('Published at'),
                ]),
            Section::make('Status')
                ->description('Controls whether the account is active.')
                ->fields([
                    ToggleField::make('is_active')->label('Active'),
                    SelectField::make('status')->label('Status')->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
                ]),
        ]);
    }
}

final class FlatFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
        ]);
    }
}

final class PresenceFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            TextField::make('default_status')->label('Database default status'),
        ]);
    }
}

final class ContextFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            EmailField::make('email')->label('Email address'),
            PasswordField::make('password')->label('Password')->requiredOnCreate()->hiddenOnEdit(),
            TextField::make('slug')->label('Slug')->readonlyOnEdit(),
            SelectField::make('status')->label('Status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
            ])->disabledOnEdit(),
            TextareaField::make('internal_notes')->label('Internal notes')->hiddenOn([ResourceContext::Create, ResourceContext::Edit]),
        ]);
    }
}

final class PasswordFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            EmailField::make('email')->label('Email address')->required(),
            PasswordField::make('password')->label('New password'),
        ]);
    }
}

final class PasswordContextFormUserResource extends Resource
{
    protected static string $model = PasswordContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            PasswordField::make('hidden_password')->label('Hidden password')->hiddenOnEdit(),
            PasswordField::make('readonly_password')->label('Readonly password')->readonlyOnEdit(),
            PasswordField::make('disabled_password')->label('Disabled password')->disabledOnEdit(),
        ]);
    }
}

/**
 * @property string $name
 * @property string|null $email
 * @property int|null $age
 * @property string|null $bio
 * @property bool $is_active
 * @property string|null $status
 * @property string|null $default_status
 * @property Carbon|null $published_at
 * @property string|null $password
 * @property string|null $slug
 * @property string|null $internal_notes
 * @property string|null $price
 * @property string|null $starts_at
 * @property string|null $opening_time
 * @property string|null $website
 * @property string|null $phone
 * @property array<int, string>|null $channels
 * @property string|null $document_path
 * @property string|null $avatar_path
 */
final class FormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'date',
        'channels' => 'array',
    ];
}

/**
 * @property string|null $password
 */
final class PasswordContextFormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    public function setHiddenPasswordAttribute(mixed $value): void
    {
        $this->attributes['password'] = $value;
    }

    public function setReadonlyPasswordAttribute(mixed $value): void
    {
        $this->attributes['password'] = $value;
    }

    public function setDisabledPasswordAttribute(mixed $value): void
    {
        $this->attributes['password'] = $value;
    }
}

final class MoneyFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            MoneyField::make('price')->label('Price')->currency('PLN')->min(0)->required(),
        ]);
    }
}

final class MoneyContextFormUserResource extends Resource
{
    protected static string $model = MoneyContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            MoneyField::make('hidden_price')->label('Hidden price')->hiddenOnEdit(),
            MoneyField::make('readonly_price')->label('Readonly price')->readonlyOnEdit(),
            MoneyField::make('disabled_price')->label('Disabled price')->disabledOnEdit(),
        ]);
    }
}

/**
 * @property string|null $price
 */
final class MoneyContextFormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    public function setHiddenPriceAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }

    public function setReadonlyPriceAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }

    public function setDisabledPriceAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }
}

final class ScheduleFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            DateTimeField::make('starts_at')->label('Starts at')->required(),
            TimeField::make('opening_time')->label('Opening time')->required(),
        ]);
    }
}

final class ScheduleContextFormUserResource extends Resource
{
    protected static string $model = ScheduleContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            DateTimeField::make('hidden_starts_at')->label('Hidden starts at')->hiddenOnEdit(),
            DateTimeField::make('readonly_starts_at')->label('Readonly starts at')->readonlyOnEdit(),
            DateTimeField::make('disabled_starts_at')->label('Disabled starts at')->disabledOnEdit(),
            TimeField::make('hidden_opening_time')->label('Hidden opening time')->hiddenOnEdit(),
            TimeField::make('readonly_opening_time')->label('Readonly opening time')->readonlyOnEdit(),
            TimeField::make('disabled_opening_time')->label('Disabled opening time')->disabledOnEdit(),
        ]);
    }
}

/**
 * @property string|null $starts_at
 * @property string|null $opening_time
 */
final class ScheduleContextFormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    public function setHiddenStartsAtAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $value;
    }

    public function setReadonlyStartsAtAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $value;
    }

    public function setDisabledStartsAtAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $value;
    }

    public function setHiddenOpeningTimeAttribute(mixed $value): void
    {
        $this->attributes['opening_time'] = $value;
    }

    public function setReadonlyOpeningTimeAttribute(mixed $value): void
    {
        $this->attributes['opening_time'] = $value;
    }

    public function setDisabledOpeningTimeAttribute(mixed $value): void
    {
        $this->attributes['opening_time'] = $value;
    }
}

final class ContactFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            UrlField::make('website')->label('Website')->required(),
            PhoneField::make('phone')->label('Phone number')->required(),
        ]);
    }
}

final class SlugFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            SlugField::make('slug')->label('Slug')->from('name')->readonlyOnEdit()->required(),
        ]);
    }
}

final class MultiSelectFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            MultiSelectField::make('channels')
                ->label('Enabled channels')
                ->options([
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'push' => 'Push notification',
                ])
                ->required(),
        ]);
    }
}

final class MultiSelectContextFormUserResource extends Resource
{
    protected static string $model = MultiSelectContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            MultiSelectField::make('hidden_channels')
                ->label('Hidden channels')
                ->options([
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'push' => 'Push notification',
                ])
                ->hiddenOnEdit(),
            MultiSelectField::make('readonly_channels')
                ->label('Readonly channels')
                ->options([
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'push' => 'Push notification',
                ])
                ->readonlyOnEdit(),
            MultiSelectField::make('disabled_channels')
                ->label('Disabled channels')
                ->options([
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'push' => 'Push notification',
                ])
                ->disabledOnEdit(),
        ]);
    }
}

/**
 * @property array<int, string>|null $channels
 */
final class MultiSelectContextFormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    protected $casts = [
        'channels' => 'array',
    ];

    public function setHiddenChannelsAttribute(mixed $value): void
    {
        $this->attributes['channels'] = json_encode($value);
    }

    public function setReadonlyChannelsAttribute(mixed $value): void
    {
        $this->attributes['channels'] = json_encode($value);
    }

    public function setDisabledChannelsAttribute(mixed $value): void
    {
        $this->attributes['channels'] = json_encode($value);
    }
}

final class UploadFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            FileField::make('document_path')
                ->label('Document')
                ->disk('public')
                ->directory('documents')
                ->maxSize(2048)
                ->acceptedFileTypes(['application/pdf'])
                ->requiredOnCreate(),
            ImageField::make('avatar_path')
                ->label('Avatar')
                ->disk('public')
                ->directory('avatars')
                ->maxSize(2048),
        ]);
    }
}

final class UploadContextFormUserResource extends Resource
{
    protected static string $model = UploadContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            FileField::make('hidden_document_path')
                ->label('Hidden document')
                ->disk('public')
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf'])
                ->hiddenOnEdit(),
            FileField::make('readonly_document_path')
                ->label('Readonly document')
                ->disk('public')
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf'])
                ->readonlyOnEdit(),
            FileField::make('disabled_document_path')
                ->label('Disabled document')
                ->disk('public')
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf'])
                ->disabledOnEdit(),
        ]);
    }
}

/**
 * @property string|null $document_path
 */
final class UploadContextFormUser extends Model
{
    protected $table = 'form_users';

    protected $guarded = [];

    public function setHiddenDocumentPathAttribute(mixed $value): void
    {
        $this->attributes['document_path'] = $value;
    }

    public function setReadonlyDocumentPathAttribute(mixed $value): void
    {
        $this->attributes['document_path'] = $value;
    }

    public function setDisabledDocumentPathAttribute(mixed $value): void
    {
        $this->attributes['document_path'] = $value;
    }
}

final class BelongsToFormUserResource extends Resource
{
    protected static string $model = BelongsToFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->query(static fn ($query) => $query->where('active', true)->where('tenant_id', 1))
                ->searchableOptions(['name', 'email'])
                ->optionsLimit(2)
                ->placeholder('Select company')
                ->required(),
        ]);
    }
}

final class BelongsToInlineFormUserResource extends Resource
{
    protected static string $model = BelongsToInlineFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->inlineCreate(BelongsToInlineCompanyResource::class)
                ->inlineCreateLabel('Create Company')
                ->placeholder('Select company'),
        ]);
    }
}

final class BelongsToInlineDeniedFormUserResource extends Resource
{
    protected static string $model = BelongsToInlineFormUser::class;

    public static function form(Form $form): Form
    {
        return BelongsToInlineFormUserResource::form($form);
    }
}

final class BelongsToContextFormUserResource extends Resource
{
    protected static string $model = BelongsToContextFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('hidden_company_id')
                ->label('Hidden company')
                ->relationship('company', 'name')
                ->hiddenOnEdit(),
            BelongsToField::make('readonly_company_id')
                ->label('Readonly company')
                ->relationship('company', 'name')
                ->readonlyOnEdit(),
            BelongsToField::make('disabled_company_id')
                ->label('Disabled company')
                ->relationship('company', 'name')
                ->disabledOnEdit(),
        ]);
    }
}

/**
 * @property string $name
 * @property string|null $email
 * @property bool $active
 * @property int $tenant_id
 */
final class BelongsToFormCompany extends Model
{
    protected $table = 'form_companies';

    protected $guarded = [];
}

final class BelongsToInlineCompanyResource extends Resource
{
    protected static string $model = BelongsToFormCompany::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            EmailField::make('email')->label('Email address'),
        ]);
    }
}

/**
 * @property string $name
 * @property int|null $company_id
 */
final class BelongsToFormUser extends Model
{
    protected $table = 'belongs_to_form_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToFormCompany::class, 'company_id');
    }
}

/**
 * @property string $name
 * @property int|null $company_id
 * @property int|null $country_id
 * @property int|null $city_id
 */
final class BelongsToInlineFormUser extends Model
{
    protected $table = 'belongs_to_form_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToFormCompany::class, 'company_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(LocationCountry::class, 'country_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(LocationCity::class, 'city_id');
    }
}

/**
 * @property string $name
 * @property int|null $company_id
 */
final class BelongsToContextFormUser extends Model
{
    protected $table = 'belongs_to_form_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BelongsToFormCompany::class, 'company_id');
    }

    public function setHiddenCompanyIdAttribute(mixed $value): void
    {
        $this->attributes['company_id'] = $value;
    }

    public function setReadonlyCompanyIdAttribute(mixed $value): void
    {
        $this->attributes['company_id'] = $value;
    }

    public function setDisabledCompanyIdAttribute(mixed $value): void
    {
        $this->attributes['company_id'] = $value;
    }
}

final class DenyBelongsToInlineCompanyPolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }
}

final class LocationUserResource extends Resource
{
    protected static string $model = BelongsToInlineFormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            BelongsToField::make('country_id')
                ->label('Country')
                ->relationship('country', 'name')
                ->optionsLimit(10),
            BelongsToField::make('city_id')
                ->label('City')
                ->relationship('city', 'name')
                ->dependsOn('country_id', static fn ($query, $countryId) => $query->where('country_id', $countryId))
                ->optionsLimit(2),
        ]);
    }
}

/**
 * @property string $name
 */
final class LocationCountry extends Model
{
    protected $table = 'location_countries';

    protected $guarded = [];
}

/**
 * @property int $country_id
 * @property string $name
 */
final class LocationCity extends Model
{
    protected $table = 'location_cities';

    protected $guarded = [];
}

final class ResourceFormEventTracker
{
    /**
     * @var array<int, array{
     *   event:string,
     *   exists:bool,
     *   source:string,
     *   operation:string,
     *   resource:string,
     *   slug:string,
     *   changes:array<string,mixed>,
     *   original:array<string,mixed>,
     *   user:mixed
     * }>
     */
    public static array $events = [];

    public static bool $throwOnCreating = false;

    public static bool $throwOnUpdating = false;

    public static bool $throwUnexpectedOnCreated = false;

    public static int $creatingCalls = 0;

    public static int $createdCalls = 0;

    public static int $updatingCalls = 0;

    public static int $updatedCalls = 0;

    public static function reset(): void
    {
        self::$events = [];
        self::$throwOnCreating = false;
        self::$throwOnUpdating = false;
        self::$throwUnexpectedOnCreated = false;
        self::$creatingCalls = 0;
        self::$createdCalls = 0;
        self::$updatingCalls = 0;
        self::$updatedCalls = 0;
    }
}

final class ResourceEventFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name')->required(),
            EmailField::make('email')->label('Email address'),
        ]);
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events
            ->onCreating(function (FormUser $record, ResourceEventContext $context): void {
                ResourceFormEventTracker::$creatingCalls++;
                ResourceFormEventTracker::$events[] = [
                    'event' => 'creating',
                    'exists' => $record->exists,
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'resource' => $context->resourceClass(),
                    'slug' => $context->resourceSlug(),
                    'changes' => $context->changes(),
                    'original' => $context->original(),
                    'user' => $context->user(),
                ];

                if (ResourceFormEventTracker::$throwOnCreating) {
                    throw new LogicException('Create blocked by event.');
                }
            })
            ->onCreated(function (FormUser $record, ResourceEventContext $context): void {
                ResourceFormEventTracker::$createdCalls++;
                ResourceFormEventTracker::$events[] = [
                    'event' => 'created',
                    'exists' => $record->exists,
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'resource' => $context->resourceClass(),
                    'slug' => $context->resourceSlug(),
                    'changes' => $context->changes(),
                    'original' => $context->original(),
                    'user' => $context->user(),
                ];

                if (ResourceFormEventTracker::$throwUnexpectedOnCreated) {
                    throw new RuntimeException('Unexpected event failure.');
                }
            })
            ->onUpdating(function (FormUser $record, ResourceEventContext $context): void {
                ResourceFormEventTracker::$updatingCalls++;
                ResourceFormEventTracker::$events[] = [
                    'event' => 'updating',
                    'exists' => $record->exists,
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'resource' => $context->resourceClass(),
                    'slug' => $context->resourceSlug(),
                    'changes' => $context->changes(),
                    'original' => $context->original(),
                    'user' => $context->user(),
                ];

                if (ResourceFormEventTracker::$throwOnUpdating) {
                    throw new LogicException('Update blocked by event.');
                }
            })
            ->onUpdated(function (FormUser $record, ResourceEventContext $context): void {
                ResourceFormEventTracker::$updatedCalls++;
                ResourceFormEventTracker::$events[] = [
                    'event' => 'updated',
                    'exists' => $record->exists,
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                    'resource' => $context->resourceClass(),
                    'slug' => $context->resourceSlug(),
                    'changes' => $context->changes(),
                    'original' => $context->original(),
                    'user' => $context->user(),
                ];
            });
    }
}
