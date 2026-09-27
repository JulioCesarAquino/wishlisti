<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventPremiumForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventPremium extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Liberar recursos (admin)';

    protected static ?string $navigationLabel = 'Liberar recursos';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    /**
     * Unlocking premium features is the admin's call (or, later, a
     * purchase) — hosts never reach this page.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->isAdmin() && parent::canAccess($parameters);
    }

    public function form(Schema $schema): Schema
    {
        return EventPremiumForm::configure($schema);
    }
}
