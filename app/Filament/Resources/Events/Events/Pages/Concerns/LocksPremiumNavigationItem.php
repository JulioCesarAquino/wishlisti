<?php

namespace App\Filament\Resources\Events\Events\Pages\Concerns;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use Filament\Support\Icons\Heroicon;

/**
 * Shows the page's item in the event sub-navigation with a lock and a
 * "Premium" badge while the event doesn't have the page's feature.
 */
trait LocksPremiumNavigationItem
{
    abstract protected static function premiumFeature(): Feature;

    /**
     * @param  array<string, mixed>  $urlParameters
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        $items = parent::getNavigationItems($urlParameters);
        $record = $urlParameters['record'] ?? null;

        if ($record instanceof Event && ! $record->hasFeature(static::premiumFeature())) {
            foreach ($items as $item) {
                $item->icon(Heroicon::OutlinedLockClosed)->badge('Premium', 'warning');
            }
        }

        return $items;
    }
}
