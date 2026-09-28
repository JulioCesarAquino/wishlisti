<?php

namespace App\Services\Identity;

use App\Models\Identity\InviteRequest;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

class InviteRequestRegenerateLinkService
{
    /**
     * A fresh "set your password" link for an approved host whose previous
     * link expired or got lost. Same kind of link as on approval: the panel's
     * own password-reset page.
     */
    public function execute(InviteRequest $inviteRequest): string
    {
        $user = $inviteRequest->invitedUser()->firstOrFail();

        return Filament::getResetPasswordUrl(
            Password::broker()->createToken($user),
            $user,
        );
    }
}
