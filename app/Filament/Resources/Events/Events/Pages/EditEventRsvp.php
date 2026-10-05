<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventRsvpForm;
use App\Filament\Support\PremiumLock;
use App\Models\Events\Event;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The RSVP form's settings. The children's age limit is for every event;
 * the fields and the companions, for the guest list premium feature — shown
 * locked, with the way to the premium page, to hosts without it.
 */
class EditEventRsvp extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Confirmação de presença';

    protected static ?string $navigationLabel = 'Confirmação de presença';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public function form(Schema $schema): Schema
    {
        /** @var Event $event */
        $event = $this->getRecord();
        $hasFeature = $event->hasFeature(Feature::GuestList);
        $isAdmin = (bool) auth()->user()?->isAdmin();

        return $schema->components([
            Group::make()
                ->relationship('rsvpSettings')
                ->columnSpanFull()
                ->components([
                    EventRsvpForm::children(),
                    ...match (true) {
                        $hasFeature => EventRsvpForm::settings(),
                        // The admin can prepare them before unlocking it.
                        $isAdmin => [PremiumLock::notGrantedNotice(Feature::GuestList), ...EventRsvpForm::settings()],
                        default => [PremiumLock::callout(Feature::GuestList, $event)],
                    },
                ]),
        ]);
    }
}
