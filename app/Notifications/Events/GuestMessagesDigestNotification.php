<?php

namespace App\Notifications\Events;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use App\Models\User;
use App\Notifications\Concerns\BuildsWishlistiMail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The e-mail to each host with the event's new messages waiting for their
 * approval: one per event, at most once an hour (see
 * GuestMessagesNotifyHostsCommand), so a party day doesn't fill their inbox.
 */
class GuestMessagesDigestNotification extends Notification
{
    use BuildsWishlistiMail;

    /** Messages quoted in the e-mail; the others are counted. */
    public const SHOWN = 5;

    /**
     * @param  Collection<int, GuestMessage>  $messages
     */
    public function __construct(
        public Event $event,
        public Collection $messages,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $count = $this->messages->count();
        $title = $this->event->title;

        return $this->wishlistiMail($notifiable, ['mail.alert', 'mail.alert-text'], [
            'subject' => $count === 1 ? "Novo recado em {$title}" : "{$count} recados novos em {$title}",
            'preheader' => 'Aguardando a sua aprovação para aparecer no mural.',
            'badge' => $count === 1 ? 'Recado novo' : 'Recados novos',
            'intro' => ($count === 1 ? 'Chegou um recado' : "Chegaram {$count} recados")
                ." no mural de {$title}. Eles só aparecem na página depois que você ou outro anfitrião aprovar.",
            'items' => $this->messages->take(self::SHOWN)->map(fn (GuestMessage $message): array => [
                'heading' => $message->authorNameFor($notifiable),
                'text' => Str::limit($message->message, 300),
            ])->values()->all(),
            'more' => max(0, $count - self::SHOWN),
            'buttonLabel' => 'Ver e aprovar',
            'link' => EventResource::getUrl('messages', ['record' => $this->event]),
            'reason' => 'Você recebeu este e-mail porque é anfitrião deste evento em',
        ]);
    }
}
