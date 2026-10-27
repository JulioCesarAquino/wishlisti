<?php

namespace App\Filament\Support;

use App\Models\Events\Event;
use Filament\Forms\Components\Select;

/**
 * Which event the panel's totals and the guest list are about: one of the
 * events the user manages (any of them, for the admin), or all at once —
 * opening on the user's default event.
 */
class EventPicker
{
    /**
     * The newest first.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $user = auth()->user();

        return Event::query()
            ->when(! $user?->isAdmin(), fn ($query) => $query->managedBy($user?->id))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * The user's default event, while it's still one they can pick (not
     * in the trash, still theirs).
     */
    public static function default(): ?int
    {
        $eventId = auth()->user()?->default_event_id;

        return $eventId !== null && array_key_exists($eventId, self::options()) ? $eventId : null;
    }

    public static function select(string $name): Select
    {
        return Select::make($name)
            ->label('Evento')
            ->options(fn (): array => self::options())
            ->placeholder('Todos os eventos')
            ->searchable();
    }

    /**
     * A picked event, or null for all of them. The value comes from the URL
     * too: an event the user can't pick counts as none.
     */
    public static function chosen(mixed $value): ?int
    {
        if (! filled($value) || ! is_numeric($value)) {
            return null;
        }

        return array_key_exists((int) $value, self::options()) ? (int) $value : null;
    }
}
