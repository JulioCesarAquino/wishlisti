<?php

namespace App\Services\Identity;

use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Notifications\Identity\InviteRequestReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class InviteRequestStoreService
{
    /**
     * The admins are told, on the bell and by e-mail — after the response,
     * so the person asking doesn't wait for the e-mail to go, nor sees an
     * error if it doesn't.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): InviteRequest
    {
        $request = InviteRequest::create($data);

        defer(function () use ($request): void {
            try {
                Notification::send(User::where('is_admin', true)->get(), new InviteRequestReceivedNotification($request));
            } catch (Throwable $exception) {
                Log::warning('Could not tell the admins about an invite request.', [
                    'invite_request_id' => $request->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });

        return $request;
    }
}
