<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';
beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('morphMany renders related records and paginates', function (): void {
    $user = ShowUser::query()->create(['name' => 'Morph Parent', 'email' => 'morph-parent@example.com']);

    foreach (range(1, 4) as $i) {
        ShowComment::query()->create([
            'commentable_type' => ShowUser::class,
            'commentable_id' => $user->getKey(),
            'body' => 'Comment '.$i,
        ]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-morph-comments/'.$user->getKey())
        ->assertOk()
        ->assertSee('Comments')
        ->assertSee('Comment 1')
        ->assertSee('Comment 2')
        ->assertDontSee('Comment 3')
        ->assertDontSee('Comment 4');
});

it('morphMany create associates polymorphic parent fields automatically', function (): void {
    $user = ShowUser::query()->create(['name' => 'Morph Create Parent', 'email' => 'morph-create@example.com']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $user->getKey()])
        ->call('openHasManyCreateModal', 'comments')
        ->set('relationModalValues.body', 'Created comment')
        ->call('saveRelationModal')
        ->assertSee('Related record created successfully.');

    expect(ShowComment::query()->where([
        'commentable_type' => ShowUser::class,
        'commentable_id' => $user->getKey(),
        'body' => 'Created comment',
    ])->exists())->toBeTrue();
});

it('morphMany edit and delete are constrained to related records', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'Morph First', 'email' => 'morph-first@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Morph Second', 'email' => 'morph-second@example.com']);

    $firstComment = ShowComment::query()->create([
        'commentable_type' => ShowUser::class,
        'commentable_id' => $firstUser->getKey(),
        'body' => 'First body',
    ]);
    $secondComment = ShowComment::query()->create([
        'commentable_type' => ShowUser::class,
        'commentable_id' => $secondUser->getKey(),
        'body' => 'Second body',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $firstUser->getKey()])
        ->call('openHasManyEditModal', 'comments', $firstComment->getKey())
        ->set('relationModalValues.body', 'Updated body')
        ->call('saveRelationModal')
        ->assertSee('Related record updated successfully.');

    expect($firstComment->fresh()?->body)->toBe('Updated body');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $firstUser->getKey()])
        ->call('openHasManyEditModal', 'comments', $secondComment->getKey())
        ->assertSee('not available');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $firstUser->getKey()])
        ->call('deleteHasManyRecord', 'comments', $secondComment->getKey(), true)
        ->assertSee('not available');
});

it('morphOne renders existing related record and empty state', function (): void {
    $withAvatar = ShowUser::query()->create(['name' => 'With Avatar', 'email' => 'with-avatar@example.com']);
    $withoutAvatar = ShowUser::query()->create(['name' => 'Without Avatar', 'email' => 'without-avatar@example.com']);

    ShowAvatar::query()->create([
        'imageable_type' => ShowUser::class,
        'imageable_id' => $withAvatar->getKey(),
        'path' => '/img/avatar-a.png',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-morph-avatar/'.$withAvatar->getKey())
        ->assertOk()
        ->assertSee('Avatar')
        ->assertSee('/img/avatar-a.png');

    pest_test_case()->get('/admin/resources/show-users-with-morph-avatar/'.$withoutAvatar->getKey())
        ->assertOk()
        ->assertSee('Avatar')
        ->assertSee('No related records to display.');
});

it('morphOne create and edit work with parent boundary checks', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'Avatar First', 'email' => 'avatar-first@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Avatar Second', 'email' => 'avatar-second@example.com']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $firstUser->getKey()])
        ->call('openHasOneCreateModal', 'avatar')
        ->set('relationModalValues.path', '/img/created.png')
        ->call('saveRelationModal')
        ->assertSee('Related record created successfully.');

    $firstAvatar = ShowAvatar::query()->where([
        'imageable_type' => ShowUser::class,
        'imageable_id' => $firstUser->getKey(),
    ])->first();

    expect($firstAvatar)->not->toBeNull();

    $secondAvatar = ShowAvatar::query()->create([
        'imageable_type' => ShowUser::class,
        'imageable_id' => $secondUser->getKey(),
        'path' => '/img/second.png',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $firstUser->getKey()])
        ->call('openHasOneEditModal', 'avatar', $firstAvatar?->getKey() ?? 0)
        ->set('relationModalValues.path', '/img/updated.png')
        ->call('saveRelationModal')
        ->assertSee('Related record updated successfully.');

    expect($firstAvatar?->fresh()?->path)->toBe('/img/updated.png');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $firstUser->getKey()])
        ->call('openHasOneEditModal', 'avatar', $secondAvatar->getKey())
        ->assertSee('not available');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $firstUser->getKey()])
        ->call('deleteHasOneRecord', 'avatar', $secondAvatar->getKey(), true)
        ->assertSee('not available');
});

it('morph relation actions are blocked by related resource policies', function (): void {
    Gate::policy(ShowComment::class, DenyShowCommentPolicy::class);
    Gate::policy(ShowAvatar::class, DenyShowAvatarPolicy::class);

    $user = ShowUser::query()->create(['name' => 'Denied Morph', 'email' => 'denied-morph@example.com']);
    $comment = ShowComment::query()->create([
        'commentable_type' => ShowUser::class,
        'commentable_id' => $user->getKey(),
        'body' => 'Denied comment',
    ]);
    $avatar = ShowAvatar::query()->create([
        'imageable_type' => ShowUser::class,
        'imageable_id' => $user->getKey(),
        'path' => '/img/denied.png',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $user->getKey()])
        ->call('openHasManyCreateModal', 'comments')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $user->getKey()])
        ->call('openHasManyEditModal', 'comments', $comment->getKey())
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments', 'record' => $user->getKey()])
        ->call('deleteHasManyRecord', 'comments', $comment->getKey(), true)
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $user->getKey()])
        ->call('openHasOneCreateModal', 'avatar')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar-manage', 'record' => $user->getKey()])
        ->call('openHasOneEditModal', 'avatar', $avatar->getKey())
        ->assertForbidden();
});

it('morphMany direct saveRelationModal calls are blocked when create or edit is not allowed', function (): void {
    $user = ShowUser::query()->create(['name' => 'Morph Readonly', 'email' => 'morph-readonly@example.com']);
    $comment = ShowComment::query()->create([
        'commentable_type' => ShowUser::class,
        'commentable_id' => $user->getKey(),
        'body' => 'Initial morph body',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments-readonly', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'comments')
        ->set('relationModalMode', 'create')
        ->set('relationModalValues.body', 'Should not create')
        ->call('saveRelationModal')
        ->assertSee('Creating related records is not allowed for this relation.');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-comments-readonly', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'comments')
        ->set('relationModalMode', 'edit')
        ->set('relationModalRecordId', (string) $comment->getKey())
        ->set('relationModalValues.body', 'Should not edit')
        ->call('saveRelationModal')
        ->assertSee('Editing related records is not allowed for this relation.');

    expect($comment->fresh()?->body)->toBe('Initial morph body');
});

it('morphOne direct saveRelationModal calls are blocked when create or edit is not allowed', function (): void {
    $user = ShowUser::query()->create(['name' => 'Morph One Readonly', 'email' => 'morphone-readonly@example.com']);
    $avatar = ShowAvatar::query()->create([
        'imageable_type' => ShowUser::class,
        'imageable_id' => $user->getKey(),
        'path' => '/img/original.png',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'avatar')
        ->set('relationModalMode', 'create')
        ->set('relationModalValues.path', '/img/should-not-create.png')
        ->call('saveRelationModal')
        ->assertSee('Creating related records is not allowed for this relation.');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-avatar', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'avatar')
        ->set('relationModalMode', 'edit')
        ->set('relationModalRecordId', (string) $avatar->getKey())
        ->set('relationModalValues.path', '/img/should-not-edit.png')
        ->call('saveRelationModal')
        ->assertSee('Editing related records is not allowed for this relation.');

    expect($avatar->fresh()?->path)->toBe('/img/original.png');
});
