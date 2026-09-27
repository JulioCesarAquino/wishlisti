<?php

namespace App\Models\Premium;

use App\Enums\Premium\Feature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $grantable_type
 * @property int $grantable_id
 * @property Feature $feature
 * @property string $source
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['feature', 'source', 'expires_at'])]
class FeatureGrant extends Model
{
    use LogsActivity;

    /** Unlocked by the admin from the panel. */
    public const SOURCE_ADMIN = 'admin';

    /** Bought by the host (checkout still to be built). */
    public const SOURCE_PURCHASE = 'purchase';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('premium')
            ->logOnly(['feature', 'source', 'expires_at'])
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'liberou um recurso premium',
                'deleted' => 'removeu um recurso premium',
                default => 'atualizou um recurso premium',
            });
    }

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function grantable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
