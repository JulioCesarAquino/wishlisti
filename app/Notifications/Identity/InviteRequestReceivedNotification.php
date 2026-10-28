<?php

namespace App\Notifications\Identity;

use App\Filament\Resources\Identity\InviteRequests\InviteRequestResource;
use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Notifications\Concerns\BuildsWishlistiMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the admins, on the bell and by e-mail: someone asked for access.
 */
class InviteRequestReceivedNotification extends Notification
{
    use BuildsWishlistiMail;

    public function __construct(
        public InviteRequest $request,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return PanelNotification::make()
            ->title('Nova solicitação de convite')
            ->body("{$this->request->name} ({$this->request->email}) pediu acesso ao Wishlisti.")
            ->icon(Heroicon::OutlinedEnvelope)
            ->iconColor('info')
            ->actions([
                Action::make('review')
                    ->label('Ver solicitações')
                    ->url(InviteRequestResource::getUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return $this->wishlistiMail($notifiable, ['mail.alert', 'mail.alert-text'], [
            'subject' => "Nova solicitação de convite: {$this->request->name}",
            'preheader' => 'Alguém pediu acesso ao Wishlisti.',
            'badge' => 'Solicitação de convite',
            'intro' => 'Chegou uma nova solicitação de acesso ao Wishlisti. Ela fica aguardando até você aprovar ou recusar.',
            'items' => [[
                'heading' => $this->request->name,
                'text' => implode("\n", array_filter([$this->request->email, $this->request->whatsapp])),
            ]],
            'more' => 0,
            'buttonLabel' => 'Ver solicitações',
            'link' => InviteRequestResource::getUrl(),
            'reason' => 'Você recebeu este e-mail porque é administrador do',
        ]);
    }
}
