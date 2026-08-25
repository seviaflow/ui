<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $dashboard
 * @property array<int, array<string, mixed>> $layout
 * @property string $user_type
 * @property string|int $user_id
 * @property-read MorphTo<Model, self> $user
 */
final class DashboardLayout extends Model
{
    protected $table = 'seviaflow_dashboard_layouts';

    protected $guarded = [];

    protected $casts = [
        'layout' => 'array',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }
}
