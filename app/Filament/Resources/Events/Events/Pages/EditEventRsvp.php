<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventRsvpForm;
use App\Filament\Support\PremiumLock;
use App\Models\Events\Event;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Premium ("Lista nominal de convidados"). Hosts without it still see the
 * page, locked, so they know the feature exists.
 */
class EditEventRsvp extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Confirmação de presença';

    protected static ?string $navigationLabel = 'Confirmação de presença';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    /**
     * @param  array<string, mixed>  $urlParameters
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        $items = parent::getNavigationItems($urlParameters);
        $record = $urlParameters['record'] ?? null;

        if ($record instanceof Event && ! $record->hasFeature(Feature::GuestList)) {
            foreach ($items as $item) {
                $item->icon(Heroicon::OutlinedLockClosed)->badge('Premium', 'warning');
            }
        }

        return $items;
    }

    protected function isLocked(): bool
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return ! auth()->user()?->isAdmin() && ! $event->hasFeature(Feature::GuestList);
    }

    public function form(Schema $schema): Schema
    {
        /** @var Event $event */
        $event = $this->getRecord();

        if ($this->isLocked()) {
            return $schema->components([PremiumLock::callout(Feature::GuestList)]);
        }

        return $schema->components([
            ...($event->hasFeature(Feature::GuestList) ? [] : [PremiumLock::notGrantedNotice(Feature::GuestList)]),
            EventRsvpForm::settings(),
        ]);
    }

    protected function getFormActions(): array
    {
        return $this->isLocked() ? [] : parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_if($this->isLocked(), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}
