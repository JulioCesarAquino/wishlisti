<?php

namespace App\Console\Commands;

use App\Models\Events\Event;
use App\Services\Events\FreeGalleryPurgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily (routes/console.php): warns the hosts of free events whose photos
 * go soon, and deletes those whose day has come (see Event::photosDeleteOn()).
 */
#[Signature('gallery:purge-free')]
#[Description('Avisa e apaga as fotos dos eventos gratuitos depois do prazo após o evento')]
class GalleryPurgeFreeCommand extends Command
{
    public function handle(FreeGalleryPurgeService $service): int
    {
        $warned = 0;
        $deleted = 0;

        Event::query()
            ->where('is_demo', false)
            ->whereNotNull('event_date')
            ->whereHas('albums', fn ($albums) => $albums->whereNotNull('photos'))
            ->with(['albums', 'user', 'coHosts'])
            ->chunkById(50, function ($events) use ($service, &$warned, &$deleted): void {
                foreach ($events as $event) {
                    $photos = $service->purgeIfDue($event);
                    $deleted += $photos;

                    if ($photos === 0 && $service->warnIfDue($event)) {
                        $warned++;
                    }
                }
            });

        $this->info("Eventos avisados: {$warned}. Fotos apagadas: {$deleted}.");

        return self::SUCCESS;
    }
}
