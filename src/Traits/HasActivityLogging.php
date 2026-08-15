<?php

namespace Lvntr\StarterKit\Traits;

use Lvntr\StarterKit\Support\SensitiveLogAttributes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Enables Spatie activity logging on Eloquent models.
 *
 * Add this trait to any model that should be audited.
 * When `config('activitylog.enabled')` is false, Spatie skips recording.
 *
 * Automatically detects whether the model uses $fillable or $guarded
 * and chooses the appropriate logging strategy.
 *
 * ## Credentials are never recorded
 *
 * `logFillable()` / `logUnguarded()` copy whole attribute sets into
 * `activity_log.attribute_changes`. A model that keeps `password` in
 * `$fillable` (the shipped User stub does, because mass assignment needs it)
 * would therefore write the old AND the new password hash into a row that the
 * admin activity-log screen renders. Both strategies are consequently routed
 * through `logExcept()` with the deny list resolved by
 * `resolveExcludedLogAttributes()`:
 *
 *   - every literal name in `SENSITIVE_LOG_ATTRIBUTES`;
 *   - every attribute name of this model that `SensitiveLogAttributes::matches()`
 *     recognises — the same literals plus the
 *     `SENSITIVE_LOG_ATTRIBUTE_SUFFIXES` endings, compared WITHOUT regard to
 *     case, so a `Remember_Token` column is excluded under its real casing.
 *
 * The exclusion composes with both strategies: Spatie merges the
 * fillable/unguarded/explicit sets and only then diffs `excludedAttributes()`
 * out of the result — see `attributesToBeLogged()` in
 * vendor/spatie/laravel-activitylog/src/Models/Concerns/LogsActivity.php:174.
 * Together with `logOnlyDirty()` + `dontLogEmptyChanges()` a password-only
 * update now yields NO activity row at all instead of a row with an empty
 * diff, because the excluded attribute can never reach the dirty filter.
 *
 * ## Extending the deny list
 *
 * Override `sensitiveLogAttributes()` on the model. Trait constants are
 * copied into the using class, so the package list can be reused:
 *
 *     protected function sensitiveLogAttributes(): array
 *     {
 *         return [...static::SENSITIVE_LOG_ATTRIBUTES, 'iban'];
 *     }
 *
 * Override `getActivitylogOptions()` only if the `logExcept()` call is kept:
 * `LogOptions::logExcept()` REPLACES the exclusion list instead of merging it
 * (vendor/spatie/laravel-activitylog/src/Support/LogOptions.php:103), so a
 * rewritten options method silently re-opens the leak.
 */
trait HasActivityLogging
{
    use LogsActivity;

    /**
     * Attribute names that must never be written to the activity log.
     *
     * DELEGATED, not redefined: the values live in
     * `Lvntr\StarterKit\Support\SensitiveLogAttributes` so that the write path
     * (this trait) and the cleanup path (`sk:redact-activity-secrets`, which
     * strips these same names out of legacy rows) can never drift apart. The
     * command cannot read this constant — PHP forbids accessing a trait
     * constant through the trait name — hence the shared class.
     *
     * Kept in sync with the mask in
     * resources/js/pages/Admin/ActivityLogs/components/ActivityLogDetail.vue,
     * which redacts these keys on legacy rows written before this deny list.
     *
     * @var list<string>
     */
    public const SENSITIVE_LOG_ATTRIBUTES = SensitiveLogAttributes::ATTRIBUTES;

    /**
     * Attribute name suffixes that mark a value as a credential.
     *
     * Deliberately narrow. A wider pattern would silently empty legitimate
     * audit fields, and a silently empty audit trail is indistinguishable
     * from a broken one. Delegated to `SensitiveLogAttributes::SUFFIXES` for
     * the same single-definition reason as above.
     *
     * @var list<string>
     */
    public const SENSITIVE_LOG_ATTRIBUTE_SUFFIXES = SensitiveLogAttributes::SUFFIXES;

    /**
     * Default activity-log options.
     *
     * Uses logFillable() for models with $fillable, logUnguarded() for
     * models using $guarded. Records only dirty values, skips empty logs and
     * — on BOTH branches — excludes the resolved sensitive attributes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept($this->resolveExcludedLogAttributes());

        if (! empty($this->getFillable())) {
            return $options->logFillable();
        }

        return $options->logUnguarded();
    }

    /**
     * The literal attribute names this model must never log.
     *
     * Override in a model to extend (or narrow) the package deny list.
     *
     * @return list<string>
     */
    protected function sensitiveLogAttributes(): array
    {
        return static::SENSITIVE_LOG_ATTRIBUTES;
    }

    /**
     * Resolve the concrete attribute names handed to `logExcept()`.
     *
     * `logExcept()` matches by exact name — Spatie diffs the excluded list out
     * of the logged set with a plain value comparison and understands no
     * pattern syntax — so the deny rules are expanded here against the same
     * two sources Spatie itself reads: `getFillable()` (LogsActivity.php:188)
     * and the model's own attribute keys (LogsActivity.php:197). Names that
     * are not actually logged cost nothing: an excluded name that never
     * appears in the logged set is a no-op.
     *
     * The per-candidate decision is delegated to
     * `SensitiveLogAttributes::matches()` — the SAME predicate the cleanup
     * command uses — rather than re-implemented here. An inline
     * `str_ends_with()` loop covered only the suffixes and only in the exact
     * casing of the constant, so a column literally named `Remember_Token`
     * passed both the literal list (handed to Spatie's exact-name matcher in
     * canonical lower case) and the suffix rule, and leaked. `matches()` folds
     * case, so the model's REAL column casing is what gets excluded while the
     * decision stays identical on both sides of the seam.
     *
     * A model that overrides `sensitiveLogAttributes()` gets the same
     * treatment for its own additions: the override list is compared
     * case-insensitively against the candidates too, so `['iban']` also
     * excludes an `IBAN` column. Widening the exclusion can only ever keep
     * MORE credentials out, never fewer.
     *
     * @return list<string>
     */
    protected function resolveExcludedLogAttributes(): array
    {
        $excluded = $this->sensitiveLogAttributes();

        $overrides = array_map(
            // Cast, not a string type hint: `sensitiveLogAttributes()` is a
            // consumer override whose element type is a docblock promise, and
            // a model save is the wrong place to discover it was broken.
            static fn (mixed $name): string => mb_strtolower((string) $name),
            $excluded,
        );

        $candidates = array_merge(
            $this->getFillable(),
            array_keys($this->getAttributes()),
        );

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;

            if (SensitiveLogAttributes::matches($candidate)
                || in_array(mb_strtolower($candidate), $overrides, true)) {
                $excluded[] = $candidate;
            }
        }

        return array_values(array_unique($excluded));
    }
}
