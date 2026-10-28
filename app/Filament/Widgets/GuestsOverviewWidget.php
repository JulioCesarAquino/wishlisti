<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Guests\Guests\GuestResource;
use App\Filament\Support\EventPicker;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * On the dashboard, about the event picked there — or all of them. Above
 * the guest list, see ListGuestsOverviewWidget.
 */
class GuestsOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function eventId(): ?int
    {
        return EventPicker::chosen($this->pageFilters['event_id'] ?? null);
    }

    protected function getStats(): array
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();
        $eventId = $this->eventId();

        // The same guests as the list below: not those of an event in the
        // trash, and only the host's own events for a host.
        $guestsQuery = GuestResource::getEloquentQuery()
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId));

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

        // Events that tell children apart (each with its own age).
        $childEvents = Event::query()
            ->when(! $isAdmin, fn ($query) => $query->managedBy(auth()->id()))
            ->when($eventId, fn ($query) => $query->whereKey($eventId))
            ->whereHas('rsvpSettings', fn ($query) => $query->whereNotNull('child_age_limit'))
            ->with('rsvpSettings')
            ->get();
        $ages = $childEvents->map(fn (Event $event) => $event->childAgeLimit())->unique();

        return [
            Stat::make('Total de pessoas confirmadas', (string) $totalPeopleConfirmed)
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info'),
            Stat::make('Confirmaram presença', (string) $confirmedGuests)
                ->description('Respostas (os acompanhantes vão no total de pessoas)')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            ...($childEvents->isEmpty() ? [] : [
                Stat::make(
                    $ages->count() === 1 ? "Crianças (menos de {$ages->first()} anos)" : 'Crianças',
                    (string) $childEvents->sum(fn (Event $event) => $event->rsvpHeadcount()['children'] ?? 0),
                )
                    ->description('Já incluídas no total de pessoas')
                    ->icon(Heroicon::OutlinedFaceSmile)
                    ->color('warning'),
            ]),
            Stat::make('Não vão', (string) $declinedGuests)
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger'),
        ];
    }
}
