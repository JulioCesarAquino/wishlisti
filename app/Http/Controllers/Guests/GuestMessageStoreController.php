<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\GuestMessageStoreRequest;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Services\Guests\GuestMessageStoreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GuestMessageStoreController extends Controller
{
    public function __invoke(GuestMessageStoreRequest $request, Event $event, GuestMessageStoreService $service): RedirectResponse
    {
        abort_unless($event->isViewableBy($request->user()), 404);

        $guestIdentifier = Guest::identifierFrom($request, $event);

        $service->execute(
            event: $event,
            authorName: $request->validated('author_name'),
            message: $request->validated('message'),
            guestIdentifier: is_string($guestIdentifier) ? $guestIdentifier : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Recado enviado! Ele aparece no mural depois que os anfitriões aprovarem.',
        ]);

        return back();
    }
}
