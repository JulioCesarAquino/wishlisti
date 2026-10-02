<?php

namespace App\Enums\Events;

/**
 * The tabs of an event's public page, in their default order. The host
 * turns each one on or off and reorders them (see EventSection) — except
 * the home tab, which is where the page opens.
 */
enum PageSection: string
{
    case Home = 'inicio';
    case Gallery = 'galeria';
    case Gifts = 'presentes';
    case Rsvp = 'confirmar_presenca';
    case Guestbook = 'recado';
    case Location = 'localizacao';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Início',
            self::Gallery => 'Galeria',
            self::Gifts => 'Presentes',
            self::Rsvp => 'Confirmar presença',
            self::Guestbook => 'Recados',
            self::Location => 'Localização',
        };
    }

    /**
     * How the page addresses the tab, in the URL hash ("#confirmar-presenca").
     * Kept as it was before tabs could be configured, so links already
     * shared still land on the right tab.
     */
    public function key(): string
    {
        return match ($this) {
            self::Rsvp => 'confirmar-presenca',
            self::Guestbook => 'recados',
            default => $this->value,
        };
    }

    public function canBeDisabled(): bool
    {
        return $this !== self::Home;
    }

    /**
     * When the tab shows up regardless of the host's choice — tabs with
     * nothing to show stay out of the menu.
     */
    public function requirement(): ?string
    {
        return match ($this) {
            self::Gifts => 'Só aparece quando os presentes estão no modo "lista" (em Presentes → Exibição na página).',
            self::Guestbook => 'Só aparece em eventos com o mural de recados (Premium).',
            self::Location => 'Só aparece quando o evento tem ao menos uma localização.',
            default => null,
        };
    }
}
