<?php

namespace App\Filament\Widgets;

use App\Models\Guests\Guest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuestsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();

        $guestsQuery = Guest::query()
            ->when(! $isAdmin, fn ($query) => $query->whereHas(
                'event',
                fn ($eventQuery) => $eventQuery->where('user_id', auth()->id()),
            ));

        // Companions listed by another guest are already inside that guest's
        // headcount, so they don't count as a separate response.
        $confirmedGuests = (clone $guestsQuery)
            ->where('rsvp_status', Guest::RSVP_CONFIRMED)
            ->whereNull('companion_of_guest_id')
            ->count();
        $totalPeopleConfirmed = (int) (clone $guestsQuery)
            ->where('rsvp_status', Guest::RSVP_CONFIRMED)
            ->sum('rsvp_guests_count');
        $declinedGuests = (clone $guestsQuery)->where('rsvp_status', Guest::RSVP_DECLINED)->count();

        return [
            Stat::make('Confirmaram presença', (string) $confirmedGuests)
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make('Total de pessoas confirmadas', (string) $totalPeopleConfirmed)
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info'),
            Stat::make('Não vão', (string) $declinedGuests)
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger'),
        ];
    }
}
