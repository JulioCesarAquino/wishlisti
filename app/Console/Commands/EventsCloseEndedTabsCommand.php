<?php

namespace App\Console\Commands;

use App\Models\Events\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('events:close-ended-tabs')]
#[Description('Desliga as abas de localização e de confirmar presença dos eventos que já passaram (uma vez só)')]
class EventsCloseEndedTabsCommand extends Command
{
    public function handle(): int
    {
        $closed = 0;

        Event::query()
            ->whereNull('tabs_closed_after_event_at')
            ->whereDate('event_date', '<', Carbon::now(Event::TIMEZONE)->toDateString())
            ->chunkById(100, function ($events) use (&$closed): void {
                foreach ($events as $event) {
                    $closed += (int) $event->closeTabsAfterEvent();
                }
            });

        $this->info("{$closed} evento(s) com as abas desligadas.");

        return self::SUCCESS;
    }
}
