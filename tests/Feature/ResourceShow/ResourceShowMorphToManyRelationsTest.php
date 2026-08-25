<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('morphToMany renders attached records', function (): void {
    $user = ShowUser::query()->create(['name' => 'Tags User', 'email' => 'tags-user@example.com']);
    $tag = ShowTag::query()->create(['name' => 'VIP']);
    $user->tags()->attach($tag->getKey(), ['source' => 'manual']);

    pest_test_case()->get('/admin/resources/show-users-with-morph-tags/'.$user->getKey())
        ->assertOk()
        ->assertSee('Tags')
        ->assertSee('Tag name')
        ->assertSee('VIP')
        ->assertSee('Detach');
});

it('morphToMany records are paginated', function (): void {
    $user = ShowUser::query()->create(['name' => 'Paginated Tags User', 'email' => 'paginated-tags@example.com']);

    foreach (range(1, 4) as $index) {
        $tag = ShowTag::query()->create(['name' => 'Tag '.$index]);
        $user->tags()->attach($tag->getKey());
    }

    pest_test_case()->get('/admin/resources/show-users-with-morph-tags/'.$user->getKey())
        ->assertOk()
        ->assertSee('Tag 1')
        ->assertSee('Tag 2')
        ->assertDontSee('Tag 3')
        ->assertDontSee('Tag 4');
});

it('morphToMany attach options are limited', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach Limit Tags', 'email' => 'attach-limit-tags@example.com']);

    foreach (range(1, 4) as $index) {
        ShowTag::query()->create(['name' => 'Attach Tag '.$index]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-morph-tags/'.$user->getKey())
        ->assertOk()
        ->assertSee('Attach Tag 1')
        ->assertSee('Attach Tag 2')
        ->assertDontSee('Attach Tag 3')
        ->assertDontSee('Attach Tag 4');
});

it('morphToMany attach works and duplicate attach is handled safely', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach Tags User', 'email' => 'attach-tags@example.com']);
    $tag = ShowTag::query()->create(['name' => 'Editor']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->set('attachSelection.tags', (string) $tag->getKey())
        ->call('attachBelongsToMany', 'tags')
        ->assertSee('Related record attached successfully.');

    expect($user->tags()->whereKey($tag->getKey())->exists())->toBeTrue();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->set('attachSelection.tags', (string) $tag->getKey())
        ->call('attachBelongsToMany', 'tags')
        ->assertSee('already attached');
});

it('morphToMany detach works and blocks unrelated detaches', function (): void {
    $user = ShowUser::query()->create(['name' => 'Detach Tags User', 'email' => 'detach-tags@example.com']);
    $otherUser = ShowUser::query()->create(['name' => 'Other Tags User', 'email' => 'other-tags@example.com']);
    $tag = ShowTag::query()->create(['name' => 'Detach Tag']);
    $otherTag = ShowTag::query()->create(['name' => 'Other Tag']);
    $user->tags()->attach($tag->getKey());
    $otherUser->tags()->attach($otherTag->getKey());

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'tags', $tag->getKey())
        ->assertSee('Please confirm detaching this related record before continuing.');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'tags', $tag->getKey(), true)
        ->assertSee('Related record detached successfully.');

    expect($user->tags()->whereKey($tag->getKey())->exists())->toBeFalse();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'tags', $otherTag->getKey(), true)
        ->assertSee('is not attached');
});

it('morphToMany shows only configured pivot columns', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot Tags User', 'email' => 'pivot-tags@example.com']);
    $tag = ShowTag::query()->create(['name' => 'Pivot Tag']);
    $user->tags()->attach($tag->getKey(), [
        'source' => 'sync',
        'internal_note' => 'secret',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-morph-tags/'.$user->getKey())
        ->assertOk()
        ->assertSee('Source')
        ->assertSee('sync')
        ->assertDontSee('internal_note')
        ->assertDontSee('secret');
});

it('morphToMany attach and detach respect related resource policy', function (): void {
    Gate::policy(ShowTag::class, DenyShowTagPolicy::class);

    $user = ShowUser::query()->create(['name' => 'Policy Tags User', 'email' => 'policy-tags@example.com']);
    $tag = ShowTag::query()->create(['name' => 'Blocked Tag']);
    $user->tags()->attach($tag->getKey());

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->set('attachSelection.tags', (string) $tag->getKey())
        ->call('attachBelongsToMany', 'tags')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'tags', $tag->getKey(), true)
        ->assertForbidden();
});

it('morphToMany crafted pivot attach calls are blocked when attaching is disabled', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach Disabled Tags', 'email' => 'attach-disabled-tags@example.com']);
    $tag = ShowTag::query()->create(['name' => 'Attach Disabled Tag']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-morph-tags-attach-disabled', 'record' => $user->getKey()])
        ->set('pivotModalRelationship', 'tags')
        ->set('pivotModalMode', 'attach')
        ->set('attachSelection.tags', (string) $tag->getKey())
        ->set('pivotModalValues.source', 'manual')
        ->call('savePivotModal')
        ->assertSee('Attaching related records is not allowed for this relation.');

    expect($user->tags()->whereKey($tag->getKey())->exists())->toBeFalse();
});
