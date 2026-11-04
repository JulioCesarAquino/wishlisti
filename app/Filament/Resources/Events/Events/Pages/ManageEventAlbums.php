<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use App\Services\Events\EventGalleryService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * The gallery's photos. Free events have a single album, shown on the page
 * as a plain gallery, up to the free photo limit (Configurações). With the
 * albums feature, as many albums and photos as the host wants, each shown
 * as a fanned card. The limits are also enforced by EventAlbum itself.
 */
class ManageEventAlbums extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'albums';

    protected static ?string $title = 'Fotos';

    protected static ?string $navigationLabel = 'Fotos';

    protected static ?string $breadcrumb = 'Fotos';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private function isFree(): bool
    {
        return ! $this->event()->hasFeature(Feature::Albums);
    }

    public function getSubheading(): ?string
    {
        if (! $this->isFree()) {
            return 'Com mais de um álbum, cada um aparece na página como um cartão com a capa na frente. Com um só, as fotos aparecem direto. Arraste as linhas para mudar a ordem.';
        }

        $count = $this->event()->photoCount();
        $limit = $this->event()->photoLimit();

        // Albums made while the event was premium stay on the page.
        $albums = $this->event()->albums()->count() > 1
            ? ' Os álbuns que você já criou continuam na página, mas novos álbuns precisam do Premium.'
            : '';

        return "Plano gratuito: {$count} de {$limit} fotos, numa galeria só.{$albums} Álbuns e fotos sem limite fazem parte do Premium.";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->shareOnWhatsappAction(),
            $this->viewPublicPageAction(),
            Action::make('discoverPremium')
                ->label('Conhecer o Premium')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('warning')
                ->visible(fn (): bool => $this->isFree())
                ->url(fn (): string => PurchaseEventPremium::getUrl(['record' => $this->event()])),
        ];
    }

    /**
     * How many files the upload takes: the free limit, minus the other
     * albums' photos — but never less than the album already has, so an
     * event over the limit can still edit (and trim) what it had.
     */
    private function maxFiles(?EventAlbum $record): ?int
    {
        $limit = $this->event()->photoLimit();

        if ($limit === null) {
            return null;
        }

        $own = count($record?->photoPaths() ?? []);

        return max($own, $limit - $this->event()->photoCount(except: $record));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Nome do álbum')
                    ->helperText('Aparece na página do evento, embaixo do cartão do álbum.')
                    ->placeholder('Ex: Cerimônia, Festa, Making of')
                    ->default(EventAlbum::DEFAULT_NAME)
                    ->required()
                    ->maxLength(100),
                FileUpload::make('photos')
                    ->label('Fotos')
                    ->helperText(fn (?EventAlbum $record): string => $this->isFree()
                        ? "Arraste para mudar a ordem. No plano gratuito, até {$this->event()->photoLimit()} fotos no evento."
                        : 'Arraste para mudar a ordem. A primeira foto é a capa, a não ser que você escolha outra abaixo.')
                    ->image()
                    ->disk('public')
                    ->directory('events/gallery')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('120')
                    ->maxSize(20 * 1024)
                    ->maxFiles(fn (?EventAlbum $record): ?int => $this->maxFiles($record))
                    ->validationMessages([
                        'max' => EventAlbum::limitMessage((int) $this->event()->photoLimit()),
                    ]),
                Radio::make('cover_photo')
                    ->label('Capa do álbum')
                    ->helperText('As fotos que você acabou de enviar aparecem aqui depois de salvar. Sem escolha, a capa é a primeira foto.')
                    ->visible(fn (?EventAlbum $record): bool => count($record?->photoPaths() ?? []) > 1)
                    ->options(fn (?EventAlbum $record): array => $this->coverOptions($record))
                    ->inline(),
            ]);
    }

    /**
     * The album's saved photos, as thumbnails to pick from.
     *
     * @return array<string, HtmlString>
     */
    private function coverOptions(?EventAlbum $record): array
    {
        $gallery = app(EventGalleryService::class);
        $options = [];

        foreach ($record?->photoPaths() ?? [] as $index => $path) {
            $options[$path] = new HtmlString(sprintf(
                '<img src="%s" alt="Foto %d" style="width:72px;height:72px;object-fit:cover;border-radius:6px">',
                e($gallery->thumbUrl($path)),
                $index + 1,
            ));
        }

        return $options;
    }

    public function table(Table $table): Table
    {
        $gallery = app(EventGalleryService::class);

        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('álbum')
            ->pluralModelLabel('álbuns')
            ->reorderable('position')
            ->defaultSort('position')
            ->emptyStateHeading('Nenhuma foto ainda')
            ->emptyStateDescription('Crie a galeria do evento e envie as fotos.')
            ->columns([
                ImageColumn::make('cover')
                    ->label('Capa')
                    ->state(fn (EventAlbum $record): ?string => ($path = $record->coverPath()) ? $gallery->thumbUrl($path) : null)
                    ->square(),
                TextColumn::make('name')
                    ->label('Nome'),
                TextColumn::make('photos_count')
                    ->label('Fotos')
                    ->state(fn (EventAlbum $record): int => count($record->photoPaths()))
                    ->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(fn (): string => $this->event()->albums()->exists() ? 'Novo álbum' : 'Criar galeria')
                    ->disabled(fn (): bool => ! $this->event()->canCreateAlbum())
                    ->tooltip(fn (): ?string => $this->event()->canCreateAlbum()
                        ? null
                        : 'Mais de um álbum faz parte do Premium. No plano gratuito, as fotos ficam numa galeria só.')
                    ->before(function (CreateAction $action): void {
                        if (! $this->event()->canCreateAlbum()) {
                            $this->notifyPremium('Álbuns fazem parte do Premium', 'No plano gratuito, as fotos ficam numa galeria só. Contrate o Premium para separar as fotos em álbuns.');
                            $action->halt();
                        }
                    })
                    ->mutateFormDataUsing(fn (array $data): array => [
                        ...$data,
                        'position' => (int) $this->event()->albums()->max('position') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar fotos'),
                DeleteAction::make()
                    ->modalDescription('O álbum e as fotos dele saem da página do evento. Não dá para desfazer.'),
            ]);
    }

    private function notifyPremium(string $title, string $body): void
    {
        Notification::make()
            ->warning()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('discoverPremium')
                    ->label('Conhecer o Premium')
                    ->url(PurchaseEventPremium::getUrl(['record' => $this->event()])),
            ])
            ->persistent()
            ->send();
    }
}
