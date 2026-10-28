<?php

namespace App\Notifications\Identity;

use App\Models\User;
use App\Notifications\Concerns\BuildsWishlistiMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An e-mail with a link to set a password (resources/views/mail/access-link).
 */
abstract class AccessLinkNotification extends Notification
{
    use BuildsWishlistiMail;

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
        return $this->wishlistiMail($notifiable, ['mail.access-link', 'mail.access-link-text'], [
            ...$this->copy(),
            'link' => $this->link(),
        ]);
    }

    /** "3 dias", "24 horas": how long a password link lasts. */
    public static function validity(): string
    {
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);

        return $hours >= 48 ? intdiv($hours, 24).' dias' : "{$hours} horas";
    }
}
