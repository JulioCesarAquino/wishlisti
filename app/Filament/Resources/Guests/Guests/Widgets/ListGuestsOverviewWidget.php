<?php

namespace App\Filament\Resources\Guests\Guests\Widgets;

use App\Filament\Resources\Guests\Guests\Pages\ListGuests;
use App\Filament\Support\EventPicker;
use App\Filament\Widgets\GuestsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

/**
 * The dashboard's guest totals, above the guest list: about the event its
 * filter picked, or all of them.
 *
 * A widget of its own (and outside app/Filament/Widgets, so the dashboard
 * doesn't pick it up): the table's properties are reactive, and on the
 * dashboard, which has no table, they'd come in null and break every
 * request — Livewire answers those with a 419, "page expired".
 */
class ListGuestsOverviewWidget extends GuestsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListGuests::class;
    }

    protected function eventId(): ?int
    {
        return EventPicker::chosen($this->tableFilters['event_id']['value'] ?? null);
    }
}
