<?php

namespace App\Filament\Support;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\PurchaseEventPremium;
use App\Models\Events\Event;
use Filament\Actions\Action;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;

/**
 * What a host sees in place of a premium screen they haven't unlocked:
 * what the feature does, and the way to the premium page to get it.
 */
class PremiumLock
{
    /**
     * @param  Event|null  $event  The event to buy the plan for; without one
     *                             (host-level features), the host's latest event.
     */
    public static function callout(Feature $feature, ?Event $event = null): Callout
    {
        return Callout::make("Recurso premium: {$feature->label()}")
            ->description("{$feature->headline()}. {$feature->description()}")
            ->icon(Heroicon::OutlinedLockClosed)
            ->warning()
            ->actions([
                Action::make('discoverPremium')
                    ->label('Conhecer o Premium — '.PurchaseEventPremium::formattedPrice())
                    ->icon(Heroicon::OutlinedSparkles)
                    ->url(self::purchaseUrl($event)),
            ]);
    }

    public static function purchaseUrl(?Event $event = null): string
    {
        $event ??= Event::query()->managedBy(auth()->id())->latest('id')->first();

        return $event
            ? PurchaseEventPremium::getUrl(['record' => $event])
            : EventResource::getUrl('index');
    }

    /**
     * For the admin, who can always edit the settings: a reminder that
     * they only take effect once the feature is unlocked.
     */
    public static function notGrantedNotice(Feature $feature): Callout
    {
        return Callout::make("\"{$feature->label()}\" não está liberado neste evento")
            ->description('As configurações abaixo só passam a valer depois de liberar o recurso (aba "Liberar recursos") ou de o anfitrião contratar o Premium.')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->info();
    }
}
