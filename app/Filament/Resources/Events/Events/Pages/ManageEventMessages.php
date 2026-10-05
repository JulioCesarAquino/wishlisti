<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Pages\Concerns\LocksPremiumNavigationItem;
use App\Filament\Support\PremiumLock;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The guestbook (premium): the host approves which messages appear on the
 * public page.
 */
class ManageEventMessages extends ManageRelatedRecords
{
    use HasEventHeaderActions;
    use LocksPremiumNavigationItem;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'messages';

    protected static ?string $title = 'Mural de recados';

    protected static ?string $navigationLabel = 'Recados';

    protected static ?string $breadcrumb = 'Recados';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static function premiumFeature(): Feature
    {
        return Feature::Guestbook;
    }

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    public function content(Schema $schema): Schema
    {
        if (! auth()->user()?->isAdmin() && ! $this->event()->hasFeature(Feature::Guestbook)) {
            return $schema->components([PremiumLock::callout(Feature::Guestbook, $this->event())]);
        }

        return parent::content($schema);
    }

    public function getSubheading(): ?string
    {
        return 'Os recados só aparecem na página do evento depois que você aprova.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (GuestMessage $record): string => $record->authorNameFor(auth()->user()))
            ->modelLabel('recado')
            ->pluralModelLabel('recados')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('author_name')
                    ->label('De')
                    ->formatStateUsing(fn (GuestMessage $record): string => $record->authorNameFor(auth()->user()))
                    ->placeholder(GuestMessage::ANONYMOUS_NAME)
                    // Searching by name must not find anonymous authors
                    // (it'd give them away), except for the admin.
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('author_name', 'like', "%{$search}%")
                        ->when(! auth()->user()?->isAdmin(), fn (Builder $query) => $query->where('is_anonymous', false))),
                TextColumn::make('message')
                    ->label('Recado')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('approved_at')
                    ->label('Na página')
                    ->badge()
                    ->state(fn (GuestMessage $record): string => $record->isApproved() ? 'Aprovado' : 'Aguardando')
                    ->color(fn (GuestMessage $record): string => $record->isApproved() ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('approved_at')
                    ->label('Situação')
                    ->nullable()
                    ->trueLabel('Aprovados')
                    ->falseLabel('Aguardando aprovação'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (GuestMessage $record): bool => ! $record->isApproved())
                    ->action(fn (GuestMessage $record) => $record->update(['approved_at' => now()])),
                Action::make('hide')
                    ->label('Tirar da página')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (GuestMessage $record): bool => $record->isApproved())
                    ->action(fn (GuestMessage $record) => $record->update(['approved_at' => null])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Aprovar')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(fn (Collection $records) => $records->each->update(['approved_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
