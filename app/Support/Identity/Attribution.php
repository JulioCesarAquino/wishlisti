<?php

namespace App\Support\Identity;

use App\Models\Events\Event;

/**
 * Where a new host came from: the event page they followed ("Feita com
 * Wishlisti", ?ref=slug) and the ad campaign of the link (utm_*). The
 * browser keeps them for a while (see resources/js/lib/attribution.ts).
 */
class Attribution
{
    public const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign'];

    /**
     * Validation for the fields the forms send along.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            // An unknown slug is just ignored.
            'ref' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * The invite request's columns for it.
     *
     * @param  array<string, mixed>  $data
     * @return array{referred_by_event_id: int|null, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null}
     */
    public static function columns(array $data): array
    {
        $ref = $data['ref'] ?? null;

        return [
            'referred_by_event_id' => filled($ref) ? Event::where('slug', $ref)->value('id') : null,
            'utm_source' => filled($data['utm_source'] ?? null) ? $data['utm_source'] : null,
            'utm_medium' => filled($data['utm_medium'] ?? null) ? $data['utm_medium'] : null,
            'utm_campaign' => filled($data['utm_campaign'] ?? null) ? $data['utm_campaign'] : null,
        ];
    }
}
