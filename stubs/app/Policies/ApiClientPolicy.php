<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Passport\Client;

/**
 * Authorization rules for Passport OAuth Client records.
 *
 * Super admin bypass is handled by Gate::before in AuthServiceProvider.
 * These methods enforce explicit permission checks for non-super-admin users.
 */
class ApiClientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('api-clients.read');
    }

    public function view(User $actor, Client $client): bool
    {
        return $actor->can('api-clients.read');
    }

    public function create(User $actor): bool
    {
        return $actor->can('api-clients.create');
    }

    public function update(User $actor, Client $client): bool
    {
        return $actor->can('api-clients.update');
    }

    public function delete(User $actor, Client $client): bool
    {
        return $actor->can('api-clients.delete');
    }
}
