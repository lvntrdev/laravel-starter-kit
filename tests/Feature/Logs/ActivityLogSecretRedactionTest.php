<?php

/*
|--------------------------------------------------------------------------
| Activity log — credentials never reach the audit trail (red-line regression)
|--------------------------------------------------------------------------
|
| `logFillable()` / `logUnguarded()` copy whole attribute sets into
| `activity_log.attribute_changes`. The shipped User stub keeps `password` in
| `$fillable` (mass assignment needs it), so every password change used to write
| the OLD and the NEW bcrypt hash into a row the admin activity-log screen
| renders. Two halves fix that and both are guarded here:
|
|   WRITE PATH   — HasActivityLogging routes BOTH strategies through
|                  Spatie's logExcept(), resolved from SensitiveLogAttributes
|                  (literal names + `_token` / `_secret` suffixes).
|   CLEANUP PATH — sk:redact-activity-secrets strips the same names out of
|                  `attribute_changes` AND `properties` on rows written before
|                  the deny list existed.
|
| The package suite never exercised the trait: its only users are
| stubs/app/Models/{User,Role,Permission}.php, which live in the `App\`
| namespace the package does not autoload. Hence the two purpose-built stubs
| (tests/Stubs/AuditedFillableModel.php, tests/Stubs/AuditedGuardedModel.php) —
| one per branch — over an inline `audited_records` table.
|
| Every secret-shaped value below is a synthetic placeholder. Nothing here is,
| or resembles, a real credential.
|
*/

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Console\Commands\RedactActivityLogSecretsCommand;
use Lvntr\StarterKit\Console\Doctor\Checks\ActivityLogSecretsCheck;
use Lvntr\StarterKit\Support\SensitiveLogAttributes;
use Lvntr\StarterKit\Tests\Stubs\AuditedFillableModel;
use Lvntr\StarterKit\Tests\Stubs\AuditedGuardedModel;
use Spatie\Activitylog\Models\Activity;

/** Synthetic stand-ins — never a real credential. */
const REDACT_PLACEHOLDER = 'SYNTHETIC-PLACEHOLDER-NOT-A-CREDENTIAL';
const REDACT_PLACEHOLDER_OLD = 'SYNTHETIC-PLACEHOLDER-PREVIOUS-VALUE';

beforeEach(function (): void {
    // Mirrors database/migrations/2026_03_11_071628_create_activity_log_table.php
    // as widened by 2026_06_20_000000_widen_activity_log_morphs_to_string.php
    // (char(36) morph ids so both uuid and numeric keys fit one column).
    Schema::create('activity_log', function (Blueprint $table): void {
        $table->id();
        $table->string('log_name')->nullable()->index();
        $table->text('description');
        $table->string('subject_type')->nullable();
        $table->char('subject_id', 36)->nullable();
        $table->string('event')->nullable();
        $table->string('causer_type')->nullable();
        $table->char('causer_id', 36)->nullable();
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();

        $table->index(['subject_type', 'subject_id'], 'subject');
        $table->index(['causer_type', 'causer_id'], 'causer');
    });

    Schema::create('audited_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('password')->nullable();
        $table->string('remember_token')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        // Suffix-rule columns: neither name is on the literal deny list, both
        // must be excluded by `_token` / `_secret`.
        $table->string('api_token')->nullable();
        $table->string('webhook_secret')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('audited_records');
    Schema::dropIfExists('activity_log');
});

/**
 * Every raw JSON payload written to activity_log, as stored.
 *
 * Asserted on the RAW column rather than through the Activity model's
 * collection casts: the leak is the bytes on disk, and a cast could hide a key
 * the UI would still render.
 *
 * @return list<string>
 */
function auditRawPayloads(): array
{
    $payloads = [];

    foreach (DB::table('activity_log')->get(['attribute_changes', 'properties']) as $row) {
        $payloads[] = (string) ($row->attribute_changes ?? '');
        $payloads[] = (string) ($row->properties ?? '');
    }

    return $payloads;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function auditedAttributes(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Ada',
        'password' => REDACT_PLACEHOLDER,
        'remember_token' => REDACT_PLACEHOLDER,
        'two_factor_secret' => REDACT_PLACEHOLDER,
        'two_factor_recovery_codes' => REDACT_PLACEHOLDER,
        'api_token' => REDACT_PLACEHOLDER,
        'webhook_secret' => REDACT_PLACEHOLDER,
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Write path — $fillable branch (logFillable)
// ──────────────────────────────────────────────────────────────────────────────

it('writes no activity row at all for a password-only update', function (): void {
    $model = AuditedFillableModel::create(auditedAttributes());

    $before = Activity::query()->count();

    $model->update(['password' => REDACT_PLACEHOLDER_OLD]);

    // logOnlyDirty + dontLogEmptyChanges + an excluded attribute means the
    // dirty diff is empty, so there is no row — not even one with an empty
    // diff. The write itself must still have happened.
    expect(Activity::query()->count())->toBe($before)
        ->and($model->fresh()->password)->toBe(REDACT_PLACEHOLDER_OLD);
});

it('keeps every sensitive key out of a created row while the ordinary keys survive', function (): void {
    $model = AuditedFillableModel::create(auditedAttributes(['name' => 'Grace']));

    $activity = Activity::query()->latest('id')->firstOrFail();
    $changes = json_decode((string) DB::table('activity_log')->find($activity->id)->attribute_changes, true);

    // Only the one non-sensitive fillable column survives the deny list.
    expect($changes['attributes'])->toBe(['name' => 'Grace'])
        ->and($activity->event)->toBe('created')
        ->and((int) $activity->subject_id)->toBe($model->getKey());

    foreach (auditRawPayloads() as $payload) {
        expect($payload)->not->toContain(REDACT_PLACEHOLDER);

        foreach (SensitiveLogAttributes::ATTRIBUTES as $attribute) {
            expect($payload)->not->toContain('"'.$attribute.'"');
        }
    }
});

it('excludes suffix-matched attribute names, not just the literal deny list', function (): void {
    AuditedFillableModel::create(auditedAttributes());

    $changes = json_decode((string) DB::table('activity_log')->latest('id')->first()->attribute_changes, true);

    // api_token / webhook_secret are on no literal list — only `_token` and
    // `_secret` keep them out.
    expect(array_keys($changes['attributes']))->not->toContain('api_token')
        ->and(array_keys($changes['attributes']))->not->toContain('webhook_secret');
});

it('does not snapshot the credential into old when the record is deleted', function (): void {
    $model = AuditedFillableModel::create(auditedAttributes());
    $model->delete();

    $deleted = Activity::query()->where('event', 'deleted')->latest('id')->firstOrFail();
    $changes = json_decode((string) DB::table('activity_log')->find($deleted->id)->attribute_changes, true) ?? [];

    foreach (['attributes', 'old'] as $side) {
        foreach (array_keys($changes[$side] ?? []) as $key) {
            expect(SensitiveLogAttributes::matches((string) $key))->toBeFalse("delete snapshot leaked [{$key}] in [{$side}]");
        }
    }

    foreach (auditRawPayloads() as $payload) {
        expect($payload)->not->toContain(REDACT_PLACEHOLDER);
    }
});

it('still records a genuine change so the audit trail is not silently emptied', function (): void {
    // The deny list is narrow on purpose: an audit trail that logs nothing is
    // indistinguishable from a broken one.
    $model = AuditedFillableModel::create(auditedAttributes());

    $model->update(['name' => 'Grace', 'password' => REDACT_PLACEHOLDER_OLD]);

    $updated = Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();
    $changes = json_decode((string) DB::table('activity_log')->find($updated->id)->attribute_changes, true);

    expect($changes['attributes'])->toBe(['name' => 'Grace'])
        ->and($changes['old'])->toBe(['name' => 'Ada']);
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Write path — $guarded branch (logUnguarded)
//
// The wider of the two: logUnguarded logs EVERY attribute key, so the deny list
// has to compose with it as well.
// ──────────────────────────────────────────────────────────────────────────────

it('keeps sensitive keys out of an unguarded model on create', function (): void {
    AuditedGuardedModel::create(auditedAttributes(['name' => 'Hedy']));

    $changes = json_decode((string) DB::table('activity_log')->latest('id')->first()->attribute_changes, true);

    expect($changes['attributes'])->toHaveKey('name');

    foreach (array_keys($changes['attributes']) as $key) {
        expect(SensitiveLogAttributes::matches((string) $key))->toBeFalse("unguarded create leaked [{$key}]");
    }

    foreach (auditRawPayloads() as $payload) {
        expect($payload)->not->toContain(REDACT_PLACEHOLDER);
    }
});

it('keeps sensitive keys out of an unguarded model on update and delete', function (): void {
    $model = AuditedGuardedModel::create(auditedAttributes());

    $model->update(['password' => REDACT_PLACEHOLDER_OLD, 'api_token' => REDACT_PLACEHOLDER_OLD]);
    $model->delete();

    foreach (DB::table('activity_log')->get() as $row) {
        $decoded = json_decode((string) $row->attribute_changes, true) ?? [];

        foreach (['attributes', 'old'] as $side) {
            foreach (array_keys($decoded[$side] ?? []) as $key) {
                expect(SensitiveLogAttributes::matches((string) $key))->toBeFalse("unguarded row {$row->id} leaked [{$key}]");
            }
        }
    }

    foreach (auditRawPayloads() as $payload) {
        expect($payload)->not->toContain(REDACT_PLACEHOLDER)
            ->and($payload)->not->toContain(REDACT_PLACEHOLDER_OLD);
    }
});

it('resolves the deny list for both strategies from the same canonical source', function (): void {
    // If the trait's constants ever stop delegating to SensitiveLogAttributes,
    // the write path and sk:redact-activity-secrets drift apart silently.
    expect(AuditedFillableModel::SENSITIVE_LOG_ATTRIBUTES)->toBe(SensitiveLogAttributes::ATTRIBUTES)
        ->and(AuditedGuardedModel::SENSITIVE_LOG_ATTRIBUTE_SUFFIXES)->toBe(SensitiveLogAttributes::SUFFIXES);

    $excluded = new ReflectionMethod(AuditedFillableModel::class, 'resolveExcludedLogAttributes')
        ->invoke(new AuditedFillableModel);

    foreach ([...SensitiveLogAttributes::ATTRIBUTES, 'api_token', 'webhook_secret'] as $expected) {
        expect($excluded)->toContain($expected);
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Cleanup path — sk:redact-activity-secrets on legacy rows
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A row shaped like one written BEFORE the deny list existed: credentials in
 * `attribute_changes` (spatie 5.x diff column) AND in `properties` (free-form
 * withProperties bag, plus the 4.x diff location).
 */
function seedLegacyActivityRow(): int
{
    return (int) DB::table('activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => 'updated',
        'subject_type' => 'legacy_subject',
        'subject_id' => '7',
        'event' => 'updated',
        'attribute_changes' => json_encode([
            'attributes' => [
                'name' => 'Ada',
                'password' => REDACT_PLACEHOLDER,
                'api_token' => REDACT_PLACEHOLDER,
            ],
            'old' => [
                'name' => 'Grace',
                'password' => REDACT_PLACEHOLDER_OLD,
                'two_factor_secret' => REDACT_PLACEHOLDER_OLD,
            ],
        ]),
        'properties' => json_encode([
            'ip' => '203.0.113.9',
            'meta' => [
                'keep' => 'yes',
                'webhook_secret' => REDACT_PLACEHOLDER,
            ],
            'items' => [
                ['label' => 'first', 'two_factor_recovery_codes' => REDACT_PLACEHOLDER],
                ['label' => 'second'],
            ],
            // Case variant: the cleanup path walks free-form JSON, where
            // `Password` is as plausible as `password`.
            'Password' => REDACT_PLACEHOLDER,
            // Everything under this key is sensitive — it must survive as an
            // empty OBJECT, not flip to a JSON array.
            'nested_bag' => ['password' => REDACT_PLACEHOLDER],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('strips credentials from both JSON columns of a legacy row while its other keys survive', function (): void {
    $id = seedLegacyActivityRow();

    $this->artisan('sk:redact-activity-secrets')->assertSuccessful();

    $row = DB::table('activity_log')->find($id);

    $changes = json_decode((string) $row->attribute_changes, true);
    $properties = json_decode((string) $row->properties, true);

    expect($changes)->toBe([
        'attributes' => ['name' => 'Ada'],
        'old' => ['name' => 'Grace'],
    ]);

    expect($properties)->toBe([
        'ip' => '203.0.113.9',
        'meta' => ['keep' => 'yes'],
        'items' => [
            ['label' => 'first'],
            ['label' => 'second'],
        ],
        'nested_bag' => [],
    ]);

    // The bytes, not just the decoded shape.
    expect((string) $row->attribute_changes)->not->toContain(REDACT_PLACEHOLDER)
        ->and((string) $row->attribute_changes)->not->toContain(REDACT_PLACEHOLDER_OLD)
        ->and((string) $row->properties)->not->toContain(REDACT_PLACEHOLDER)
        // An emptied sub-object stays an object; round-tripping through arrays
        // would rewrite it as `[]` and change what the UI receives.
        ->and((string) $row->properties)->toContain('"nested_bag":{}');
});

it('is idempotent — a second run rewrites nothing', function (): void {
    $id = seedLegacyActivityRow();

    $first = RedactActivityLogSecretsCommand::redact();
    $after = DB::table('activity_log')->find($id);

    $second = RedactActivityLogSecretsCommand::redact();

    expect($first['redacted'])->toBe(1)
        ->and($first['columns'])->toBe(['attribute_changes', 'properties'])
        ->and($second['redacted'])->toBe(0)
        ->and($second['scanned'])->toBe(0) // pre-filter no longer selects the row
        ->and(DB::table('activity_log')->find($id)->attribute_changes)->toBe($after->attribute_changes)
        ->and(DB::table('activity_log')->find($id)->properties)->toBe($after->properties);
});

it('leaves a row that carries no credential completely untouched', function (): void {
    $id = (int) DB::table('activity_log')->insertGetId([
        'log_name' => 'audit',
        'description' => 'Settings updated',
        'event' => 'updated',
        'attribute_changes' => json_encode(['attributes' => ['app_name' => 'Kit'], 'old' => ['app_name' => 'Old']]),
        'properties' => json_encode(['group' => 'general', 'keys' => ['app_name']]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = DB::table('activity_log')->find($id);

    $stats = RedactActivityLogSecretsCommand::redact(scanEveryRow: true);

    $after = DB::table('activity_log')->find($id);

    expect($stats['redacted'])->toBe(0)
        ->and($after->attribute_changes)->toBe($before->attribute_changes)
        ->and($after->properties)->toBe($before->properties);
});

it('reports without writing under --dry-run', function (): void {
    $id = seedLegacyActivityRow();
    $before = DB::table('activity_log')->find($id);

    $this->artisan('sk:redact-activity-secrets --dry-run')->assertSuccessful();

    $after = DB::table('activity_log')->find($id);

    expect($after->attribute_changes)->toBe($before->attribute_changes)
        ->and($after->properties)->toBe($before->properties);
});

/*
|--------------------------------------------------------------------------
| The sk:doctor probe — bounded, and never collation-dependent
|--------------------------------------------------------------------------
|
| ActivityLogSecretsCheck used to call redact(dryRun: true) with the default
| pre-filter, and that made it lie in one direction and hang in the other:
|
|   FALSE OK  — the pre-filter builds lowercase LIKE patterns, and on
|               MySQL/MariaDB a JSON column compares case-sensitively, so a row
|               whose only sensitive key is `Password` was skipped and the check
|               reported OK over a readable credential.
|   NO BOUND  — off the pre-filter drivers (PostgreSQL and anything else) the
|               same call decodes EVERY row of the biggest table in the app,
|               inside a five-second doctor budget.
|
| Both are gone because the probe filters NOTHING in SQL — it reads a fixed
| window ordered by the primary key and decides in PHP, through the same walk
| the redactor uses, whose matcher folds case. That is why the tests below
| assert on `scanned`: a probe that narrowed its window by key name would show
| it there immediately.
|
| The SQLite caveat is worth stating, since it is why the mixed-case test alone
| would not have caught the bug: SQLite's LIKE is ASCII case-INsensitive, so the
| pre-filter happens to match `Password` here and the old code passes a purely
| behavioural mixed-case assertion. The structural assertion (no SQL filter at
| all, on any driver) is the one that actually holds the line.
|
*/

/** Seed a row whose ONLY credential sits under a mixed-case key. */
function seedMixedCaseCredentialRow(): int
{
    return (int) DB::table('activity_log')->insertGetId([
        'log_name' => 'audit',
        'description' => 'Legacy import',
        'event' => 'updated',
        'properties' => json_encode(['ip' => '203.0.113.9', 'Password' => REDACT_PLACEHOLDER]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Seed a row that carries nothing sensitive under any casing. */
function seedCleanActivityRow(string $description = 'Settings updated'): int
{
    return (int) DB::table('activity_log')->insertGetId([
        'log_name' => 'audit',
        'description' => $description,
        'event' => 'updated',
        'attribute_changes' => json_encode(['attributes' => ['app_name' => 'Kit'], 'old' => ['app_name' => 'Old']]),
        'properties' => json_encode(['ip' => '203.0.113.9', 'meta' => ['keep' => 'yes']]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('fails the doctor check when the only sensitive key is mixed-case', function (): void {
    seedCleanActivityRow();
    seedMixedCaseCredentialRow();

    $report = (new ActivityLogSecretsCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('activity_log')
        ->and($report->hint)->toContain('sk:redact-activity-secrets');
});

it('decides in PHP, so no key-name filter narrows what the probe reads', function (): void {
    // Three rows the pre-filter's LIKE patterns cannot match, one it can. A
    // probe that still asked the database to pick candidates would report
    // scanned = 1; reading all four is what makes the decision independent of
    // the column's collation.
    seedCleanActivityRow('first');
    seedCleanActivityRow('second');
    seedCleanActivityRow('third');
    seedMixedCaseCredentialRow();

    $stats = RedactActivityLogSecretsCommand::probe();

    expect($stats['scanned'])->toBe(4)
        ->and($stats['dirty'])->toBe(1)
        ->and($stats['exhaustive'])->toBeTrue()
        ->and($stats['table'])->toBe('activity_log')
        ->and($stats['columns'])->toBe(['attribute_changes', 'properties']);
});

it('stops at the row limit and reports the finding as a floor, not a total', function (): void {
    seedMixedCaseCredentialRow();
    seedMixedCaseCredentialRow();
    seedCleanActivityRow();
    seedCleanActivityRow();

    $stats = RedactActivityLogSecretsCommand::probe(limit: 2);

    // The bound is the point: two rows read out of four, on every driver.
    expect($stats['scanned'])->toBe(2)
        ->and($stats['dirty'])->toBe(2)
        ->and($stats['exhaustive'])->toBeFalse();
});

it('never claims a clean bill of health it did not measure', function (): void {
    seedCleanActivityRow('first');
    seedCleanActivityRow('second');
    seedCleanActivityRow('third');

    $exhaustive = RedactActivityLogSecretsCommand::probe();
    $bounded = RedactActivityLogSecretsCommand::probe(limit: 2);

    expect($exhaustive['exhaustive'])->toBeTrue()
        ->and($exhaustive['dirty'])->toBe(0)
        ->and($bounded['exhaustive'])->toBeFalse()
        ->and($bounded['dirty'])->toBe(0);

    // Having seen the whole table, the check may speak in absolutes; having
    // seen a window, it must name the window instead.
    $report = (new ActivityLogSecretsCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('all 3 row(s) inspected');
});

it('writes nothing while probing', function (): void {
    $id = seedLegacyActivityRow();
    $before = DB::table('activity_log')->find($id);

    (new ActivityLogSecretsCheck)->run();
    RedactActivityLogSecretsCommand::probe();

    $after = DB::table('activity_log')->find($id);

    expect($after->attribute_changes)->toBe($before->attribute_changes)
        ->and($after->properties)->toBe($before->properties);
});

it('keeps reporting OK when the activity log table does not exist', function (): void {
    Schema::drop('activity_log');

    $report = (new ActivityLogSecretsCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('nothing that could hold a credential');
});

it('warns instead of passing when a payload cannot be decoded', function (): void {
    DB::table('activity_log')->insert([
        'log_name' => 'audit',
        'description' => 'Corrupt payload',
        'event' => 'updated',
        'properties' => '{not valid json',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = (new ActivityLogSecretsCheck)->run();

    // Undecodable is not clean — it is unknown, and unknown never reports OK.
    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('could not be decoded');
});
