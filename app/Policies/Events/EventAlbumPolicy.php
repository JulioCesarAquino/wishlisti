<?php

namespace App\Policies\Events;

use App\Models\Events\EventAlbum;
use App\Models\User;

/**
 * Albums are managed by whoever manages their event. Whether the event can
 * have one more album (premium) is EventAlbum's own check, since creating
 * doesn't know the event here.
 */
class EventAlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EventAlbum $album): bool
    {
        return $album->event->isManagedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EventAlbum $album): bool
    {
        return $album->event->isManagedBy($user);
    }

    public function delete(User $user, EventAlbum $album): bool
    {
        return $album->event->isManagedBy($user);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function reorder(User $user): bool
    {
        return true;
    }
}
