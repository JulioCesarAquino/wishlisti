<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\Event;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
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
                Textarea::make('story')
                    ->label('Nossa história')
                    ->helperText('Texto livre para contar a história do casal/evento. Aparece na seção "Nossa história" da página pública.')
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
                ->required(),
            TextInput::make('title')
                ->label('Título')
                ->required(),
            DatePicker::make('event_date')
                ->label('Data do evento'),
        ];
    }
}
