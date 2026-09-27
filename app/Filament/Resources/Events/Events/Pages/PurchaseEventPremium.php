<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Models\Events\Event;
use App\Models\Premium\PremiumPurchase;
use App\Services\Premium\PremiumPurchaseStoreService;
use App\Services\Premium\PremiumPurchaseUpdateService;
use App\Support\MercadoPagoPlatform;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Where a host learns what the premium plan offers and buys it for the
 * event, paying the platform through the Mercado Pago Payment Brick.
 */
class PurchaseEventPremium extends Page
{
    use HasEventHeaderActions;
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Premium';

    protected static ?string $navigationLabel = 'Premium';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        $items = parent::getNavigationItems($urlParameters);
        $record = $urlParameters['record'] ?? null;

        if ($record instanceof Event && app(PremiumPurchaseUpdateService::class)->hasEverything($record)) {
            foreach ($items as $item) {
                $item->badge('Ativo', 'success');
            }
        }

        return $items;
    }

    public static function formattedPrice(): string
    {
        return 'R$ '.number_format((float) config('premium.price'), 2, ',', '.');
    }

    protected function event(): Event
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return $event;
    }

    protected function hasEverything(): bool
    {
        return app(PremiumPurchaseUpdateService::class)->hasEverything($this->event());
    }

    protected function pendingPurchase(): ?PremiumPurchase
    {
        return PremiumPurchase::query()
            ->where('event_id', $this->event()->id)
            ->where('status', PremiumPurchase::STATUS_PENDING)
            ->whereNotNull('payment_id')
            ->latest('id')
            ->first();
    }

    public function content(Schema $schema): Schema
    {
        $event = $this->event();

        return $schema->components([
            $this->hasEverything()
                ? Callout::make('Este evento é Premium')
                    ->description('Todos os recursos abaixo estão liberados. Obrigado por apoiar o Wishlisti!')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->success()
                : Callout::make('Wishlisti Premium por '.self::formattedPrice())
                    ->description('Pagamento único para este evento, sem mensalidade. Os recursos são liberados assim que o pagamento é aprovado.')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->warning(),
            Grid::make(1)
                ->schema(array_map(fn (Feature $feature) => $this->featureCard($feature, $event), [
                    ...config('premium.event_features'),
                    ...config('premium.host_features'),
                ])),
            ...$this->checkoutComponents(),
        ]);
    }

    private function featureCard(Feature $feature, Event $event): Section
    {
        $active = $feature->appliesTo() === Event::class
            ? $event->hasFeature($feature)
            : $event->user->hasFeature($feature);

        return Section::make($feature->label())
            ->description($feature->headline())
            ->icon($feature->icon())
            ->iconColor('primary')
            ->aside()
            ->afterHeader($active ? [Text::make('Ativo')->badge()->color('success')] : [])
            ->schema([
                ...array_map(fn (string $benefit) => Text::make("✓  {$benefit}"), $feature->benefits()),
                ...($feature->appliesTo() === Event::class ? [] : [
                    Text::make('Vale para todos os seus eventos.')->badge()->color('gray'),
                ]),
            ]);
    }

    /**
     * @return array<int, Callout|Section>
     */
    private function checkoutComponents(): array
    {
        if ($this->hasEverything()) {
            return [];
        }

        if (! MercadoPagoPlatform::isConfigured()) {
            return [
                Callout::make('Pagamento indisponível no momento')
                    ->description('Fale com o administrador do Wishlisti para liberar o Premium neste evento.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->danger(),
            ];
        }

        $components = [];

        if ($pending = $this->pendingPurchase()) {
            $components[] = Callout::make('Pagamento aguardando confirmação')
                ->description('Se você pagou com Pix ou boleto, a liberação acontece assim que o Mercado Pago confirmar. Já pagou? Verifique agora.')
                ->icon(Heroicon::OutlinedClock)
                ->info()
                ->actions([
                    Action::make('checkPayment')
                        ->label('Verificar pagamento')
                        ->action(fn () => $this->checkPayment($pending)),
                ]);
        }

        $components[] = Section::make('Pagamento')
            ->description('Pague com Pix, cartão ou boleto. Processado com segurança pelo Mercado Pago.')
            ->icon(Heroicon::OutlinedLockClosed)
            ->schema([
                View::make('filament.premium.checkout')->viewData([
                    'publicKey' => MercadoPagoPlatform::publicKey(),
                    'amount' => (float) config('premium.price'),
                    'payerEmail' => auth()->user()?->email,
                ]),
            ]);

        return $components;
    }

    /**
     * Called by the Payment Brick when the host submits the form.
     *
     * @param  array<string, mixed>  $formData
     * @return array{payment_id?: string|null, status?: string, error?: string}
     */
    public function pay(array $formData): array
    {
        if (blank($formData['payment_method_id'] ?? null)) {
            return ['error' => 'Escolha uma forma de pagamento.'];
        }

        try {
            $purchase = app(PremiumPurchaseStoreService::class)->execute($this->event(), auth()->user(), $formData);
        } catch (ValidationException $exception) {
            return ['error' => (string) collect($exception->errors())->flatten()->first()];
        }

        if ($purchase->status === PremiumPurchase::STATUS_PAID) {
            Notification::make()->success()->title('Premium ativado!')->body('Os recursos já estão liberados neste evento.')->send();
        }

        return ['payment_id' => $purchase->payment_id, 'status' => $purchase->status];
    }

    protected function checkPayment(PremiumPurchase $purchase): void
    {
        app(PremiumPurchaseUpdateService::class)->execute((string) $purchase->payment_id);

        $purchase->refresh();
        $this->event()->unsetRelation('featureGrants');

        $purchase->status === PremiumPurchase::STATUS_PAID
            ? Notification::make()->success()->title('Pagamento confirmado — Premium ativado!')->send()
            : Notification::make()->warning()->title('O pagamento ainda não foi confirmado')->body('Tente de novo em alguns minutos.')->send();
    }
}
