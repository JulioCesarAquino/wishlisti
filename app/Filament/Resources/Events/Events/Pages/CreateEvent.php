<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    /**
     * Set on signing up from the Premium plan ("Começar agora"): the first
     * event, once created, opens its Premium payment.
     */
    public const PREMIUM_NEXT = 'premium_after_first_event';

    protected static string $resource = EventResource::class;

    protected function getRedirectUrl(): string
    {
        if (session()->pull(self::PREMIUM_NEXT)) {
            return EventResource::getUrl('premium', ['record' => $this->getRecord()]);
        }

        return parent::getRedirectUrl();
    }
}
