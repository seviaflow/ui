<?php

declare(strict_types=1);

use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';
beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('workflow panel renders when workflow exists', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-001',
        'status' => 'draft',
    ]);

    pest_test_case()->get('/admin/resources/show-orders-workflow/'.$order->getKey())
        ->assertOk()
        ->assertSee('Workflow')
        ->assertSee('Draft')
        ->assertSee('sf-workflow-panel', false)
        ->assertSee('sf-badge', false);
});

it('no workflow panel when resource has no workflow', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-002',
        'status' => 'draft',
    ]);

    pest_test_case()->get('/admin/resources/show-orders/'.$order->getKey())
        ->assertOk()
        ->assertDontSee('Current process state and available transitions.');
});

it('available workflow transitions render and invalid transitions do not render', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-003',
        'status' => 'draft',
    ]);

    pest_test_case()->get('/admin/resources/show-orders-workflow/'.$order->getKey())
        ->assertOk()
        ->assertSee('Submit order')
        ->assertSee('Cancel order')
        ->assertSee('Comment')
        ->assertSee('Add reason for this transition')
        ->assertDontSee('Ship order');
});

it('workflow transition can be executed from show page and updates state', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-004',
        'status' => 'draft',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-orders-workflow', 'record' => $order->getKey()])
        ->call('executeWorkflowTransition', 'submit')
        ->assertSee('Transition completed successfully.');

    expect($order->fresh()?->status)->toBe('pending');
});

it('workflow transition required comment is enforced', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-005',
        'status' => 'pending',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-orders-workflow', 'record' => $order->getKey()])
        ->set('workflowTransitionComment', '')
        ->call('executeWorkflowTransition', 'cancel', true)
        ->assertSee('requires a comment');

    expect($order->fresh()?->status)->toBe('pending');
});

it('safe confirmation panel renders confirm and cancel for pending workflow transition', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-005A',
        'status' => 'pending',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-orders-workflow', 'record' => $order->getKey()])
        ->call('executeWorkflowTransition', 'cancel')
        ->assertSee('Confirm dangerous transition')
        ->assertSee('Confirm')
        ->assertSee('Cancel');
});

it('workflow timeline displays activity after transition', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-006',
        'status' => 'draft',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-orders-workflow', 'record' => $order->getKey()])
        ->call('executeWorkflowTransition', 'submit');

    pest_test_case()->get('/admin/resources/show-orders-workflow/'.$order->getKey())
        ->assertOk()
        ->assertSee('Activity timeline')
        ->assertSee('sf-timeline', false)
        ->assertSee('sf-timeline-item', false)
        ->assertSee('Submit')
        ->assertSee('draft')
        ->assertSee('pending');
});

it('failed workflow transition shows safe error', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'WF-007',
        'status' => 'draft',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-orders-workflow-failing', 'record' => $order->getKey()])
        ->call('executeWorkflowTransition', 'submit')
        ->assertSee('The transition could not be completed.');

    expect($order->fresh()?->status)->toBe('draft');
});

it('workflow visibleIf false hides transition on show page', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'HIDDEN-001',
        'status' => 'draft',
    ]);

    pest_test_case()->get('/admin/resources/show-orders-workflow-visibility/'.$order->getKey())
        ->assertOk()
        ->assertDontSee('Submit order');
});

it('workflow visibleIf true shows transition on show page', function (): void {
    $order = ShowOrder::query()->create([
        'show_user_id' => 1,
        'number' => 'SHOW-001',
        'status' => 'draft',
    ]);

    pest_test_case()->get('/admin/resources/show-orders-workflow-visibility/'.$order->getKey())
        ->assertOk()
        ->assertSee('Submit order');
});
