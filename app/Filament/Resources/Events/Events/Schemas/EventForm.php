<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Anfitrião')
                    ->relationship('user', 'name')
                    ->required()
                    ->default(fn () => auth()->id())
                    ->disabled(fn () => ! auth()->user()?->isAdmin())
                    ->dehydrated(),
                Select::make('type')
                    ->label('Tipo de evento')
                    ->options([
                        'casamento' => 'Casamento',
                        'cha_bebe' => 'Chá de bebê',
                        'cha_panela' => 'Chá de panela',
                        'aniversario' => 'Aniversário',
                        'outro' => 'Outro',
                    ])
                    ->required(),
                TextInput::make('title')
                    ->label('Título')
                    ->required(),
                TextInput::make('slug')
                    ->helperText('Deixe em branco para gerar automaticamente a partir do título. Usado na URL pública do evento.')
                    ->unique(ignoreRecord: true),
                DatePicker::make('event_date')
                    ->label('Data do evento'),
                FileUpload::make('cover_image')
                    ->label('Imagem de capa')
                    ->image()
                    ->disk('public')
                    ->imageEditor()
                    ->imagePreviewHeight('160')
                    ->directory('events/covers'),
                FileUpload::make('gallery')
                    ->label('Galeria de fotos')
                    ->image()
                    ->disk('public')
                    ->multiple()
                    ->reorderable()
                    ->imagePreviewHeight('120')
                    ->directory('events/gallery'),
                Textarea::make('description')
                    ->label('Texto do evento')
                    ->columnSpanFull(),
                Textarea::make('story')
                    ->label('Nossa história')
                    ->helperText('Texto livre para contar a história do casal/evento. Aparece na seção "Nossa história" da página pública.')
                    ->rows(5)
                    ->columnSpanFull(),
                Section::make('Localização')
                    ->description('Exibida na página pública com um mapa. Vale para casamento, aniversário ou qualquer outro evento.')
                    ->columns(2)
                    ->components([
                        Textarea::make('address')
                            ->label('Endereço')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Rua, número, bairro e cidade — como deve aparecer para os convidados.')
                            ->hintAction(
                                Action::make('useCurrentLocation')
                                    ->label('Usar minha localização atual')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->alpineClickHandler(<<<'JS'
                                        if (! navigator.geolocation) {
                                            alert('Seu navegador não suporta geolocalização. Preencha o endereço e as coordenadas manualmente.');
                                            return;
                                        }
                                        navigator.geolocation.getCurrentPosition(
                                            (position) => {
                                                $wire.set('data.latitude', position.coords.latitude);
                                                $wire.set('data.longitude', position.coords.longitude);
                                                alert('Localização obtida automaticamente. Ela pode não ser exata — confira no mapa e preencha o endereço abaixo.');
                                            },
                                            () => alert('Não foi possível obter sua localização automaticamente. Preencha o endereço e as coordenadas manualmente.'),
                                        );
                                        JS),
                            ),
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step('any')
                            ->minValue(-90)
                            ->maxValue(90)
                            ->requiredWith('longitude'),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step('any')
                            ->minValue(-180)
                            ->maxValue(180)
                            ->requiredWith('latitude'),
                    ]),
                Section::make('Aparência da página pública')
                    ->relationship('appearance')
                    ->columns(2)
                    ->components([
                        Slider::make('cover_effect_intensity')
                            ->label('Intensidade do efeito de desfoque na capa')
                            ->helperText('100 é o efeito completo (foto embaçada até o convidado passar o mouse ou tocar). 0 exibe a foto normalmente, sem nenhum efeito.')
                            ->range(minValue: 0, maxValue: 100)
                            ->step(5)
                            ->tooltips()
                            ->default(100)
                            ->columnSpanFull(),
                        Select::make('font_family')
                            ->label('Fonte')
                            ->options([
                                'default' => 'Moderna',
                                'script' => 'Manuscrita',
                                'serif' => 'Elegante',
                                'classic' => 'Clássica',
                            ])
                            ->default('default')
                            ->native(false)
                            ->columnSpanFull(),
                        ColorPicker::make('primary_color')
                            ->label('Cor primária')
                            ->helperText('Tom de fundo geral da página.'),
                        ColorPicker::make('secondary_color')
                            ->label('Cor secundária')
                            ->helperText('Usada nos botões e detalhes decorativos.'),
                        ColorPicker::make('font_color_primary')
                            ->label('Cor da fonte principal')
                            ->helperText('Usada no nome do casal e títulos.'),
                        ColorPicker::make('font_color_secondary')
                            ->label('Cor da fonte secundária')
                            ->helperText('Usada nos textos e menu.'),
                    ]),
                Section::make('Mercado Pago')
                    ->description('Necessário para o evento poder receber pagamentos via Pix, cartão ou boleto.')
                    ->relationship('paymentSettings')
                    ->components([
                        TextInput::make('mp_access_token')
                            ->label('Access Token')
                            ->password()
                            ->revealable()
                            ->hintAction(
                                Action::make('mpCredentialsHelp')
                                    ->label('Como obter minhas credenciais')
                                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                                    ->url('https://www.mercadopago.com.br/developers/pt/docs/linx/additional-content/your-integrations/credentials#bookmark_obter_credenciais')
                                    ->openUrlInNewTab(),
                            )
                            ->columnSpanFull(),
                        TextInput::make('mp_public_key')
                            ->label('Public Key')
                            ->columnSpanFull(),
                    ]),
                Toggle::make('is_published')
                    ->label('Publicado')
                    ->helperText('Enquanto desativado, só você e o admin conseguem ver a página pública (modo prévia). Convidados não têm acesso.')
                    ->default(false),
                CheckboxList::make('premium_features')
                    ->label('Recursos premium deste evento')
                    ->options(collect(Feature::for(Event::class))->mapWithKeys(fn (Feature $feature) => [$feature->value => $feature->label()]))
                    ->descriptions(collect(Feature::for(Event::class))->mapWithKeys(fn (Feature $feature) => [$feature->value => $feature->description()]))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->live()
                    ->afterStateHydrated(fn (CheckboxList $component, ?Event $record) => $component->state(
                        array_map(fn (Feature $feature) => $feature->value, $record?->activeFeatures() ?? []),
                    ))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(fn (Event $record, ?array $state) => $record->syncFeatures($state ?? []))
                    ->columnSpanFull(),
                Section::make('Formulário de confirmação de presença')
                    ->description('Escolha quais dados os convidados precisam informar ao confirmar presença.')
                    ->relationship('rsvpSettings')
                    ->visible(fn (Get $get, ?Event $record): bool => auth()->user()?->isAdmin()
                        ? in_array(Feature::GuestList->value, $get('premium_features') ?? [], true)
                        : (bool) $record?->hasFeature(Feature::GuestList))
                    ->columnSpanFull()
                    ->components([
                        CheckboxList::make('required_fields')
                            ->label('Dados obrigatórios')
                            ->helperText('O nome é sempre obrigatório. Os dados marcados serão exigidos do convidado e de cada acompanhante, e também servem para reconhecer quem já foi listado por outra pessoa.')
                            ->options([
                                'whatsapp' => 'Telefone / WhatsApp',
                                'email' => 'E-mail',
                                'cpf' => 'CPF',
                            ])
                            ->default(['whatsapp'])
                            // Events created before this setting existed have
                            // it empty; start them from the free form's rule.
                            ->afterStateHydrated(function (CheckboxList $component, ?array $state): void {
                                if (blank($state)) {
                                    $component->state(['whatsapp']);
                                }
                            })
                            ->required()
                            ->minItems(1)
                            ->columns(3),
                        Toggle::make('collect_companions')
                            ->label('Pedir os dados de cada acompanhante')
                            ->helperText('Em vez de só informar quantas pessoas vão, o convidado preenche os dados de cada uma. Se um acompanhante confirmar presença por conta própria, ele deixa de contar para quem o listou e vira uma confirmação individual.')
                            ->default(false),
                    ]),
            ]);
    }
}
