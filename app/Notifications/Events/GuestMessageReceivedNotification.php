<?php

namespace App\Notifications\Events;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Guests\GuestMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * On the bell of each host: a message waiting for their approval. Written
 * right away, not queued like Filament's own (there's no queue worker);
 * the e-mail goes once an hour, with all the new ones (see
 * GuestMessagesDigestNotification).
 */
class GuestMessageReceivedNotification extends Notification
{
    public function __construct(
        public GuestMessage $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $event = $this->message->event;

        return PanelNotification::make()
            ->title("Novo recado em {$event->title}")
            ->body($this->message->authorNameFor($notifiable).': “'.Str::limit($this->message->message, 120).'”')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->iconColor('warning')
            ->actions([
                Action::make('review')
                    ->label('Ver recados')
                    ->url(EventResource::getUrl('messages', ['record' => $event]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
