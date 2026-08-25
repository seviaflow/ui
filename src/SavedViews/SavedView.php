<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\SavedViews;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $resource
 * @property string $name
 * @property string $visibility
 * @property string $user_type
 * @property string|int $user_id
 * @property array<string, mixed> $state
 * @property-read MorphTo<Model, self> $user
 */
final class SavedView extends Model
{
    protected $table = 'seviaflow_saved_views';

    protected $guarded = [];

    protected $casts = [
        'state' => 'array',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }
}
