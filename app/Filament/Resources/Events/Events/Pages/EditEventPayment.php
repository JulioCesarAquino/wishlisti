<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventPaymentForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventPayment extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Pagamentos';

    protected static ?string $navigationLabel = 'Pagamentos';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public function form(Schema $schema): Schema
    {
        return EventPaymentForm::configure($schema);
    }
}
