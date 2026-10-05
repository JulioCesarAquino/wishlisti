<?php

namespace App\Filament\Resources\Identity\InviteRequests\Tables;

use App\Models\Identity\InviteRequest;
use App\Services\Identity\InviteLinkSendService;
use App\Services\Identity\InviteRequestApproveService;
use App\Services\Identity\InviteRequestRegenerateLinkService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Js;

class InviteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        InviteRequest::STATUS_PENDING => 'Pendente',
                        InviteRequest::STATUS_APPROVED => 'Aprovado',
                        InviteRequest::STATUS_REJECTED => 'Rejeitado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        InviteRequest::STATUS_PENDING => 'warning',
                        InviteRequest::STATUS_APPROVED => 'success',
                        InviteRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Solicitado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        InviteRequest::STATUS_PENDING => 'Pendente',
                        InviteRequest::STATUS_APPROVED => 'Aprovado',
                        InviteRequest::STATUS_REJECTED => 'Rejeitado',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (InviteRequest $record): bool => $record->status === InviteRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription('Isso cria a conta do anfitrião e envia por e-mail um link para ele definir a senha.')
                    ->action(function (InviteRequest $record, InviteRequestApproveService $service, InviteLinkSendService $sender): void {
                        $link = $service->execute($record);

                        self::notifyLinkSent($record, $link, $sender->execute($record, $link), 'Convite aprovado');
                    }),
                Action::make('regenerateLink')
                    ->label('Gerar novo link')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (InviteRequest $record): bool => $record->status === InviteRequest::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->modalDescription('Gera um novo link para o anfitrião definir a senha e envia por e-mail. O link anterior deixa de valer.')
                    ->action(function (InviteRequest $record, InviteRequestRegenerateLinkService $service, InviteLinkSendService $sender): void {
                        $link = $service->execute($record);

                        self::notifyLinkSent($record, $link, $sender->execute($record, $link, firstTime: false), 'Novo link gerado');
                    }),
                Action::make('reject')
                    ->label('Rejeitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (InviteRequest $record): bool => $record->status === InviteRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (InviteRequest $record) => $record->forceFill(['status' => InviteRequest::STATUS_REJECTED])->save()),
            ]);
    }

    /**
     * Tells the admin the link went by e-mail — or, if it couldn't, to send
     * it by hand. The link can be copied either way.
     */
    private static function notifyLinkSent(InviteRequest $record, string $link, bool $emailed, string $title): void
    {
        $notification = Notification::make()
            ->title($title)
            ->persistent()
            ->actions([
                Action::make('copy')
                    ->label('Copiar link')
                    ->button()
                    ->color('gray')
                    ->alpineClickHandler('window.navigator.clipboard.writeText('.Js::from($link).')'),
            ]);

        $emailed
            ? $notification->success()->body("Enviamos o link por e-mail para {$record->email}. Se precisar, copie e envie também por WhatsApp.")
            : $notification->warning()->body("Não foi possível enviar o e-mail para {$record->email}. Copie o link e envie para {$record->name} por WhatsApp: {$link}");

        $notification->send();
    }
}
