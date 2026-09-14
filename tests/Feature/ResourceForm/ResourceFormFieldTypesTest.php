<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

it('url field validates valid and invalid values', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'contact-form-users'])
        ->set('fieldValues.name', 'Contact Valid')
        ->set('fieldValues.website', 'https://example.com')
        ->set('fieldValues.phone', '+48 123-456-789')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Contact Valid')->first();
    expect($created)->not->toBeNull()
        ->and((string) $created?->website)->toBe('https://example.com');

    Livewire::test(ResourceForm::class, ['resource' => 'contact-form-users'])
        ->set('fieldValues.name', 'Contact Invalid Url')
        ->set('fieldValues.website', 'not-a-url')
        ->set('fieldValues.phone', '+48 123-456-789')
        ->call('save')
        ->assertHasErrors(['fieldValues.website' => 'url']);
});

it('phone field renders as tel input', function (): void {
    pest_test_case()->get('/admin/resources/contact-form-users/create')
        ->assertOk()
        ->assertSee('type="tel"', false)
        ->assertSee('Phone number');
});

it('slug field stores source field metadata and validates slug-like values', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'slug-form-users'])
        ->set('fieldValues.name', 'Slug Valid')
        ->set('fieldValues.slug', 'my-slug-1')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Slug Valid')->first();
    expect($created)->not->toBeNull()
        ->and((string) $created?->slug)->toBe('my-slug-1');

    Livewire::test(ResourceForm::class, ['resource' => 'slug-form-users'])
        ->set('fieldValues.name', 'Slug Invalid')
        ->set('fieldValues.slug', 'Invalid Slug!')
        ->call('save')
        ->assertHasErrors(['fieldValues.slug' => 'regex']);
});

it('readonly slug field is not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Readonly Slug',
        'slug' => 'original-slug',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'slug-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'Readonly Slug Updated')
        ->set('fieldValues.slug', 'tampered-slug')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->slug)->toBe('original-slug')
        ->and($user->name)->toBe('Readonly Slug Updated');
});

it('multiselect field validates required non-empty', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'multiselect-form-users'])
        ->set('fieldValues.name', 'Multi Required')
        ->set('fieldValues.channels', [])
        ->call('save')
        ->assertHasErrors(['fieldValues.channels' => 'required']);
});

it('multiselect field validates allowed options', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'multiselect-form-users'])
        ->set('fieldValues.name', 'Multi Invalid')
        ->set('fieldValues.channels', ['email', 'hacker'])
        ->call('save')
        ->assertHasErrors(['fieldValues.channels.1']);
});

it('multiselect field persists selected values as array', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'multiselect-form-users'])
        ->set('fieldValues.name', 'Multi Valid')
        ->set('fieldValues.channels', ['email', 'push'])
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Multi Valid')->first();

    expect($created)->not->toBeNull()
        ->and($created?->channels)->toBe(['email', 'push']);
});

it('multiselect hidden readonly and disabled fields are not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Multi Context',
        'channels' => ['email'],
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'multiselect-context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_channels', ['sms'])
        ->set('fieldValues.readonly_channels', ['sms'])
        ->set('fieldValues.disabled_channels', ['sms'])
        ->set('fieldValues.name', 'Multi Context Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->channels)->toBe(['email'])
        ->and($user->name)->toBe('Multi Context Updated');
});

it('file upload stores path', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf');

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users'])
        ->set('fieldValues.name', 'Upload File User')
        ->set('fieldValues.document_path', $file)
        ->set('fieldValues.avatar_path', UploadedFile::fake()->image('avatar.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Upload File User')->first();

    expect($created)->not->toBeNull()
        ->and((string) $created?->document_path)->not->toBe('');

    Storage::disk('public')->assertExists((string) $created?->document_path);
});

it('image upload stores path', function (): void {
    Storage::fake('public');

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users'])
        ->set('fieldValues.name', 'Upload Image User')
        ->set('fieldValues.document_path', UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf'))
        ->set('fieldValues.avatar_path', UploadedFile::fake()->image('avatar.png'))
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Upload Image User')->first();

    expect($created)->not->toBeNull()
        ->and((string) $created?->avatar_path)->not->toBe('');

    Storage::disk('public')->assertExists((string) $created?->avatar_path);
});

it('invalid file mime type fails validation', function (): void {
    Storage::fake('public');

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users'])
        ->set('fieldValues.name', 'Upload Invalid Mime')
        ->set('fieldValues.document_path', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
        ->set('fieldValues.avatar_path', UploadedFile::fake()->image('avatar.jpg'))
        ->call('save')
        ->assertHasErrors(['fieldValues.document_path' => 'mimetypes']);
});

it('oversized upload fails validation', function (): void {
    Storage::fake('public');

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users'])
        ->set('fieldValues.name', 'Upload Too Big')
        ->set('fieldValues.document_path', UploadedFile::fake()->create('manual.pdf', 3000, 'application/pdf'))
        ->set('fieldValues.avatar_path', UploadedFile::fake()->image('avatar.jpg'))
        ->call('save')
        ->assertHasErrors(['fieldValues.document_path' => 'max']);
});

it('edit with no new file keeps existing path', function (): void {
    Storage::fake('public');
    $existingPath = UploadedFile::fake()->create('existing.pdf', 20, 'application/pdf')->store('documents', 'public');

    $user = FormUser::query()->create([
        'name' => 'Upload Edit Keep',
        'document_path' => $existingPath,
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'Upload Edit Keep Updated')
        ->set('fieldValues.document_path', null)
        ->set('fieldValues.avatar_path', null)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->document_path)->toBe($existingPath)
        ->and($user->name)->toBe('Upload Edit Keep Updated');
});

it('removes an existing file through the explicit form action', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/removable.pdf', 'old');
    $user = FormUser::query()->create([
        'name' => 'Upload Remove',
        'document_path' => 'documents/removable.pdf',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users', 'record' => $user->getKey()])
        ->call('removeFile', 'document_path')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->document_path)->toBeNull();
    Storage::disk('public')->assertMissing('documents/removable.pdf');
});

it('downloads a public file through the authorized file route', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/download.pdf', 'download');
    $user = FormUser::query()->create([
        'name' => 'Upload Download',
        'document_path' => 'documents/download.pdf',
    ]);

    pest_test_case()->get('/admin/resources/upload-form-users/'.$user->getKey().'/files/document_path')
        ->assertOk();
});

it('blocks private file downloads without an explicit read authorization', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/private.pdf', 'private');
    $user = FormUser::query()->create([
        'name' => 'Upload Private',
        'document_path' => 'documents/private.pdf',
    ]);
    config()->set('seviaflow.resources', array_merge(
        (array) config('seviaflow.resources', []),
        ['private-upload-users' => PrivateUploadFormUserResource::class]
    ));

    pest_test_case()->get('/admin/resources/private-upload-users/'.$user->getKey().'/files/document_path')
        ->assertForbidden();
});

it('hidden readonly and disabled file fields are not persisted when tampered', function (): void {
    Storage::fake('public');
    $originalPath = UploadedFile::fake()->create('original.pdf', 20, 'application/pdf')->store('documents', 'public');

    $user = FormUser::query()->create([
        'name' => 'Upload Context',
        'document_path' => $originalPath,
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'upload-context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_document_path', UploadedFile::fake()->create('hidden.pdf', 10, 'application/pdf'))
        ->set('fieldValues.readonly_document_path', UploadedFile::fake()->create('readonly.pdf', 10, 'application/pdf'))
        ->set('fieldValues.disabled_document_path', UploadedFile::fake()->create('disabled.pdf', 10, 'application/pdf'))
        ->set('fieldValues.name', 'Upload Context Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->document_path)->toBe($originalPath)
        ->and($user->name)->toBe('Upload Context Updated');
});

final class PrivateUploadFormUserResource extends Resource
{
    protected static string $model = FormUser::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            FileField::make('document_path')
                ->disk('public')
                ->directory('documents')
                ->visibility(FileField::VISIBILITY_PRIVATE),
        ]);
    }
}
