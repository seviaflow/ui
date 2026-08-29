<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;
use SeviaFlow\Ui\Navigation\NavigationMetadataResolver;

/**
 * @param  array{
 *   groups: array<int, array{
 *     key:string,
 *     label:string,
 *     items: array<int, array<string,mixed>>
 *   }>
 * }  $metadata
 * @return array<string, array<string,mixed>>
 */
function navigationIconMetadataItems(array $metadata): array
{
    $items = [];

    foreach ($metadata['groups'] as $group) {
        foreach ($group['items'] as $item) {
            $items[(string) $item['key']] = $item;
        }
    }

    return $items;
}

it('assigns canonical icons to built in navigation metadata without a database connection', function (): void {
    $resolver = new NavigationMetadataResolver(
        new ResourceRegistry([]),
        new DashboardRegistry,
    );

    $items = navigationIconMetadataItems($resolver->resolve());

    expect($items['dashboard:default']['icon'] ?? null)->toBe('squares-2x2')
        ->and($items['settings.roles']['icon'] ?? null)->toBe('shield-check')
        ->and($items['settings.admin-users']['icon'] ?? null)->toBe('users')
        ->and($items['developer.api-docs']['icon'] ?? null)->toBe('code-bracket')
        ->and($items['account.profile']['icon'] ?? null)->toBe('user-circle')
        ->and($items['account.security']['icon'] ?? null)->toBe('lock-closed')
        ->and($items['account.logout']['icon'] ?? null)->toBe('arrow-right-on-rectangle');
});

it('copies resource navigation icon slugs into navigation metadata without a database connection', function (): void {
    $resolver = new NavigationMetadataResolver(
        new ResourceRegistry(['navigation-icon-resource' => NavigationIconMetadataResource::class]),
        new DashboardRegistry,
    );

    $items = navigationIconMetadataItems($resolver->resolve());

    expect($items['resource:navigation-icon-resource']['icon'] ?? null)->toBe('users')
        ->and($items['resource:navigation-icon-resource']['label'] ?? null)->toBe('Navigation icon resources');
});

final class NavigationIconMetadataModel extends Model {}

final class NavigationIconMetadataResource extends Resource
{
    protected static string $model = NavigationIconMetadataModel::class;

    protected static ?string $navigationLabel = 'Navigation icon resources';

    protected static ?string $navigationIcon = 'users';
}
