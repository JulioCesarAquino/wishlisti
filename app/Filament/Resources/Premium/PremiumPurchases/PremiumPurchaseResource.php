<?php

namespace App\Filament\Resources\Premium\PremiumPurchases;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Premium\PremiumPurchases\Pages\ListPremiumPurchases;
use App\Models\Premium\PremiumPurchase;
use App\Services\Payments\PaymentPayerLookupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The platform's premium sales, for the admin.
 */
class PremiumPurchaseResource extends Resource
{
    protected static ?string $model = PremiumPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'venda premium';

    protected static ?string $pluralModelLabel = 'Vendas Premium';

    protected static ?string $slug = 'premium-purchases';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['event', 'user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('event.title')
                    ->label('Evento')
                    ->searchable()
                    ->url(fn (PremiumPurchase $record) => EventResource::getUrl('edit', ['record' => $record->event])),
                TextColumn::make('user.name')
                    ->label('Anfitrião')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL'),
                TextColumn::make('payment_method')
                    ->label('Forma de pagamento')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PremiumPurchase::STATUS_PAID => 'Pago',
                        PremiumPurchase::STATUS_FAILED => 'Recusado',
                        PremiumPurchase::STATUS_CANCELLED => 'Cancelado/estornado',
                        default => 'Pendente',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        PremiumPurchase::STATUS_PAID => 'success',
                        PremiumPurchase::STATUS_FAILED, PremiumPurchase::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                // Admin only: asked of Mercado Pago on opening, never stored,
                // and logged in the audit trail (see PaymentPayerLookupService).
                Action::make('viewPayer')
                    ->label('Ver pagador')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->color('gray')
                    ->visible(fn (PremiumPurchase $record): bool => (bool) auth()->user()?->isAdmin() && filled($record->payment_id))
                    ->modalHeading('Quem pagou')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (PremiumPurchase $record) => view('filament.payments.payer', [
                        'payer' => app(PaymentPayerLookupService::class)->execute($record, auth()->user()),
                    ])),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PremiumPurchase::STATUS_PAID => 'Pago',
                        PremiumPurchase::STATUS_PENDING => 'Pendente',
                        PremiumPurchase::STATUS_FAILED => 'Recusado',
                        PremiumPurchase::STATUS_CANCELLED => 'Cancelado/estornado',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPremiumPurchases::route('/'),
        ];
    }
}
