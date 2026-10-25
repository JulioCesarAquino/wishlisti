<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Support\PremiumLock;
use App\Filament\Support\SafeDeleteBulkAction;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Rules\Guests\Cpf;
use App\Services\Guests\GuestAnonymizeService;
use App\Services\Guests\GuestListPdfService;
use App\Services\Guests\GuestManageService;
use App\Services\Guests\GuestMergeService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageEventGuests extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'guests';

    protected static ?string $title = 'Convidados';

    protected static ?string $navigationLabel = 'Convidados';

    protected static ?string $breadcrumb = 'Convidados';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /**
     * The host adding someone by hand (a pre-list of who's invited, without
     * an answer until the guest replies or the host marks it) or fixing a
     * guest's details.
     */
    public function form(Schema $schema): Schema
    {
        $isCompanion = fn (?Guest $record): bool => $record?->companion_of_guest_id !== null;
        $confirmed = fn (Get $get, ?Guest $record): bool => ! $isCompanion($record) && $get('rsvp_status') === Guest::RSVP_CONFIRMED;

        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('whatsapp')
                ->label('WhatsApp')
                ->tel()
                ->maxLength(30),
            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255),
            TextInput::make('cpf')
                ->label('CPF')
                ->rule(new Cpf),
            TextInput::make('age')
                ->label('Idade')
                ->integer()
                ->minValue(0)
                ->maxValue(120)
                ->suffix('anos'),
            Select::make('rsvp_status')
                ->label('Presença')
                ->placeholder('Sem resposta')
                ->options([
                    Guest::RSVP_CONFIRMED => 'Confirmada',
                    Guest::RSVP_DECLINED => 'Não vai',
                ])
                ->live()
                ->hidden($isCompanion)
                ->helperText('Fica "Sem resposta" até o convidado responder ou você marcar.'),
            TextInput::make('rsvp_guests_count')
                ->label('Pessoas no grupo')
                ->helperText('Contando com o convidado.')
                ->integer()
                ->default(1)
                ->minValue(fn (?Guest $record): int => 1 + ($record?->companions()->count() ?? 0))
                ->maxValue(Guest::MAX_PARTY_SIZE)
                ->required($confirmed)
                ->visible($confirmed),
            TextInput::make('rsvp_children_count')
                ->label(fn (): string => 'Crianças com menos de '.$this->childAgeLimit().' anos')
                ->helperText('Quantas pessoas do grupo.')
                ->integer()
                ->minValue(0)
                ->lte('rsvp_guests_count')
                ->visible(fn (Get $get, ?Guest $record): bool => $confirmed($get, $record) && $this->event()->asksRsvpChildrenCount()),
            TextInput::make('max_companions')
                ->label('Pode levar até')
                ->suffix('acompanhantes')
                ->integer()
                ->minValue(0)
                ->maxValue(Guest::MAX_PARTY_SIZE - 1)
                ->placeholder(fn (): string => 'Padrão do evento: '.$this->event()->rsvpCompanionLimit())
                ->helperText('Para quem vai levar a família toda, ou para um convite só da pessoa (0). Em branco, vale o padrão do evento.')
                ->hidden($isCompanion),
        ])->columns(2);
    }

    /**
     * Who's coming, at a glance — split into who pays and the children who
     * don't, when the event has an age limit.
     */
    public function getSubheading(): ?string
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();
        $headcount = $event->rsvpHeadcount();
        $people = $headcount['people'] === 1 ? '1 pessoa confirmada' : "{$headcount['people']} pessoas confirmadas";
        $capacity = $event->rsvpCapacity();
        $places = $capacity === null ? null : "{$event->rsvpTakenPlaces()} de {$capacity} vagas ocupadas";

        if ($headcount['children'] === null) {
            return implode(' · ', array_filter([$people, $places]));
        }

        $children = ($headcount['children'] === 1 ? '1 criança' : "{$headcount['children']} crianças")." com menos de {$event->childAgeLimit()} anos";

        return implode(' · ', array_filter([$people, $children, $places]));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('convidado')
            ->pluralModelLabel('convidados')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with('companionOf')
                ->when(! auth()->user()?->isAdmin(), fn (Builder $query) => $query->scopes('visibleToHost'))
                // Anonymous gifts don't count: the number would give them away.
                ->withCount([
                    'orders as paid_orders_count' => fn (Builder $ordersQuery) => $ordersQuery
                        ->where('status', Order::STATUS_PAID)
                        ->where('is_anonymous', false),
                ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Idade')
                    ->placeholder('—')
                    ->numeric()
                    ->sortable()
                    ->badge(fn (Guest $record): bool => $this->isChild($record))
                    ->color(fn (Guest $record): ?string => $this->isChild($record) ? 'warning' : null)
                    ->description(fn (Guest $record): ?string => $this->isChild($record) ? 'Criança' : null)
                    ->toggleable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('paid_orders_count')
                    ->label('Presentes dados')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rsvp_status')
                    ->label('Presença')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                        default => 'Sem resposta',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Guest::RSVP_CONFIRMED => 'success',
                        Guest::RSVP_DECLINED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('companionOf.name')
                    ->label('Acompanhante de')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rsvp_guests_count')
                    ->label('Pessoas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_companions')
                    ->label('Pode levar')
                    ->tooltip('Quantos acompanhantes o convidado pode levar ao confirmar')
                    ->placeholder(fn (): string => 'Padrão ('.$this->event()->rsvpCompanionLimit().')')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('rsvp_children_count')
                    ->label('Crianças')
                    ->tooltip(fn (): ?string => ($limit = $this->childAgeLimit()) ? "Quantas pessoas do grupo têm menos de {$limit} anos" : null)
                    ->placeholder('—')
                    ->numeric()
                    ->visible(fn (): bool => $this->childAgeLimit() !== null)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Chegou em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label('Lixeira'),
                Filter::make('children')
                    ->label(fn (): string => 'Crianças com menos de '.$this->childAgeLimit().' anos')
                    ->visible(fn (): bool => $this->childAgeLimit() !== null)
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->where('age', '<', $this->childAgeLimit())
                        ->orWhere('rsvp_children_count', '>', 0))),
                SelectFilter::make('rsvp_status')
                    ->label('Presença')
                    ->options([
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                    ]),
            ])
            ->headerActions([
                Action::make('pdf')
                    ->label('Lista em PDF')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->modalHeading('Lista de convidados em PDF')
                    ->modalDescription('Para imprimir: cada convidado numa linha, com um quadrinho marcado para quem confirmou e vazio para quem ainda não respondeu.')
                    ->modalSubmitActionLabel('Baixar PDF')
                    ->schema([
                        Toggle::make('include_declined')
                            ->label('Incluir quem disse que não vai'),
                        Toggle::make('show_whatsapp')
                            ->label('Mostrar o WhatsApp'),
                    ])
                    ->action(function (array $data, GuestListPdfService $service): StreamedResponse {
                        $event = $this->event();
                        $pdf = $service->render(
                            $event,
                            includeDeclined: (bool) $data['include_declined'],
                            showWhatsapp: (bool) $data['show_whatsapp'],
                            hostView: ! auth()->user()?->isAdmin(),
                        );

                        return response()->streamDownload(fn () => print $pdf, $service->filename($event), ['Content-Type' => 'application/pdf']);
                    }),
                CreateAction::make()
                    ->label('Adicionar convidado')
                    ->modalHeading('Adicionar convidado')
                    ->using(fn (array $data, GuestManageService $service): Guest => $service->save($this->event(), $data)),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->tooltip('Marcar a presença como confirmada')
                    ->visible(fn (Guest $record): bool => $this->canBeConfirmed($record))
                    ->action(function (Guest $record, GuestManageService $service): void {
                        $service->confirm($record);

                        Notification::make()->success()->title("Presença de {$record->name} confirmada")->send();
                    }),
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (Guest $record): bool => ! $record->trashed())
                        ->using(fn (Guest $record, array $data, GuestManageService $service): Guest => $service->save($this->event(), $data, $record)),
                    Action::make('invite')
                        ->label('Enviar convite pelo WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->visible(fn (Guest $record): bool => ! $record->trashed() && $record->name !== Guest::ANONYMIZED_NAME)
                        ->url(fn (Guest $record): string => $this->event()->whatsappShareUrl($record))
                        ->openUrlInNewTab(),
                    Action::make('invite_link')
                        ->label('Link do convite')
                        ->icon(Heroicon::OutlinedLink)
                        ->visible(fn (Guest $record): bool => ! $record->trashed() && $record->name !== Guest::ANONYMIZED_NAME)
                        ->modalHeading(fn (Guest $record): string => "Link do convite de {$record->name}")
                        ->modalDescription('Para mandar por outro aplicativo: quem abre este link já é reconhecido na página e vê quantos acompanhantes pode levar. Não compartilhe com outras pessoas.')
                        ->fillForm(fn (Guest $record): array => ['url' => $record->setRelation('event', $this->event())->inviteUrl()])
                        ->schema([
                            TextInput::make('url')
                                ->hiddenLabel()
                                ->readOnly()
                                ->copyable(copyMessage: 'Link copiado'),
                        ])
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar'),
                    $this->requiresGuestList(Action::make('companions')
                        ->label('Acompanhantes')
                        ->icon(Heroicon::OutlinedUsers)
                        ->visible(fn (Guest $record): bool => ! $record->trashed()
                            && $record->companion_of_guest_id === null
                            && $record->rsvp_status === Guest::RSVP_CONFIRMED
                            && $record->rsvp_guests_count > 1)
                        ->modalHeading(fn (Guest $record): string => "Acompanhantes de {$record->name}")
                        ->modalDescription(fn (Guest $record): string => "O grupo tem {$record->rsvp_guests_count} pessoas, contando com {$record->name}. Preencha quem você souber; quem ficar de fora continua contando, sem nome.")
                        ->modalSubmitActionLabel('Salvar')
                        ->fillForm(fn (Guest $record): array => [
                            'companions' => $record->companions()->orderBy('id')->get()
                                ->map(fn (Guest $companion): array => $companion->only(['id', 'name', 'age', 'whatsapp', 'email', 'cpf']))
                                ->all(),
                        ])
                        ->schema(fn (Guest $record): array => [
                            Repeater::make('companions')
                                ->hiddenLabel()
                                ->schema([
                                    Hidden::make('id'),
                                    TextInput::make('name')
                                        ->label('Nome')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('age')
                                        ->label('Idade')
                                        ->integer()
                                        ->minValue(0)
                                        ->maxValue(120)
                                        ->suffix('anos'),
                                    TextInput::make('whatsapp')
                                        ->label('WhatsApp')
                                        ->tel()
                                        ->maxLength(30),
                                    TextInput::make('email')
                                        ->label('E-mail')
                                        ->email()
                                        ->maxLength(255),
                                    TextInput::make('cpf')
                                        ->label('CPF')
                                        ->rule(new Cpf),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->maxItems($record->rsvp_guests_count - 1)
                                ->addActionLabel('Adicionar acompanhante'),
                        ])
                        ->action(function (Guest $record, array $data, GuestManageService $service): void {
                            $service->saveCompanions($record, array_values($data['companions'] ?? []));

                            Notification::make()->success()->title('Acompanhantes salvos')->send();
                        })),
                    $this->requiresGuestList(Action::make('merge')
                        ->label('Unificar com…')
                        ->icon(Heroicon::OutlinedArrowsPointingIn)
                        ->visible(fn (Guest $record): bool => ! $record->trashed())
                        ->modalHeading(fn (Guest $record): string => "Unificar {$record->name}")
                        ->modalDescription('Para quando a mesma pessoa aparece duas vezes, como o filho que confirmou sozinho e também foi listado pelo pai. Fica a confirmação feita pela própria pessoa, o que faltar num registro (WhatsApp, e-mail, CPF, idade) vem do outro, e os presentes e recados passam para o registro que fica. Quem tinha listado a pessoa passa a contar uma a menos.')
                        ->modalSubmitActionLabel('Unificar')
                        ->schema([
                            Select::make('other_guest_id')
                                ->label('É a mesma pessoa que')
                                ->options(fn (Guest $record): array => $this->mergeCandidates($record))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Guest $record, array $data, GuestMergeService $service): void {
                            $other = $this->event()->guests()->whereKey($data['other_guest_id'])->firstOrFail();
                            $kept = $service->execute($record, $other);

                            Notification::make()
                                ->success()
                                ->title('Registros unificados')
                                ->body("Ficou o registro de {$kept->name}.")
                                ->send();
                        })),
                    DeleteAction::make()
                        ->label('Mover para a lixeira')
                        ->modalDescription('O convidado fica 30 dias na lixeira e pode ser restaurado nesse período. Os acompanhantes dele vão junto.')
                        // Only identified gifts block it: refusing over an
                        // anonymous one would give the giver away.
                        ->before(function (DeleteAction $action, Guest $record): void {
                            if ($record->hasIdentifiedPaidOrders()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Este convidado não pode ser excluído')
                                    ->body('Ele tem um presente pago registrado, que faz parte do histórico financeiro do evento. Se ele pediu para remover os dados pessoais, use "Anonimizar".')
                                    ->persistent()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                    Action::make('anonymize')
                        ->label('Anonimizar')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Anonimizar convidado')
                        ->modalDescription('Apaga nome, telefone, e-mail e CPF deste convidado, mantendo a confirmação de presença e os presentes dados no histórico. Use quando o convidado pedir a remoção dos dados pessoais (LGPD). Não pode ser desfeito.')
                        ->visible(fn (Guest $record): bool => ! $record->trashed() && $record->name !== Guest::ANONYMIZED_NAME)
                        ->action(function (Guest $record, GuestAnonymizeService $service): void {
                            $service->execute($record);

                            Notification::make()->success()->title('Dados do convidado anonimizados')->send();
                        }),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirm')
                        ->label('Confirmar presença')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, GuestManageService $service): void {
                            foreach ($records as $guest) {
                                if ($guest instanceof Guest && $this->canBeConfirmed($guest)) {
                                    $service->confirm($guest);
                                }
                            }

                            Notification::make()->success()->title('Presenças confirmadas')->send();
                        }),
                    SafeDeleteBulkAction::make(
                        fn (Guest $guest): bool => $guest->hasIdentifiedPaidOrders(),
                        'Convidados com presentes pagos fazem parte do histórico financeiro e não podem ser excluídos. Use "Anonimizar" se eles pediram a remoção dos dados.',
                    ),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private function childAgeLimit(): ?int
    {
        return $this->event()->childAgeLimit();
    }

    /**
     * A guest list premium action: hosts without the feature still find it,
     * and it tells them what it is and how to get it. The admin can always
     * use it.
     */
    private function requiresGuestList(Action $action): Action
    {
        $event = $this->event();

        if (auth()->user()?->isAdmin() || $event->hasFeature(Feature::GuestList)) {
            return $action;
        }

        return $action
            ->icon(Heroicon::OutlinedLockClosed)
            ->modalHeading(Feature::GuestList->label())
            ->modalDescription(null)
            ->fillForm([])
            ->schema([PremiumLock::callout(Feature::GuestList, $event)])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->action(fn () => abort(403));
    }

    /** Companions follow whoever listed them. */
    private function canBeConfirmed(Guest $guest): bool
    {
        return ! $guest->trashed()
            && $guest->companion_of_guest_id === null
            && $guest->rsvp_status !== Guest::RSVP_CONFIRMED;
    }

    /**
     * The other guests of the event, each told apart by how they're on the
     * list ("confirmou", "acompanhante de Fulano"…) and a contact, so two
     * people with the same name aren't mixed up.
     *
     * @return array<int, string>
     */
    private function mergeCandidates(Guest $guest): array
    {
        return $this->event()->guests()
            ->with('companionOf')
            ->whereKeyNot($guest->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('companion_of_guest_id')
                ->orWhere('companion_of_guest_id', '!=', $guest->id))
            ->whereKeyNot($guest->companion_of_guest_id ?? 0)
            ->when(! auth()->user()?->isAdmin(), fn (Builder $query) => $query->scopes('visibleToHost'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Guest $other): array => [$other->id => implode(' · ', array_filter([
                $other->name,
                match (true) {
                    $other->companion_of_guest_id !== null => 'acompanhante de '.$other->companionOf?->name,
                    $other->rsvp_status === Guest::RSVP_CONFIRMED => 'confirmou',
                    $other->rsvp_status === Guest::RSVP_DECLINED => 'não vai',
                    default => 'sem resposta',
                },
                $other->whatsapp ?? $other->email,
            ]))])
            ->all();
    }

    private function isChild(Guest $guest): bool
    {
        $limit = $this->childAgeLimit();

        return $limit !== null && $guest->age !== null && $guest->age < $limit;
    }
}
