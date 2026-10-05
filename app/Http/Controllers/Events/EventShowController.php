<?php

namespace App\Http\Controllers\Events;

use App\Enums\Events\PageSection;
use App\Enums\Premium\Feature;
use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventAppearance;
use App\Models\Events\EventGiftSetting;
use App\Models\Events\EventLocation;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Services\Events\EventSharePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventShowController extends Controller
{
    /**
     * Also serves the location links (see routes/web.php): the same page,
     * opened on the location tab.
     */
    public function __invoke(
        Request $request,
        Event $event,
        EventSharePreviewService $sharePreview,
        ?EventLocation $location = null,
    ): Response {
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
                // "16h", next to the date; only with a date to go with it.
                'event_time' => $event->event_date ? Event::formatTime($event->event_time) : null,
                // With the offset, so the countdown is right wherever the guest is.
                'starts_at' => $event->startsAt()?->toIso8601String(),
                'description' => $event->description,
                'cover_image_url' => $event->coverImageUrl(),
                'gallery_urls' => $event->galleryUrls(),
                'type_label' => $event->typeLabel(),
                'story_title' => $event->storyTitle(),
                'story' => $event->story,
                'url' => route('events.show', ['event' => $event->slug]),
                'sections' => array_map(fn (PageSection $section) => [
                    'key' => $section->key(),
                    'label' => $section->label(),
                ], $event->visibleSections()),
                'locations' => $event->locations->map(fn (EventLocation $eventLocation) => [
                    'name' => $eventLocation->name,
                    'start_time' => Event::formatTime($eventLocation->start_time),
                    'slug' => $eventLocation->slug,
                    'address' => $eventLocation->address,
                    'maps_url' => $eventLocation->maps_url,
                    'latitude' => $eventLocation->latitude,
                    'longitude' => $eventLocation->longitude,
                    'url' => $eventLocation->setRelation('event', $event)->publicUrl(),
                ]),
                'primary_color' => $event->appearance->primary_color,
                'secondary_color' => $event->appearance->secondary_color,
                'button_color' => $event->appearance->button_color ?: EventAppearance::DEFAULT_BUTTON_COLOR,
                'font_color_primary' => $event->appearance->font_color_primary,
                'font_color_secondary' => $event->appearance->font_color_secondary,
                'font_family' => $event->appearance->font_family,
                'cover_effect_intensity' => $event->appearance->cover_effect_intensity,
                'show_location_shortcut' => $event->appearance->show_location_shortcut,
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
                // Each field hidden, optional or required (the age too).
                'rsvp_fields' => $event->rsvpFields(),
                'rsvp_child_age_limit' => $event->childAgeLimit(),
                'rsvp_asks_children_count' => $event->asksRsvpChildrenCount(),
                'rsvp_collect_companions' => $event->collectsRsvpCompanions(),
            ],
            'is_preview' => ! $event->is_published || $event->isArchived(),
            // Where the page opens when the URL carries no #section.
            'initial_section' => $onLocationTab ? 'localizacao' : null,
            'initial_location' => $location?->slug,
            // Also after the Premium has ended: what was approved stays.
            'messages' => $event->showsSection(PageSection::Guestbook)
                ? $event->messages()->approved()->latest('approved_at')->get()->map(fn (GuestMessage $message) => [
                    'id' => $message->id,
                    // Never the real name of an anonymous author: it'd
                    // reach every visitor's browser.
                    'author_name' => $message->authorNameFor(null),
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
                'age' => $guest->age,
                'rsvp_status' => $guest->rsvp_status,
                'rsvp_guests_count' => $guest->rsvp_guests_count,
                'rsvp_children_count' => $guest->rsvp_children_count,
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
                    ->get(['name', 'whatsapp', 'email', 'cpf', 'age'])
                    ->map(fn (Guest $companion) => [
                        'name' => $companion->name,
                        'whatsapp' => $companion->whatsapp,
                        'email' => $companion->email,
                        'cpf' => $companion->cpf,
                        'age' => $companion->age,
                    ]),
            ] : null,
        ])->withViewData('share', $this->shareCard($request, $event, $location, $sharePreview));
    }

    /**
     * The card WhatsApp and other apps show for the link. They don't run
     * JavaScript, so it goes in the HTML sent by the server.
     *
     * @return array{title: string, description: string, image: ?string, url: string}
     */
    private function shareCard(Request $request, Event $event, ?EventLocation $location, EventSharePreviewService $sharePreview): array
    {
        $date = null;

        if ($event->event_date) {
            $day = Carbon::parse($event->event_date);
            $day->setLocale('pt_BR');
            $date = implode(' · ', array_filter([$day->isoFormat('D [de] MMMM [de] YYYY'), Event::formatTime($event->event_time)]));
        }
        $type = $event->type === 'outro' ? null : $event->typeLabel();

        $description = $location
            ? implode(' · ', array_filter([Event::formatTime($location->start_time), $location->address]))
            : (filled($event->description) ? $event->description : implode(' · ', array_filter([$type, $date])));

        return [
            'title' => $location ? "{$event->title} · {$location->name}" : $event->title,
            'description' => Str::limit(Str::squish((string) $description), 200),
            'image' => $sharePreview->url($event),
            'url' => $request->url(),
        ];
    }
}
