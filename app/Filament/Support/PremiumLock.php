<?php

namespace App\Filament\Support;

use App\Enums\Premium\Feature;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;

/**
 * What a host sees in place of a premium screen they haven't unlocked:
 * what the feature does and how to get it.
 */
class PremiumLock
{
    public static function callout(Feature $feature): Callout
    {
        return Callout::make("Recurso premium: {$feature->label()}")
            ->description($feature->description().' Para liberar, fale com o administrador do Wishlisti.')
            ->icon(Heroicon::OutlinedLockClosed)
            ->warning();
    }

    /**
     * For the admin, who can always edit the settings: a reminder that
     * they only take effect once the feature is unlocked.
     */
    public static function notGrantedNotice(Feature $feature): Callout
    {
        return Callout::make("\"{$feature->label()}\" não está liberado neste evento")
            ->description('As configurações abaixo só passam a valer depois de liberar o recurso na aba Premium.')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->info();
    }
}
