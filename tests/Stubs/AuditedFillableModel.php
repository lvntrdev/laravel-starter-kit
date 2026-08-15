<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Traits\HasActivityLogging;

/**
 * Test-only audited model exercising the `$fillable` branch of
 * {@see HasActivityLogging} (Spatie's `logFillable()`).
 *
 * Mirrors the shipped `User` stub's shape in the one way that matters: it keeps
 * `password` in `$fillable` because mass assignment needs it there, which is
 * precisely what used to push credential hashes into `activity_log`.
 *
 * The package suite does not autoload `App\`, so `stubs/app/Models/{User,Role,
 * Permission}.php` — the trait's only real users — are unreachable from here.
 * Without this stub the trait would ship completely unexercised.
 *
 * Backed by the `audited_records` table, created inline by
 * tests/Feature/Logs/ActivityLogSecretRedactionTest.php.
 */
class AuditedFillableModel extends Model
{
    use HasActivityLogging;

    protected $table = 'audited_records';

    /**
     * `password` is deliberately fillable — see the class docblock.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'webhook_secret',
    ];
}
