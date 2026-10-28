<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * An e-mail in the Wishlisti look (resources/views/mail/layout). Sent right
 * away, never queued: there's no queue worker.
 */
trait BuildsWishlistiMail
{
    /**
     * @param  array<int, view-string>  $views  the HTML view and the plain-text one
     * @param  array<string, mixed>  $data  what the views show; 'subject' and 'preheader' included
     */
    protected function wishlistiMail(User $notifiable, array $views, array $data): MailMessage
    {
        $message = (new MailMessage)->subject($data['subject']);

        // It's sent from a no-reply address: a reply goes to the admin.
        if ($admin = User::where('is_admin', true)->orderBy('id')->first()) {
            $message->replyTo($admin->email, config('app.name'));
        }

        return $message->view($views, [
            ...$data,
            'name' => Str::before($notifiable->name, ' '),
            'logoUrl' => asset('apple-touch-icon.png'),
            'homeUrl' => url('/'),
            'homeHost' => parse_url(url('/'), PHP_URL_HOST),
        ]);
    }
}
