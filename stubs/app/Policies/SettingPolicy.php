<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Authorization rules for Setting records.
 *
 * Additive layer on top of the route-level `check.permission` middleware.
 * Settings expose only read/update abilities — there is no create/delete
 * because settings are seeded and keyed, not user-created rows.
 */
class SettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('settings.read');
    }

    public function view(User $actor, Setting $setting): bool
    {
        return $actor->can('settings.read');
    }

    public function update(User $actor, Setting $setting): bool
    {
        return $actor->can('settings.update');
    }
}
