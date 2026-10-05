<?php

namespace App\Services\Identity;

use App\Models\Identity\InviteRequest;
use App\Notifications\Identity\HostInviteLinkNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class InviteLinkSendService
{
    /**
     * E-mails the host their link to set a password. Sent right away (there's
     * no queue worker). False when the e-mail couldn't go out — the admin
     * then still has the link to send by hand, so it never blocks approving.
     */
    public function execute(InviteRequest $inviteRequest, string $link, bool $firstTime = true): bool
    {
        try {
            $inviteRequest->invitedUser()->firstOrFail()->notify(new HostInviteLinkNotification($link, $firstTime));

            return true;
        } catch (Throwable $exception) {
            Log::error('Convite: não foi possível enviar o e-mail com o link de acesso', [
                'invite_request_id' => $inviteRequest->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
