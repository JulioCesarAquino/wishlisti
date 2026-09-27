<?php

namespace App\Models\Contacts;

use App\Models\Concerns\HasContactDetails;
use App\Models\User;
use Database\Factories\Contacts\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone in a host's address book. Private to that host: it's how a host
 * reuses their own guest list across their events, never shared with other
 * hosts or linked to guests of events that aren't theirs.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $cpf
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'whatsapp', 'email', 'cpf', 'notes'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasContactDetails, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
