<?php

namespace App\Models\Catalog;

use App\Models\Events\Event;
use App\Models\Orders\OrderItem;
use Database\Factories\Catalog\EventProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $product_template_id
 * @property string $name
 * @property string|null $description
 * @property string|null $image
 * @property float $price
 * @property int $quantity_total
 * @property int $quantity_purchased
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'event_id', 'product_template_id', 'name', 'description',
    'image', 'price', 'quantity_total', 'quantity_purchased', 'is_active',
])]
class EventProduct extends Model
{
    /** @use HasFactory<EventProductFactory> */
    use HasFactory;

    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('event_product')
            ->logOnly(['name', 'description', 'image', 'price', 'quantity_total', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'adicionou o presente',
                'updated' => 'editou o presente',
                'deleted' => $this->isForceDeleting() ? 'excluiu definitivamente o presente' : 'moveu o presente para a lixeira',
                'restored' => 'restaurou o presente',
                default => $event,
            });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity_total' => 'integer',
            'quantity_purchased' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<ProductTemplate, $this>
     */
    public function productTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * A gift that is part of an order stays in the order history: it can
     * be deactivated (hidden from the public page), not deleted.
     */
    public function hasOrders(): bool
    {
        return $this->quantity_purchased > 0 || $this->orderItems()->exists();
    }

    public function quantityAvailable(): int
    {
        return max(0, $this->quantity_total - $this->quantity_purchased);
    }

    public function isSoldOut(): bool
    {
        return $this->quantityAvailable() === 0;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
