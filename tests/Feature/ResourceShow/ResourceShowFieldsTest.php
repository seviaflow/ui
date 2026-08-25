<?php

declare(strict_types=1);

use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';
beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('show route renders for registered resource', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Show User',
        'email' => 'show@example.com',
        'is_active' => true,
        'show_note' => 'Visible only on show',
        'internal_notes' => 'Hidden on show',
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSeeLivewire(ResourceShow::class)
        ->assertSee('sf-card', false)
        ->assertSee('sf-button', false);
});

it('show page displays record values', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Operator User',
        'email' => 'operator@example.com',
        'published_at' => '2026-05-01',
        'is_active' => true,
        'show_note' => 'Operator note',
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Record details')
        ->assertSee('Operator User')
        ->assertSee('operator@example.com')
        ->assertSee('Yes')
        ->assertSee('2026-05-01')
        ->assertSee('Operator note');
});

it('hiddenOnShow fields are not displayed and visibleOnShow fields are displayed', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Context User',
        'email' => 'context@example.com',
        'is_active' => false,
        'show_note' => 'Show-only text',
        'internal_notes' => 'Should not be visible',
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Show note')
        ->assertSee('Show-only text')
        ->assertDontSee('Internal notes')
        ->assertDontSee('Should not be visible');
});

it('null values render as safe placeholder', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Nullable User',
        'email' => null,
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Email address')
        ->assertSee('—');
});

it('boolean false renders clearly as no', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Inactive User',
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('No');
});

it('sections render on show page', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Section User',
        'email' => 'section@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Account');
});

it('complex values render safely on show page', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Metadata User',
        'email' => 'metadata@example.com',
        'metadata' => ['role' => 'operator'],
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Metadata')
        ->assertSee('{"role":"operator"}');
});

it('show page does not render editable inputs', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Read Only User',
        'email' => 'readonly-show@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('sf-input', false)
        ->assertDontSee('sf-textarea', false)
        ->assertDontSee('sf-select', false);
});

it('handles unknown resource slug safely on show page', function (): void {
    pest_test_case()->get('/admin/resources/unknown-slug/1')
        ->assertNotFound();
});

it('handles unknown record safely on show page', function (): void {
    pest_test_case()->get('/admin/resources/show-users/999999')
        ->assertNotFound();
});
