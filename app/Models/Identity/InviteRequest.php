<?php

namespace App\Models\Identity;

use App\Models\Events\Event;
use App\Models\User;
use Database\Factories\Identity\InviteRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $whatsapp
 * @property string $status
 * @property int|null $invited_user_id
 * @property int|null $referred_by_event_id the event page they came from
 * @property-read Event|null $referredByEvent
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'whatsapp'])]
class InviteRequest extends Model
{
    /** @use HasFactory<InviteRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The event whose page brought them here ("Feita com Wishlisti"). Also
     * an event since sent to the trash.
     *
     * @return BelongsTo<Event, $this>
     */
    public function referredByEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'referred_by_event_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }
}
