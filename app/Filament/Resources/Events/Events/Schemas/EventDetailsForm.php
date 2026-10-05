<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\Event;
use App\Services\Premium\PremiumValidityService;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventDetailsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::basicComponents(),
                TextInput::make('slug')
                    ->helperText('Deixe em branco para gerar automaticamente a partir do título. Usado na URL pública do evento.')
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Texto do evento')
                    ->columnSpanFull(),
                TextInput::make('story_title')
                    ->label('Título da seção de história')
                    ->maxLength(80)
                    ->placeholder(fn (Get $get): string => Event::defaultStoryTitle($get('type')))
                    ->helperText('Em branco, usa o título do tipo de evento (o que aparece no campo). Ex.: "Sobre a Maju".')
                    ->columnSpanFull(),
                Textarea::make('story')
                    ->label('História')
                    ->helperText('Texto livre: a história do casal, do aniversariante, da turma… Aparece na tela inicial da página pública, com o título acima.')
                    ->rows(5)
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Publicado')
                    ->helperText('Enquanto desativado, só você e o admin conseguem ver a página pública (modo prévia). Convidados não têm acesso.')
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Also used by the create form.
     *
     * @return array<int, Component>
     */
    public static function basicComponents(): array
    {
        return [
            Select::make('user_id')
                ->label('Anfitrião')
                ->relationship('user', 'name')
                ->required()
                ->default(fn () => auth()->id())
                ->disabled(fn () => ! auth()->user()?->isAdmin())
                ->dehydrated(),
            Select::make('type')
                ->label('Tipo de evento')
                ->options(Event::TYPE_LABELS)
                ->searchable()
                ->live()
                ->required(),
            TextInput::make('title')
                ->label('Título')
                ->required(),
            DatePicker::make('event_date')
                ->label('Data do evento')
                // The Premium is bought for this date: while it lasts, the
                // host can only move it so far (the admin, anywhere).
                ->required(fn (?Event $record): bool => self::premiumDates($record) !== null)
                ->minDate(fn (?Event $record) => self::premiumDates($record)[0] ?? null)
                ->maxDate(fn (?Event $record) => self::premiumDates($record)[1] ?? null)
                ->helperText(fn (?Event $record): ?string => ($dates = self::premiumDates($record))
                    ? "O Premium foi comprado para esta data. Você pode ajustá-la entre {$dates[0]->format('d/m/Y')} e {$dates[1]->format('d/m/Y')}; para mudar além disso, fale com o Wishlisti."
                    : null),
            TimePicker::make('event_time')
                ->label('Horário de início')
                ->seconds(false)
                ->helperText('Aparece junto à data, e a contagem regressiva conta até ele. Cerimônia e festa em horários diferentes? Informe o horário de cada uma em Localização.'),
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    private static function premiumDates(?Event $record): ?array
    {
        if (! $record?->exists || auth()->user()?->isAdmin()) {
            return null;
        }

        return app(PremiumValidityService::class)->allowedDates($record);
    }
}
