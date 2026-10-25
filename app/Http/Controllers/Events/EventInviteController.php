<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A guest's personal invitation link: the browser starts holding who they
 * are, and the page opens with the form ready for them. An unknown code
 * (a guest removed from the list) just opens the page.
 */
class EventInviteController extends Controller
{
    public function __invoke(Request $request, Event $event, string $code, CookieJar $cookies): RedirectResponse
    {
        abort_unless($event->isViewableBy($request->user()), 404);

        $redirect = redirect()->route('events.show', ['event' => $event->slug]);
        $guest = $event->guests()->where('invite_code', $code)->first();

        if (! $guest) {
            return $redirect;
        }

        $minutes = 60 * 24 * 365 * 5;

        return $redirect
            ->withCookie($cookies->make(Guest::cookieName($event), $guest->identifier, $minutes))
            ->withCookie($cookies->make(Guest::inviteCookieName($event), $guest->invite_code, $minutes));
    }
}
