<?php

namespace App\Filament\Resources\Identity\InviteRequests;

use App\Filament\Resources\Identity\InviteRequests\Pages\ListInviteRequests;
use App\Filament\Resources\Identity\InviteRequests\Tables\InviteRequestsTable;
use App\Models\Identity\InviteRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InviteRequestResource extends Resource
{
    protected static ?string $model = InviteRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'invite-requests';

    protected static ?string $modelLabel = 'cadastro';

    protected static ?string $pluralModelLabel = 'Cadastros e convites';

    protected static ?string $navigationLabel = 'Cadastros e convites';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function table(Table $table): Table
    {
        return InviteRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInviteRequests::route('/'),
        ];
    }
}
