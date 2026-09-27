<?php

namespace App\Filament\Resources\Contacts\Contacts;

use App\Filament\Resources\Contacts\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\Contacts\Schemas\ContactForm;
use App\Filament\Resources\Contacts\Contacts\Tables\ContactsTable;
use App\Models\Contacts\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'contato';

    protected static ?string $pluralModelLabel = 'Agenda de contatos';

    protected static ?string $navigationLabel = 'Agenda de contatos';

    protected static ?string $slug = 'contacts';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
