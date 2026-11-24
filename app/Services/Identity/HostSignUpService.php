<?php

namespace App\Services\Identity;

use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Notifications\Identity\InviteRequestReceivedNotification;
use App\Support\Identity\Attribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * A host signing up on their own, from the home page. The sign-up is kept
 * with the invite requests, already approved, so the admin sees who came
 * in, from which event page or ad campaign, and their WhatsApp.
 */
class HostSignUpService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $signUp = new InviteRequest([
                'name' => $data['name'],
                'email' => $data['email'],
                'whatsapp' => $data['whatsapp'],
            ]);
            $signUp->forceFill([
                ...Attribution::columns($data),
                'status' => InviteRequest::STATUS_APPROVED,
                'via_signup' => true,
                'invited_user_id' => $user->id,
                'approved_at' => now(),
            ])->save();

            return $user->setRelation('signUp', $signUp);
        });

        $signUp = $user->getRelation('signUp');
        $user->unsetRelation('signUp');

        // After the response: the new host doesn't wait for the e-mail.
        defer(function () use ($signUp): void {
            try {
                Notification::send(User::where('is_admin', true)->get(), new InviteRequestReceivedNotification($signUp));
            } catch (Throwable $exception) {
                Log::warning('Could not tell the admins about a sign-up.', [
                    'invite_request_id' => $signUp->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });

        return $user;
    }
}
