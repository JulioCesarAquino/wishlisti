<?php

namespace App\Models\Concerns;

use App\Enums\Premium\Feature;
use App\Models\Premium\FeatureGrant;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models that premium features can be unlocked for (events, hosts).
 */
trait HasFeatureGrants
{
    /**
     * @return MorphMany<FeatureGrant, $this>
     */
    public function featureGrants(): MorphMany
    {
        return $this->morphMany(FeatureGrant::class, 'grantable');
    }

    public function hasFeature(Feature $feature): bool
    {
        return $this->featureGrants->contains(
            fn (FeatureGrant $grant) => $grant->feature === $feature && $grant->isActive(),
        );
    }

    /**
     * @return array<int, Feature>
     */
    public function activeFeatures(): array
    {
        return array_values(array_filter(Feature::for(static::class), fn (Feature $feature) => $this->hasFeature($feature)));
    }

    /**
     * Makes the given features — and only them — active, as picked by the
     * admin in the panel. Removing a feature here removes it whatever its
     * source, since the admin has the last word.
     *
     * @param  array<int, Feature|string>  $features
     */
    public function syncFeatures(array $features, string $source = FeatureGrant::SOURCE_ADMIN): void
    {
        $wanted = array_map(fn (Feature|string $feature) => $feature instanceof Feature ? $feature : Feature::from($feature), $features);

        foreach (Feature::for(static::class) as $feature) {
            $enabled = in_array($feature, $wanted, true);

            if ($enabled && ! $this->hasFeature($feature)) {
                $this->featureGrants()->where('feature', $feature)->get()->each->delete();
                $this->featureGrants()->create(['feature' => $feature, 'source' => $source]);
            }

            if (! $enabled) {
                $this->featureGrants()->where('feature', $feature)->get()->each->delete();
            }
        }

        $this->unsetRelation('featureGrants');
    }

    public function grantFeature(Feature $feature, string $source = FeatureGrant::SOURCE_ADMIN): void
    {
        $this->syncFeatures([...$this->activeFeatures(), $feature], $source);
    }
}
