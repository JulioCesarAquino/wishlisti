<?php

namespace App\Http\Controllers\Identity;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\CreateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\HostSignUpRequest;
use App\Services\Identity\HostSignUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HostSignUpController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect('/admin');
        }

        return Inertia::render('cadastro');
    }

    /**
     * Signs the new host in and takes them to create their first event,
     * in the panel (outside Inertia: a full page load).
     */
    public function store(HostSignUpRequest $request, HostSignUpService $service): SymfonyResponse
    {
        $user = $service->execute($request->validated());

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        // The panel's next page reports the conversion (partials.meta-pixel).
        $request->session()->flash('signed_up', true);

        // Came for the Premium: once the event exists, its payment opens.
        if ($request->validated('plan') === 'premium') {
            $request->session()->put(CreateEvent::PREMIUM_NEXT, true);
        }

        return Inertia::location(EventResource::getUrl('create'));
    }
}
