<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\RsvpStoreRequest;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Services\Guests\RsvpStoreService;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RsvpStoreController extends Controller
{
    public function __invoke(
        RsvpStoreRequest $request,
        Event $event,
        RsvpStoreService $service,
        CookieJar $cookies,
    ): RedirectResponse {
        abort_unless($event->isViewableBy($request->user()), 404);

        $guestIdentifier = $request->cookie(Guest::cookieName($event));

        $guest = $service->execute(
            event: $event,
            guestData: $request->validated('guest'),
            attending: (bool) $request->validated('attending'),
            guestsCount: $request->validated('guests_count'),
            companions: $request->validated('companions') ?? [],
            guestIdentifier: is_string($guestIdentifier) ? $guestIdentifier : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Presença registrada! Obrigado por confirmar.',
        ]);

        return back()
            ->withCookie($cookies->make(
                Guest::cookieName($event),
                $guest->identifier,
                60 * 24 * 365 * 5,
            ));
    }
}
