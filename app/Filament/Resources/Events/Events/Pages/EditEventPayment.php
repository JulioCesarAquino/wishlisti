<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Pages\Concerns\RequiresPremiumFeature;
use App\Filament\Resources\Events\Events\Schemas\EventPaymentForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventPayment extends EditRecord
{
    use HasEventHeaderActions;
    use RequiresPremiumFeature;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Pagamentos';

    protected static ?string $navigationLabel = 'Pagamentos';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static function premiumFeature(): Feature
    {
        return Feature::Payments;
    }

    public function form(Schema $schema): Schema
    {
        return $this->premiumForm($schema, EventPaymentForm::components());
    }
}
