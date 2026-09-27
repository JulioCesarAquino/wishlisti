<?php

namespace App\Services\Premium;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Premium\FeatureGrant;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Support\MercadoPagoPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Resources\Payment;

class PremiumPurchaseUpdateService
{
    public function __construct(
        protected PaymentClient $paymentClient,
    ) {}

    /**
     * Re-fetches the payment from Mercado Pago (never trusting a webhook
     * body or the browser) and applies its status to the purchase.
     */
    public function execute(string $paymentId): void
    {
        if (! MercadoPagoPlatform::isConfigured()) {
            return;
        }

        try {
            $payment = $this->paymentClient->get((int) $paymentId, MercadoPagoPlatform::requestOptions());
        } catch (MPApiException $exception) {
            Log::warning('Mercado Pago: falha ao buscar pagamento do Premium', [
                'payment_id' => $paymentId,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
            ]);

            return;
        }

        $this->applyPayment($payment);
    }

    /**
     * Unlocks the features on the transition into "paid" and revokes the
     * ones this purchase unlocked on a refund or chargeback, so applying
     * the same payment repeatedly (webhook retries) is harmless.
     */
    public function applyPayment(Payment $payment): void
    {
        $purchaseId = PremiumPurchase::idFromExternalReference($payment->external_reference);

        if (! $purchaseId) {
            return;
        }

        DB::transaction(function () use ($purchaseId, $payment): void {
            $purchase = PremiumPurchase::whereKey($purchaseId)->lockForUpdate()->first();

            if (! $purchase) {
                Log::warning('Mercado Pago: compra Premium não encontrada', ['external_reference' => $payment->external_reference]);

                return;
            }

            $newStatus = match ($payment->status) {
                'approved' => PremiumPurchase::STATUS_PAID,
                'rejected' => PremiumPurchase::STATUS_FAILED,
                'cancelled', 'refunded', 'charged_back' => PremiumPurchase::STATUS_CANCELLED,
                default => PremiumPurchase::STATUS_PENDING,
            };

            if ($newStatus === PremiumPurchase::STATUS_PAID && (float) $payment->transaction_amount < (float) $purchase->amount) {
                Log::error('Mercado Pago: valor pago do Premium menor que o preço', [
                    'premium_purchase_id' => $purchase->id,
                    'paid' => $payment->transaction_amount,
                ]);

                return;
            }

            $wasPaid = $purchase->status === PremiumPurchase::STATUS_PAID;

            $purchase->forceFill([
                'status' => $newStatus,
                'payment_id' => (string) $payment->id,
                'payment_method' => $payment->payment_method_id,
                'paid_at' => $newStatus === PremiumPurchase::STATUS_PAID ? ($purchase->paid_at ?? now()) : $purchase->paid_at,
            ])->save();

            if ($newStatus === PremiumPurchase::STATUS_PAID && ! $wasPaid) {
                $this->grant($purchase);
            }

            if ($wasPaid && $newStatus !== PremiumPurchase::STATUS_PAID) {
                $purchase->featureGrants()->get()->each->delete();
            }
        });
    }

    /**
     * Whether the event (and its host) already have everything the plan
     * unlocks — then there's nothing left to sell.
     */
    public function hasEverything(Event $event): bool
    {
        /** @var array<int, Feature> $eventFeatures */
        $eventFeatures = config('premium.event_features');
        /** @var array<int, Feature> $hostFeatures */
        $hostFeatures = config('premium.host_features');

        return collect($eventFeatures)->every(fn (Feature $feature) => $event->hasFeature($feature))
            && collect($hostFeatures)->every(fn (Feature $feature) => $event->user->hasFeature($feature));
    }

    private function grant(PremiumPurchase $purchase): void
    {
        $event = $purchase->event;

        $this->grantTo($event, config('premium.event_features'), $purchase);
        $this->grantTo($event->user, config('premium.host_features'), $purchase);
    }

    /**
     * Features already active (e.g. unlocked by the admin) are left alone.
     *
     * @param  Event|User  $model
     * @param  array<int, Feature>  $features
     */
    private function grantTo(Model $model, array $features, PremiumPurchase $purchase): void
    {
        foreach ($features as $feature) {
            if ($model->hasFeature($feature)) {
                continue;
            }

            // An expired grant would clash with the unique index.
            $model->featureGrants()->where('feature', $feature)->get()->each->delete();

            $model->featureGrants()->create([
                'feature' => $feature,
                'source' => FeatureGrant::SOURCE_PURCHASE,
                'premium_purchase_id' => $purchase->id,
            ]);
        }

        $model->unsetRelation('featureGrants');
    }
}
