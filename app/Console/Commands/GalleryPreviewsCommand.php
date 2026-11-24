<?php

namespace App\Console\Commands;

use App\Models\Events\Event;
use App\Services\Events\EventGalleryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Makes the light copies of the gallery photos that don't have them yet.
 * Every minute, by the scheduler (routes/console.php), a batch at a time:
 * an album of hundreds of photos is ready in a few minutes, without
 * holding the site's own processes. Without --limit, all of them (after a
 * deploy, say).
 */
#[Signature('gallery:previews {--limit= : Máximo de fotos por execução}')]
#[Description('Gera as cópias leves das fotos das galerias que ainda não têm')]
class GalleryPreviewsCommand extends Command
{
    public function handle(EventGalleryService $gallery): int
    {
        $prepared = 0;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        Event::query()
            ->whereHas('albums', fn ($query) => $query->whereNotNull('photos'))
            ->orderByDesc('updated_at')
            ->each(function (Event $event) use ($gallery, $limit, &$prepared): bool {
                $left = $limit === null ? null : $limit - $prepared;

                // Not alongside a page that's already making this event's.
                Cache::lock("event-gallery:{$event->id}", 600)
                    ->get(function () use ($gallery, $event, $left, &$prepared): void {
                        $prepared += $gallery->prepare($event, $left);
                    });

                return $limit === null || $prepared < $limit;
            });

        $this->info("Fotos convertidas: {$prepared}.");

        return self::SUCCESS;
    }
}
