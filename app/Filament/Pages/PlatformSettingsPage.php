<?php

namespace App\Filament\Pages;

use App\Services\Premium\PremiumValidityService;
use App\Support\PlatformSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The platform's own settings, for the admin: the Premium's price and how
 * long it lasts — changed here, not in the server's .env.
 *
 * @property-read Schema $form
 */
class PlatformSettingsPage extends Page
{
    protected static ?string $slug = 'configuracoes';

    protected static ?string $title = 'Configurações';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'premium_price' => PlatformSettings::premiumPrice(),
            'premium_grace_days' => PlatformSettings::premiumGraceDays(),
            'premium_date_window_days' => PlatformSettings::premiumDateWindowDays(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Premium')
                    ->description('Vale para as próximas compras; a carência vale também para as já feitas.')
                    ->columns(3)
                    ->components([
                        TextInput::make('premium_price')
                            ->label('Preço')
                            ->prefix('R$')
                            ->numeric()
                            ->minValue(1)
                            ->step('0.01')
                            ->required(),
                        TextInput::make('premium_grace_days')
                            ->label('Carência após o evento')
                            ->suffix('dias')
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->helperText('Por quantos dias depois da data do evento os recursos Premium continuam liberados. Mudar aqui recalcula o fim do Premium de todos os eventos.'),
                        TextInput::make('premium_date_window_days')
                            ->label('Ajuste de data pelo anfitrião')
                            ->suffix('dias')
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->helperText('Até quantos dias, para mais ou para menos, o anfitrião pode mudar a data de um evento Premium. Além disso, só o admin muda.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Salvar')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $graceChanged = (int) $data['premium_grace_days'] !== PlatformSettings::premiumGraceDays();

        PlatformSettings::set([
            'premium_price' => round((float) $data['premium_price'], 2),
            'premium_grace_days' => (int) $data['premium_grace_days'],
            'premium_date_window_days' => (int) $data['premium_date_window_days'],
        ]);

        if ($graceChanged) {
            app(PremiumValidityService::class)->syncAll();
        }

        Notification::make()->success()->title('Configurações salvas')->send();
    }
}
