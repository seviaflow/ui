<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\SavedViews\SavedView;
use SeviaFlow\Ui\SavedViews\SavedViewRepository;

beforeEach(function (): void {
    Schema::create('saved_view_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
        $table->id();
        $table->string('resource');
        $table->string('name');
        $table->string('user_type');
        $table->string('user_id');
        $table->string('visibility');
        $table->json('state');
        $table->timestamps();
    });
});

it('creates and loads a private saved view scoped to user and resource slug', function (): void {
    $user = SavedViewTestUser::query()->create(['name' => 'Owner']);
    $repository = new SavedViewRepository;

    $savedView = $repository->createPrivate($user, 'orders', 'My Paid Orders', [
        'search' => 'acme',
        'filters' => ['status' => 'paid'],
        'sort' => ['column' => 'created_at', 'direction' => 'desc'],
        'per_page' => 25,
    ]);

    expect($savedView->resource)->toBe('orders')
        ->and($savedView->visibility)->toBe('private')
        ->and($savedView->user_type)->toBe($user->getMorphClass())
        ->and((string) $savedView->user_id)->toBe((string) $user->getKey());

    $loaded = $repository->forResourceAndUser('orders', $user);

    expect($loaded)->toHaveCount(1)
        ->and($loaded->first()?->is($savedView))->toBeTrue();
});

it('fails to create private saved view without authenticated user', function (): void {
    $repository = new SavedViewRepository;

    expect(fn () => $repository->createPrivate(null, 'orders', 'No User', []))
        ->toThrow(InvalidArgumentException::class, 'Private saved views require an authenticated user.');
});

it('stores only allowed state keys', function (): void {
    $user = SavedViewTestUser::query()->create(['name' => 'Owner']);
    $repository = new SavedViewRepository;

    $savedView = $repository->createPrivate($user, 'orders', 'Safe State', [
        'search' => '  paid  ',
        'filters' => ['status' => 'paid', 123 => 'bad-key'],
        'sort' => ['column' => 'created_at', 'direction' => 'DESC'],
        'per_page' => 50,
        'resource' => 'hijack',
        'unexpected' => ['raw' => true],
        'admin_only' => true,
    ]);

    expect($savedView->state)->toBe([
        'state_version' => 1,
        'search' => 'paid',
        'filters' => ['status' => 'paid'],
        'sort' => ['column' => 'created_at', 'direction' => 'desc'],
        'per_page' => 50,
        'columns' => [],
        'trash' => 'active',
    ]);
});

it('enforces slug resource and rejects class names', function (): void {
    $user = SavedViewTestUser::query()->create(['name' => 'Owner']);
    $repository = new SavedViewRepository;

    expect(fn () => $repository->createPrivate($user, SavedView::class, 'Invalid Resource', []))
        ->toThrow(InvalidArgumentException::class, 'Resource must be a slug, not a class name.');
});

it('isolates private saved views between users', function (): void {
    $owner = SavedViewTestUser::query()->create(['name' => 'Owner']);
    $other = SavedViewTestUser::query()->create(['name' => 'Other']);
    $repository = new SavedViewRepository;

    $ownerView = $repository->createPrivate($owner, 'orders', 'Owner View', []);
    $repository->createPrivate($other, 'orders', 'Other View', []);

    $ownerLoaded = $repository->forResourceAndUser('orders', $owner);
    $otherLoaded = $repository->forResourceAndUser('orders', $other);

    expect($ownerLoaded)->toHaveCount(1)
        ->and($ownerLoaded->first()?->id)->toBe($ownerView->id)
        ->and($otherLoaded)->toHaveCount(1)
        ->and($otherLoaded->first()?->id)->not->toBe($ownerView->id);
});

it('deletes only owned private saved views', function (): void {
    $owner = SavedViewTestUser::query()->create(['name' => 'Owner']);
    $other = SavedViewTestUser::query()->create(['name' => 'Other']);
    $repository = new SavedViewRepository;

    $ownerView = $repository->createPrivate($owner, 'orders', 'Owner View', []);

    expect($repository->deleteOwned($ownerView, $other))->toBeFalse()
        ->and(SavedView::query()->whereKey($ownerView->id)->exists())->toBeTrue()
        ->and($repository->deleteOwned($ownerView, $owner))->toBeTrue()
        ->and(SavedView::query()->whereKey($ownerView->id)->exists())->toBeFalse();
});

/**
 * @property string $name
 */
final class SavedViewTestUser extends Model
{
    protected $table = 'saved_view_users';

    protected $guarded = [];
}
