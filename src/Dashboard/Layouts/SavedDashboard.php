<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $dashboard
 * @property string $name
 * @property string $visibility
 * @property array<int, array<string, mixed>> $layout
 * @property bool $is_default
 * @property string $user_type
 * @property string|int $user_id
 * @property-read MorphTo<Model, self> $user
 */
final class SavedDashboard extends Model
{
    protected $table = 'seviaflow_saved_dashboards';

    protected $guarded = [];

    protected $casts = [
        'layout' => 'array',
        'is_default' => 'bool',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }
}
