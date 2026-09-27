<?php

namespace App\Filament\Resources\Contacts\Contacts\Schemas;

use App\Rules\Guests\Cpf;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactForm
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
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('whatsapp')
                    ->label('Telefone / WhatsApp')
                    ->maxLength(30)
                    ->requiredWithoutAll(['email', 'cpf'])
                    ->validationMessages([
                        'required_without_all' => 'Informe ao menos um contato: telefone, e-mail ou CPF.',
                    ]),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                TextInput::make('cpf')
                    ->label('CPF')
                    ->rule(new Cpf),
                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
