<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Models\Events\Event;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Co-hosts: other users who run the event alongside its owner (the couple,
 * say). Everyone running it sees the list; only the owner (and the admin)
 * adds or removes people. Only existing accounts can be added — accounts
 * themselves still come from approved invites.
 */
class ManageEventCoHosts extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'coHosts';

    protected static ?string $title = 'Anfitriões';

    protected static ?string $navigationLabel = 'Anfitriões';

    protected static ?string $breadcrumb = 'Anfitriões';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /**
     * Not the User policy (listing users is admin-only): whoever runs the
     * event sees who else does.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Event && $record->isManagedBy(auth()->user());
    }

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private function canManage(): bool
    {
        return (bool) auth()->user()?->can('manageCoHosts', $this->event());
    }

    public function getSubheading(): ?string
    {
        $owner = $this->event()->user;

        return "Quem cuida deste evento com {$owner->name} ({$owner->email}), o dono. Co-anfitriões editam tudo do evento, menos esta lista, e não podem mandá-lo para a lixeira.";
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('co-anfitrião')
            ->pluralModelLabel('co-anfitriões')
            ->emptyStateHeading('Nenhum co-anfitrião')
            ->emptyStateDescription('Adicione quem divide o evento com você, como o noivo ou a noiva. A pessoa precisa já ter uma conta no Wishlisti.')
            ->columns([
                TextColumn::make('name')->label('Nome'),
                TextColumn::make('email')->label('E-mail'),
                TextColumn::make('pivot.created_at')
                    ->label('Adicionado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('addCoHost')
                    ->label('Adicionar co-anfitrião')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->modalDescription('Informe o e-mail da conta da pessoa no Wishlisti. Quem ainda não tem conta precisa pedir um convite primeiro.')
                    ->schema([
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required(),
                    ])
                    ->action(fn (array $data, Action $action) => $this->addCoHost((string) $data['email'], $action)),
            ])
            ->recordActions([
                Action::make('removeCoHost')
                    ->label('Remover')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->visible(fn (): bool => $this->canManage())
                    ->requiresConfirmation()
                    ->modalHeading('Remover co-anfitrião?')
                    ->modalDescription('A pessoa deixa de ver e editar este evento. A conta dela continua existindo.')
                    ->action(fn (User $record) => $this->removeCoHost($record)),
            ]);
    }

    protected function addCoHost(string $email, Action $action): void
    {
        abort_unless($this->canManage(), 403);

        $event = $this->event();
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        $error = match (true) {
            $user === null => 'Não encontramos uma conta com esse e-mail. A pessoa precisa ter uma conta no Wishlisti (pedindo um convite na página inicial).',
            $event->isOwnedBy($user) => 'Essa conta já é a dona do evento.',
            $event->coHosts()->whereKey($user->id)->exists() => 'Essa pessoa já é co-anfitriã do evento.',
            default => null,
        };

        if ($error) {
            // Keeps the modal open, with the e-mail, to fix it.
            Notification::make()->danger()->title($error)->send();
            $action->halt();
        }

        /** @var User $user */
        $event->coHosts()->attach($user->id);

        activity('event')
            ->performedOn($event)
            ->causedBy(auth()->user())
            ->event('co_host_added')
            ->log("adicionou {$user->name} como co-anfitrião");

        Notification::make()->success()->title("{$user->name} agora também cuida deste evento.")->send();
    }

    protected function removeCoHost(User $user): void
    {
        abort_unless($this->canManage(), 403);

        $event = $this->event();
        $event->coHosts()->detach($user->id);

        activity('event')
            ->performedOn($event)
            ->causedBy(auth()->user())
            ->event('co_host_removed')
            ->log("removeu {$user->name} dos co-anfitriões");

        Notification::make()->success()->title("{$user->name} não é mais co-anfitrião.")->send();
    }
}
