<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Services\Events\EventSharePreviewService;
use App\Support\Demos\DemoCatalog;
use App\Support\PlatformSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page: what Wishlisti does, the demonstration events to explore
 * and the way to sign up.
 */
class WelcomeController extends Controller
{
    public function __invoke(EventSharePreviewService $sharePreview): Response
    {
        $demos = Event::query()
            ->where('is_demo', true)
            ->where('is_published', true)
            ->whereIn('slug', array_keys(DemoCatalog::all()))
            ->get()
            ->sortBy(fn (Event $event) => array_search($event->slug, array_keys(DemoCatalog::all()), true))
            ->values();

        $card = $demos->first(fn (Event $event) => $event->cover_image !== null);

        return Inertia::render('welcome', [
            'demos' => $demos->map(fn (Event $event) => [
                'slug' => $event->slug,
                'title' => $event->title,
                'type_label' => $event->typeLabel(),
                'cover_image_url' => $event->coverImageUrl(),
                'url' => route('events.show', ['event' => $event->slug]),
            ]),
            'pricing' => [
                'premium_price' => PlatformSettings::premiumPrice(),
                'free_gift_limit' => (int) config('premium.free_gift_limit'),
                'free_photo_limit' => PlatformSettings::freePhotoLimit(),
            ],
        ])->withViewData('share', [
            'title' => 'Wishlisti: a página do seu evento',
            'description' => 'Convite, confirmação de presença, lista de presentes com Pix e álbum de fotos em um só link, pronto para mandar no WhatsApp. Comece grátis.',
            'image' => $card ? $sharePreview->url($card) : null,
            'url' => route('home'),
        ]);
    }
}
