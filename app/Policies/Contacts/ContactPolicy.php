<?php

namespace App\Policies\Contacts;

use App\Enums\Premium\Feature;
use App\Models\Contacts\Contact;
use App\Models\User;

/**
 * The address book is a premium feature of the host, and each host only
 * ever sees their own contacts.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasFeature(Feature::Contacts);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->isAdmin() || ($user->hasFeature(Feature::Contacts) && $user->id === $contact->user_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
