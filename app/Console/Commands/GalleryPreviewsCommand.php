<?php

namespace App\Console\Commands;

use App\Models\Events\Event;
use App\Services\Events\EventGalleryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes the light copies of every gallery photo that doesn't have them
 * yet. Pages make them on their own after the first visit; this does it
 * for all events at once (after a deploy, say).
 */
#[Signature('gallery:previews')]
#[Description('Gera as cópias leves das fotos das galerias que ainda não têm')]
class GalleryPreviewsCommand extends Command
{
    public function handle(EventGalleryService $gallery): int
    {
        $prepared = 0;

        Event::query()
            ->whereNotNull('gallery')
            ->each(function (Event $event) use ($gallery, &$prepared): void {
                $prepared += $gallery->prepare($event);
            });

        $this->info("Fotos convertidas: {$prepared}.");

        return self::SUCCESS;
    }
}
