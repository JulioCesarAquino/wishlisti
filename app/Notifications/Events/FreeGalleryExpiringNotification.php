<?php

namespace App\Notifications\Events;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Events\Event;
use App\Models\User;
use App\Notifications\Concerns\BuildsWishlistiMail;
use App\Support\PlatformSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * To each host, some days before a free event's photos are deleted (see
 * FreeGalleryPurgeService): when, and how to keep them — the Premium.
 */
class FreeGalleryExpiringNotification extends Notification
{
    use BuildsWishlistiMail;

    public function __construct(
        public Event $event,
        public Carbon $deleteOn,
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
        $title = $this->event->title;
        $deleteOn = $this->deleteOn->copy();
        $deleteOn->setLocale('pt_BR');
        $date = $deleteOn->isoFormat('D [de] MMMM');
        $count = $this->event->photoCount();
        $price = number_format(PlatformSettings::premiumPrice(), 2, ',', '.');

        return $this->wishlistiMail($notifiable, ['mail.alert', 'mail.alert-text'], [
            'subject' => "As fotos de {$title} serão apagadas em {$date}",
            'preheader' => 'Libere o Premium para guardar as fotos para sempre.',
            'badge' => 'Fotos do evento',
            'intro' => 'No plano gratuito, as fotos ficam guardadas por '.PlatformSettings::freePhotoRetentionDays()
                ." dias depois do evento. As {$count} fotos de {$title} serão apagadas em {$date}. "
                ."Com o Premium (R$ {$price}, pagamento único), elas ficam na página para sempre. Se preferir, baixe as fotos antes dessa data.",
            'items' => [],
            'more' => 0,
            'buttonLabel' => 'Guardar as fotos com o Premium',
            'link' => EventResource::getUrl('premium', ['record' => $this->event]),
            'reason' => 'Você recebeu este e-mail porque é anfitrião deste evento em',
        ]);
    }
}
