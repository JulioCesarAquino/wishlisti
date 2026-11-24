<?php

namespace App\Filament\Resources\Identity\InviteRequests\Pages;

use App\Filament\Resources\Identity\InviteRequests\InviteRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListInviteRequests extends ListRecords
{
    protected static string $resource = InviteRequestResource::class;

    protected static ?string $title = 'Cadastros e convites';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
