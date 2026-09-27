<?php

namespace App\Filament\Resources\Events\Events;

use App\Filament\Resources\Events\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Events\Pages\EditEventAppearance;
use App\Filament\Resources\Events\Events\Pages\EditEventLocation;
use App\Filament\Resources\Events\Events\Pages\EditEventPayment;
use App\Filament\Resources\Events\Events\Pages\EditEventPremium;
use App\Filament\Resources\Events\Events\Pages\EditEventRsvp;
use App\Filament\Resources\Events\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Events\Pages\ManageEventGuests;
use App\Filament\Resources\Events\Events\Pages\ManageEventProducts;
use App\Filament\Resources\Events\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Events\Tables\EventsTable;
use App\Models\Events\Event;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'Eventos';

    protected static ?string $slug = 'events';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    /**
     * Lets trashed events still be opened — to be restored.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    /**
     * Each part of an event is its own page, with its own save button,
     * instead of one long form. Pages a user can't access (like Premium,
     * for hosts) are left out automatically.
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditEvent::class,
            EditEventLocation::class,
            EditEventAppearance::class,
            ManageEventProducts::class,
            ManageEventGuests::class,
            EditEventRsvp::class,
            EditEventPayment::class,
            EditEventPremium::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
            'location' => EditEventLocation::route('/{record}/location'),
            'appearance' => EditEventAppearance::route('/{record}/appearance'),
            'products' => ManageEventProducts::route('/{record}/products'),
            'guests' => ManageEventGuests::route('/{record}/guests'),
            'rsvp' => EditEventRsvp::route('/{record}/rsvp'),
            'payment' => EditEventPayment::route('/{record}/payment'),
            'premium' => EditEventPremium::route('/{record}/premium'),
        ];
    }
}
