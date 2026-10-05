<?php

namespace App\Support;

use App\Models\Platform\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The settings the admin changes on the panel (Configurações). Until they
 * do, each one falls back to config/premium.php.
 */
class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings';

    public static function premiumPrice(): float
    {
        return (float) (self::get('premium_price') ?? config('premium.price'));
    }

    /** Days the Premium's event features last after the event's date. */
    public static function premiumGraceDays(): int
    {
        return (int) (self::get('premium_grace_days') ?? config('premium.grace_days'));
    }

    /**
     * How far (in days, either way) the host can move the date of a Premium
     * event from the one it was bought for; further, only the admin can.
     */
    public static function premiumDateWindowDays(): int
    {
        return (int) (self::get('premium_date_window_days') ?? config('premium.date_window_days'));
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    private static function get(string $key): ?string
    {
        /** @var array<string, string|null> $all */
        $all = Cache::rememberForever(self::CACHE_KEY, fn (): array => PlatformSetting::pluck('value', 'key')->all());

        return $all[$key] ?? null;
    }
}
