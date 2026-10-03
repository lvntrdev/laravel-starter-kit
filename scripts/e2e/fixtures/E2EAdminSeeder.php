<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Lvntr\StarterKit\Domain\Setting\SettingService;

/**
 * Disposable fixture seeder for the Playwright E2E smoke suite.
 *
 * NOT part of the shipped consumer scaffold (`stubs/`) and NOT wired into
 * `DatabaseSeeder`. It is invoked directly by the E2E test harness against a
 * throwaway SQLite fixture database only.
 *
 * The email/password below are fixed TEST-ONLY defaults, overridable via
 * env. They exist solely to let the smoke test log in against a disposable
 * fixture database and must never be used against a real/production
 * database.
 */
class E2EAdminSeeder extends Seeder
{
    /**
     * Create (or find) the E2E admin users and grant them the system_admin role.
     *
     * Idempotent: safe to run repeatedly against the same fixture — users are
     * looked up by email, and the role assignment is synced rather than
     * appended.
     */
    public function run(): void
    {
        $password = env('E2E_ADMIN_PASSWORD', 'e2e-test-password-only');

        $this->seedAdmin(env('E2E_ADMIN_EMAIL', 'e2e-admin@example.test'), 'Admin', $password);

        // two-factor.spec.ts turns 2FA on for its user; a separate account keeps
        // a challenge from ever landing in front of the smoke spec's login.
        $this->seedAdmin('e2e-2fa@example.test', 'TwoFactor', $password);

        // Fresh installs ship with 2FA switched off; two-factor.spec.ts needs it on.
        app(SettingService::class)->setValue('auth.two_factor', '1');
    }

    private function seedAdmin(string $email, string $lastName, string $password): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'first_name' => 'E2E',
                'last_name' => $lastName,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );

        // Keep credentials in sync with env on reruns (fixture DB may be reused),
        // and start with 2FA off — a failed two-factor.spec.ts run leaves it on.
        $user->forceFill([
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->syncRoles(RoleEnum::SystemAdmin->value);
    }
}
