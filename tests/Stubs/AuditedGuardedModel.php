<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Traits\HasActivityLogging;

/**
 * Test-only audited model exercising the `$guarded` branch of
 * {@see HasActivityLogging} (Spatie's `logUnguarded()`).
 *
 * The trait picks its strategy on `getFillable()` being empty, so a model that
 * declares `$guarded` instead takes the other branch — and that branch logs
 * EVERY attribute key, not just a curated list, which makes it the wider of the
 * two leaks. The deny list has to compose with both; this stub is the second
 * half of that proof.
 *
 * Shares the `audited_records` table with {@see AuditedFillableModel}.
 */
class AuditedGuardedModel extends Model
{
    use HasActivityLogging;

    protected $table = 'audited_records';

    /**
     * Only the key is protected — every other column is unguarded and therefore
     * a logging candidate.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];
}
