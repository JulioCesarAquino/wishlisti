<?php

namespace App\Filament\Resources\Events\Events\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Shared by every page of an event's sub-navigation.
 */
trait HasEventHeaderActions
{
    protected function viewPublicPageAction(): Action
    {
        return Action::make('viewPublicPage')
            ->label('Ver página pública')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (): string => route('events.show', $this->getRecord()))
            ->openUrlInNewTab();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewPublicPageAction(),
        ];
    }
}
