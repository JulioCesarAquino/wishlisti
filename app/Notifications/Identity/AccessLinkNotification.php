<?php

namespace App\Notifications\Identity;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An e-mail with a link to set a password, in the Wishlisti look
 * (resources/views/mail/access-link). Sent right away, never queued: there's
 * no queue worker.
 */
abstract class AccessLinkNotification extends Notification
{
    abstract protected function link(): string;

    /**
     * The words of this e-mail: subject, preheader, badge, intro, button,
     * note (under the button) and reason (in the footer).
     *
     * @return array{subject: string, preheader: string, badge: string, intro: string, buttonLabel: string, note: string, reason: string}
     */
    abstract protected function copy(): array;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $copy = $this->copy();
        $message = (new MailMessage)->subject($copy['subject']);

        // It's sent from a no-reply address: a reply goes to the admin.
        if ($admin = User::where('is_admin', true)->orderBy('id')->first()) {
            $message->replyTo($admin->email, config('app.name'));
        }

        return $message->view(['mail.access-link', 'mail.access-link-text'], [
            ...$copy,
            'name' => Str::before($notifiable->name, ' '),
            'link' => $this->link(),
            'logoUrl' => asset('apple-touch-icon.png'),
            'homeUrl' => url('/'),
            'homeHost' => parse_url(url('/'), PHP_URL_HOST),
        ]);
    }

    /** "3 dias", "24 horas": how long a password link lasts. */
    public static function validity(): string
    {
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);

        return $hours >= 48 ? intdiv($hours, 24).' dias' : "{$hours} horas";
    }
}
