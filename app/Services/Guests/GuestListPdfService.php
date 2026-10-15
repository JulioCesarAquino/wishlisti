<?php

namespace App\Services\Guests;

use App\Models\Events\Event;
use App\Models\Events\EventLocation;
use App\Models\Guests\Guest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The guest list on paper: each person on a line, starting with a box that
 * is ticked when they confirmed and left empty when they haven't answered —
 * room for the host to tick it by hand, at the door or on the phone.
 * Companions come right under whoever listed them.
 */
class GuestListPdfService
{
    public const SITE = 'wishlisti.com.br';

    public function render(Event $event, bool $includeDeclined = false, bool $showWhatsapp = false, bool $hostView = true): string
    {
        $event->loadMissing(['locations', 'rsvpSettings']);

        $groups = $this->groups($event, $hostView);
        $headcount = $event->rsvpHeadcount();

        $pdf = Pdf::loadView('pdf.guest-list', [
            'event' => $event,
            'date' => $this->date($event),
            'locations' => $event->locations->map(fn (EventLocation $location) => [
                'name' => $location->name,
                'time' => Event::formatTime($location->start_time),
                'address' => $location->address,
            ]),
            'coming' => $groups->reject(fn (array $group) => $group['guest']->rsvp_status === Guest::RSVP_DECLINED)->values(),
            'declined' => $includeDeclined
                ? $groups->filter(fn (array $group) => $group['guest']->rsvp_status === Guest::RSVP_DECLINED)->values()
                : collect(),
            'people' => $headcount['people'],
            'children' => $headcount['children'],
            'pending' => $groups->filter(fn (array $group) => $group['guest']->rsvp_status === null)->count(),
            'childAgeLimit' => $event->childAgeLimit(),
            'showWhatsapp' => $showWhatsapp,
            'generatedAt' => now(Event::TIMEZONE)->format('d/m/Y \à\s H:i'),
        ])->setPaper('a4');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans');
        $grey = [0.45, 0.45, 0.45];
        $y = $canvas->get_height() - 34;
        $margin = 45;

        $canvas->page_text($margin, $y, 'Wishlisti  ·  '.self::SITE, $font, 7, $grey);
        $pageLabel = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $canvas->page_text($canvas->get_width() - $margin - $fontMetrics->getTextWidth('Página 00 de 00', $font, 7), $y, $pageLabel, $font, 7, $grey);

        return $dompdf->output();
    }

    public function filename(Event $event): string
    {
        return 'convidados-'.$event->slug.'.pdf';
    }

    /**
     * Everyone who answered or was invited, alphabetically, each with the
     * companions they listed and how many more people they said were
     * coming without naming them.
     *
     * @return Collection<int, array{guest: Guest, companions: Collection<int, Guest>, unnamed: int}>
     */
    private function groups(Event $event, bool $hostView): Collection
    {
        $guests = $event->guests()
            ->when($hostView, fn ($query) => $query->scopes('visibleToHost'))
            ->get();

        $alphabetically = fn (Collection $guests) => $guests
            ->sortBy(fn (Guest $guest) => Str::lower(Str::ascii($guest->name)), SORT_NATURAL)
            ->values();

        $companions = $guests->whereNotNull('companion_of_guest_id')->groupBy('companion_of_guest_id');

        return $alphabetically($guests->whereNull('companion_of_guest_id'))
            ->map(function (Guest $guest) use ($companions, $alphabetically) {
                /** @var Collection<int, Guest> $listed */
                $listed = $alphabetically($companions->get($guest->id, collect()));

                return [
                    'guest' => $guest,
                    'companions' => $listed,
                    'unnamed' => $guest->rsvp_status === Guest::RSVP_CONFIRMED
                        ? max(0, (int) $guest->rsvp_guests_count - 1 - $listed->count())
                        : 0,
                ];
            });
    }

    /** "Sábado, 14 de novembro de 2026 · 16h" */
    private function date(Event $event): ?string
    {
        if (! $event->event_date) {
            return null;
        }

        $day = Carbon::parse($event->event_date->toDateString());
        $day->setLocale('pt_BR');

        return implode(' · ', array_filter([
            Str::ucfirst($day->isoFormat('dddd, D [de] MMMM [de] YYYY')),
            Event::formatTime($event->event_time),
        ]));
    }
}
