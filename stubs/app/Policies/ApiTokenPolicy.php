<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Passport\Token;

/**
 * Authorization rules for Passport Personal Access Token records.
 *
 * Super admin bypass is handled by Gate::before in AuthServiceProvider.
 * These methods enforce explicit permission checks for non-super-admin users.
 *
 * Privilege escalation guard: a user may only revoke their own tokens
 * unless they hold the api-tokens.delete permission.
 *
 * Kısmi erişim modeli: viewAny, api-tokens.create iznine sahip kullanıcılara
 * da true döner. Bu sayede kullanıcılar kendi token'larını yönetebilir
 * (oluşturma ve iptal — security incident response için kritik).
 * Gerçek listeleme kapsamı ApiTokenController::dtApi içinde belirlenir.
 */
class ApiTokenPolicy
{
    public function viewAny(User $actor): bool
    {
        // api-tokens.read: tüm token'ları listeler (admin görünümü)
        // api-tokens.create: yalnızca kendi token'larını listeler
        return $actor->can('api-tokens.read') || $actor->can('api-tokens.create');
    }

    public function view(User $actor, Token $token): bool
    {
        // Token sahipleri kendi token'larını görebilir
        if ((string) $actor->id === (string) $token->user_id) {
            return true;
        }

        return $actor->can('api-tokens.read');
    }

    public function create(User $actor): bool
    {
        return $actor->can('api-tokens.create');
    }

    /**
     * Token revoke (delete) yetkisi.
     *
     * Privilege escalation riski: kullanıcı yalnızca kendi token'ını revoke edebilir.
     * api-tokens.delete izni olanlar tüm token'ları revoke edebilir.
     */
    public function delete(User $actor, Token $token): bool
    {
        if ((string) $actor->id === (string) $token->user_id) {
            return true;
        }

        return $actor->can('api-tokens.delete');
    }
}
