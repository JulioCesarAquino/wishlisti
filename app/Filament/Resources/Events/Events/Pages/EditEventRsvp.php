<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Pages\Concerns\RequiresPremiumFeature;
use App\Filament\Resources\Events\Events\Schemas\EventRsvpForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventRsvp extends EditRecord
{
    use HasEventHeaderActions;
    use RequiresPremiumFeature;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Confirmação de presença';

    protected static ?string $navigationLabel = 'Confirmação de presença';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static function premiumFeature(): Feature
    {
        return Feature::GuestList;
    }

    public function form(Schema $schema): Schema
    {
        return $this->premiumForm($schema, [EventRsvpForm::settings()]);
    }
}
