<?php

namespace App\Filament\Pages;

use App\Filament\Support\EventPicker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The totals of one event, or of all of them: it opens on the user's
 * default event, and the choice is kept while they browse the panel.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->components([
                    EventPicker::select('event_id')
                        ->default(fn (): ?int => EventPicker::default()),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('defaultEvent')
                ->label('Evento padrão')
                ->icon(Heroicon::OutlinedStar)
                ->color('gray')
                ->modalHeading('Evento padrão')
                ->modalDescription('O painel e a lista de convidados abrem já filtrados por ele. Você continua podendo trocar de evento, ou ver todos de uma vez.')
                ->modalSubmitActionLabel('Salvar')
                ->fillForm(fn (): array => ['default_event_id' => EventPicker::default()])
                ->schema([
                    EventPicker::select('default_event_id')
                        ->label('Abrir sempre em')
                        ->placeholder('Nenhum: todos os eventos'),
                ])
                ->action(function (array $data): void {
                    $eventId = EventPicker::chosen($data['default_event_id'] ?? null);

                    auth()->user()->forceFill(['default_event_id' => $eventId])->save();

                    $this->filters = [...($this->filters ?? []), 'event_id' => $eventId];
                    $this->updatedFilters();

                    Notification::make()->success()->title('Evento padrão salvo')->send();
                }),
        ];
    }
}
