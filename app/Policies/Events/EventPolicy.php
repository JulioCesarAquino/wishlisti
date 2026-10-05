<?php

namespace App\Policies\Events;

use App\Models\Events\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return $event->isManagedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isManagedBy($user);
    }

    /**
     * Trashing (and restoring) is the owner's call, not a co-host's.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->isOwnedBy($user);
    }

    /**
     * Adding and removing co-hosts, too.
     */
    public function manageCoHosts(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->isOwnedBy($user);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->isOwnedBy($user);
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    /**
     * Deleting for good takes the event's orders with it, so it's
     * admin-only — hosts archive or trash events instead.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
