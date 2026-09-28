<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Support\SafeDeleteBulkAction;
use App\Models\Catalog\EventProduct;
use App\Models\Catalog\ProductTemplate;
use App\Models\Events\Event;
use App\Models\Events\EventGiftSetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManageEventProducts extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'products';

    protected static ?string $title = 'Presentes';

    protected static ?string $navigationLabel = 'Presentes';

    protected static ?string $breadcrumb = 'Presentes';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    /**
     * Without the "full gift list" premium feature: catalog items only, one
     * unit each, up to the free limit. What an event already has is kept —
     * only adding (or raising) is blocked.
     */
    private function isFree(): bool
    {
        return ! $this->event()->hasFeature(Feature::FullGiftList);
    }

    /**
     * On a free event, a catalog item keeps the catalog's name, description
     * and image. A custom item created before the limits existed can still
     * be edited as it was.
     */
    private function locksToCatalog(string $operation, ?EventProduct $record): bool
    {
        return $this->isFree() && ($operation === 'create' || filled($record?->product_template_id));
    }

    /**
     * Server-side side of locksToCatalog(): the locked fields are always
     * taken from the catalog, whatever the browser sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enforceCatalog(array $data, string $operation, ?EventProduct $record = null): array
    {
        if (! $this->locksToCatalog($operation, $record)) {
            return $data;
        }

        /** @var ProductTemplate|null $template */
        $template = ProductTemplate::find($data['product_template_id'] ?? $record?->product_template_id);

        if (! $template) {
            return $data;
        }

        return [
            ...$data,
            'product_template_id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'image' => $template->image,
        ];
    }

    public function getSubheading(): ?string
    {
        if (! $this->isFree()) {
            return null;
        }

        return "Plano gratuito: {$this->event()->products()->count()} de {$this->event()->giftLimit()} presentes, só itens do catálogo e uma unidade de cada. "
            .'Presentes personalizados, cotas e lista sem limite fazem parte do Premium.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewPublicPageAction(),
            Action::make('displaySettings')
                ->label('Exibição na página')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Como os presentes aparecem na página')
                ->modalDescription('Para quem prefere deixar os convidados à vontade: a lista pode ficar discreta, ou nem aparecer.')
                ->fillForm(fn (): array => [
                    'display_mode' => $this->event()->giftDisplayMode(),
                    'gift_message' => $this->event()->giftSettings->gift_message ?: EventGiftSetting::DEFAULT_GIFT_MESSAGE,
                ])
                ->schema([
                    Radio::make('display_mode')
                        ->label('Exibição')
                        ->options([
                            EventGiftSetting::DISPLAY_LIST => 'Lista de presentes',
                            EventGiftSetting::DISPLAY_DISCREET => 'Discreto — "Se quiser presentear"',
                            EventGiftSetting::DISPLAY_NONE => 'Sem presentes',
                        ])
                        ->descriptions([
                            EventGiftSetting::DISPLAY_LIST => 'A lista tem seu próprio item no menu da página.',
                            EventGiftSetting::DISPLAY_DISCREET => 'Fora do menu: fica recolhida no fim da página inicial, sem "esgotado", contadores ou preços em destaque.',
                            EventGiftSetting::DISPLAY_NONE => 'Nenhuma lista, só a sua mensagem. Se você aceita valor livre (Premium), a opção de contribuir continua aparecendo.',
                        ])
                        ->live()
                        ->required(),
                    Textarea::make('gift_message')
                        ->label('Mensagem para os convidados')
                        ->rows(2)
                        ->maxLength(300)
                        ->visible(fn (Get $get): bool => $get('display_mode') === EventGiftSetting::DISPLAY_NONE)
                        ->required(fn (Get $get): bool => $get('display_mode') === EventGiftSetting::DISPLAY_NONE),
                ])
                ->action(function (array $data): void {
                    $this->event()->giftSettings()->updateOrCreate([], [
                        'display_mode' => $data['display_mode'],
                        'gift_message' => $data['gift_message'] ?? null,
                    ]);

                    Notification::make()->success()->title('Exibição dos presentes atualizada')->send();
                }),
            Action::make('discoverPremium')
                ->label('Conhecer o Premium')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('warning')
                ->visible(fn (): bool => $this->isFree())
                ->url(fn (): string => PurchaseEventPremium::getUrl(['record' => $this->event()])),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_template_id')
                    ->label(fn (): string => $this->isFree() ? 'Item do catálogo' : 'Item do catálogo (opcional)')
                    ->relationship('productTemplate', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(fn (string $operation, ?EventProduct $record): bool => $this->locksToCatalog($operation, $record))
                    ->helperText(fn (): string => $this->isFree()
                        ? 'No plano gratuito, os presentes vêm do catálogo. Presentes personalizados fazem parte do Premium.'
                        : 'Selecione para preencher automaticamente, ou deixe em branco para criar um item personalizado.')
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $template = ProductTemplate::find($state);

                        if (! $template) {
                            return;
                        }

                        $set('name', $template->name);
                        $set('description', $template->description);
                        $set('image', $template->image);
                        $set('price', $template->suggested_price);
                    }),
                TextInput::make('name')
                    ->label('Nome do item')
                    ->placeholder('Ex: Uma cadeira para o Arthur descansar')
                    ->disabled(fn (string $operation, ?EventProduct $record): bool => $this->locksToCatalog($operation, $record))
                    ->dehydrated()
                    ->required(),
                Textarea::make('description')
                    ->label('Descrição')
                    ->disabled(fn (string $operation, ?EventProduct $record): bool => $this->locksToCatalog($operation, $record))
                    ->dehydrated()
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Imagem')
                    ->image()
                    ->disk('public')
                    ->imageEditor()
                    ->imagePreviewHeight('160')
                    ->directory('event-products')
                    ->disabled(fn (string $operation, ?EventProduct $record): bool => $this->locksToCatalog($operation, $record))
                    ->dehydrated()
                    ->helperText('Opcional. Se não enviar, uma imagem genérica será usada.'),
                TextInput::make('price')
                    ->label('Preço')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                TextInput::make('quantity_total')
                    ->label('Quantidade disponível')
                    ->helperText(fn (): string => $this->isFree()
                        ? 'No plano gratuito, cada presente tem uma unidade. Cotas (várias unidades) fazem parte do Premium.'
                        : 'Use 1 para um item único, ou um número maior para permitir várias contribuições (ex: cotas).')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    // A quota created before the limits existed keeps its
                    // quantity (it can go down, not up).
                    ->maxValue(fn (?EventProduct $record): ?int => $this->isFree() ? max(1, $record->quantity_total ?? 1) : null)
                    ->default(1),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('presente')
            ->pluralModelLabel('presentes')
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->columns([
                ImageColumn::make('image')
                    ->label(''),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('quantity_total')
                    ->label('Disponível')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity_purchased')
                    ->label('Presenteado')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label('Lixeira'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar presente')
                    ->disabled(fn (): bool => ! $this->event()->canAddGifts())
                    ->tooltip(fn (): ?string => $this->event()->canAddGifts()
                        ? null
                        : "O plano gratuito permite até {$this->event()->giftLimit()} presentes. Contrate o Premium para ter uma lista sem limite.")
                    ->before(function (CreateAction $action): void {
                        if (! $this->event()->canAddGifts()) {
                            $this->notifyGiftLimit();
                            $action->halt();
                        }
                    })
                    ->mutateFormDataUsing(fn (array $data): array => $this->enforceCatalog($data, 'create')),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data, EventProduct $record): array => $this->enforceCatalog($data, 'edit', $record)),
                DeleteAction::make()
                    ->label('Mover para a lixeira')
                    ->modalDescription('O presente fica 30 dias na lixeira e pode ser restaurado nesse período.')
                    ->disabled(fn (EventProduct $record): bool => $record->hasOrders())
                    ->tooltip(fn (EventProduct $record): ?string => $record->hasOrders()
                        ? 'Este presente já está em pedidos e não pode ser excluído. Para tirá-lo da página, desmarque "Ativo".'
                        : null),
                RestoreAction::make()
                    ->before(function (RestoreAction $action): void {
                        if (! $this->event()->canAddGifts()) {
                            $this->notifyGiftLimit();
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SafeDeleteBulkAction::make(
                        fn (EventProduct $product): bool => $product->hasOrders(),
                        'Presentes que já estão em pedidos fazem parte do histórico financeiro. Para tirá-los da página, desmarque "Ativo".',
                    ),
                    RestoreBulkAction::make()
                        ->before(function (RestoreBulkAction $action, Collection $records): void {
                            if (! $this->event()->canAddGifts($records->count())) {
                                $this->notifyGiftLimit();
                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    private function notifyGiftLimit(): void
    {
        Notification::make()
            ->warning()
            ->title("Limite de {$this->event()->giftLimit()} presentes do plano gratuito")
            ->body('Remova um presente ou contrate o Premium para ter uma lista sem limite.')
            ->actions([
                Action::make('discoverPremium')
                    ->label('Conhecer o Premium')
                    ->url(PurchaseEventPremium::getUrl(['record' => $this->event()])),
            ])
            ->persistent()
            ->send();
    }
}
