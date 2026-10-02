<?php

namespace App\Http\Controllers\Events;

use App\Enums\Events\PageSection;
use App\Enums\Premium\Feature;
use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventGiftSetting;
use App\Models\Events\EventLocation;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class EventShowController extends Controller
{
    /**
     * Also serves the location links (see routes/web.php): the same page,
     * opened on the location tab.
     */
    public function __invoke(Request $request, Event $event, ?EventLocation $location = null): Response
    {
        abort_unless($event->isViewableBy($request->user()), 404);

        $event->load(['appearance', 'rsvpSettings', 'paymentSettings', 'giftSettings', 'featureGrants', 'locations', 'sections']);

        $onLocationTab = $request->routeIs('events.locations', 'events.location');

        // Also when the host turned the tab off after sharing the link.
        abort_if($onLocationTab && ! $event->showsSection(PageSection::Location), 404);

        $guest = null;
        $identifier = Guest::identifierFrom($request, $event);

        if ($identifier) {
            $guest = $event->guests()->where('identifier', $identifier)->first();
        }

        if ($event->shouldCountVisitFor($request->user()) && ! $request->cookie($event->visitCookieName())) {
            $event->increment('visits_count');
            Cookie::queue(Cookie::make($event->visitCookieName(), '1', 60 * 12));
        }

        return Inertia::render('events/show', [
            'event' => [
                'slug' => $event->slug,
                'type' => $event->type,
                'title' => $event->title,
                'event_date' => $event->event_date?->toDateString(),
                'description' => $event->description,
                'cover_image_url' => $event->coverImageUrl(),
                'gallery_urls' => $event->galleryUrls(),
                'story' => $event->story,
                'url' => route('events.show', ['event' => $event->slug]),
                'sections' => array_map(fn (PageSection $section) => [
                    'key' => $section->key(),
                    'label' => $section->label(),
                ], $event->visibleSections()),
                'locations' => $event->locations->map(fn (EventLocation $eventLocation) => [
                    'name' => $eventLocation->name,
                    'slug' => $eventLocation->slug,
                    'address' => $eventLocation->address,
                    'maps_url' => $eventLocation->maps_url,
                    'latitude' => $eventLocation->latitude,
                    'longitude' => $eventLocation->longitude,
                    'url' => $eventLocation->setRelation('event', $event)->publicUrl(),
                ]),
                'primary_color' => $event->appearance->primary_color,
                'secondary_color' => $event->appearance->secondary_color,
                'font_color_primary' => $event->appearance->font_color_primary,
                'font_color_secondary' => $event->appearance->font_color_secondary,
                'font_family' => $event->appearance->font_family,
                'cover_effect_intensity' => $event->appearance->cover_effect_intensity,
                'is_published' => $event->is_published,
                'visits_count' => $event->visits_count,
                'accepts_online_gifts' => $event->acceptsOnlineGifts(),
                'accepts_in_person_gifts' => $event->acceptsInPersonGifts(),
                'accepts_free_amount' => $event->acceptsFreeAmount(),
                'gift_display_mode' => $event->giftDisplayMode(),
                'gift_message' => $event->giftSettings->gift_message ?: EventGiftSetting::DEFAULT_GIFT_MESSAGE,
                'has_guestbook' => $event->hasFeature(Feature::Guestbook),
                'mp_public_key' => $event->acceptsOnlineGifts() ? $event->paymentSettings->mp_public_key : null,
                'rsvp_required_fields' => $event->rsvpRequiredFields(),
                'rsvp_collect_companions' => $event->collectsRsvpCompanions(),
            ],
            'is_preview' => ! $event->is_published || $event->isArchived(),
            // Where the page opens when the URL carries no #section.
            'initial_section' => $onLocationTab ? 'localizacao' : null,
            'initial_location' => $location?->slug,
            'messages' => $event->hasFeature(Feature::Guestbook)
                ? $event->messages()->approved()->latest('approved_at')->get()->map(fn (GuestMessage $message) => [
                    'id' => $message->id,
                    'author_name' => $message->author_name,
                    'message' => $message->message,
                ])
                : [],
            // Not even sent in the "no gifts" mode.
            'products' => ! $event->showsGiftItems() ? [] : $event->products()
                ->where('is_active', true)
                ->get()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image_url' => $product->imageUrl(),
                    'price' => (float) $product->price,
                    'quantity_available' => $product->quantityAvailable(),
                    'is_sold_out' => $product->isSoldOut(),
                ]),
            'guest' => $guest ? [
                'name' => $guest->name,
                'whatsapp' => $guest->whatsapp,
                'email' => $guest->email,
                'cpf' => $guest->cpf,
                'rsvp_status' => $guest->rsvp_status,
                'rsvp_guests_count' => $guest->rsvp_guests_count,
                // Gifts they reserved to hand over in person, so they can give
                // up on one from the page.
                'reservations' => $guest->orders()
                    ->where('fulfillment', Order::FULFILLMENT_IN_PERSON)
                    ->whereIn('status', [Order::STATUS_RESERVED, Order::STATUS_RECEIVED])
                    ->with('items.eventProduct')
                    ->latest('id')
                    ->get()
                    ->map(fn (Order $order) => [
                        'id' => $order->id,
                        'status' => $order->status,
                        'is_anonymous' => $order->is_anonymous,
                        'items' => $order->items->map(fn ($item) => "{$item->quantity}x {$item->eventProduct?->name}")->all(),
                    ]),
                'companions' => $guest->companions()
                    ->orderBy('id')
                    ->get(['name', 'whatsapp', 'email', 'cpf'])
                    ->map(fn (Guest $companion) => [
                        'name' => $companion->name,
                        'whatsapp' => $companion->whatsapp,
                        'email' => $companion->email,
                        'cpf' => $companion->cpf,
                    ]),
            ] : null,
        ]);
    }
}
