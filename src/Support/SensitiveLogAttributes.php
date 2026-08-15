<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Support;

/**
 * The single, canonical deny list of attribute names that must never end up in
 * an activity-log row.
 *
 * Two consumers read this class, and they must never drift apart:
 *
 *  - the WRITE path — `Lvntr\StarterKit\Traits\HasActivityLogging`, whose
 *    `SENSITIVE_LOG_ATTRIBUTES` / `SENSITIVE_LOG_ATTRIBUTE_SUFFIXES` constants
 *    delegate to `ATTRIBUTES` / `SUFFIXES` below and feed Spatie's
 *    `logExcept()`, so no NEW row can carry a credential;
 *  - the CLEANUP path — `sk:redact-activity-secrets`
 *    (`Lvntr\StarterKit\Console\Commands\RedactActivityLogSecretsCommand`),
 *    which strips the same names out of rows written BEFORE the deny list
 *    existed.
 *
 * The cleanup path cannot read the trait's constants: PHP forbids accessing a
 * trait constant through the trait name (`Cannot access trait constant ...
 * directly`), and reaching for an arbitrary using-class instead would make the
 * redaction depend on which models happen to be installed. Hence a plain class
 * as the one definition, and the trait delegating to it.
 *
 * Also mirrored — by hand, because it lives in another language — by the mask
 * in `resources/js/pages/Admin/ActivityLogs/components/ActivityLogDetail.vue`.
 * Any change here needs the same change there.
 *
 * The list is deliberately NARROW. Widening it silently empties legitimate
 * audit fields, and a silently empty audit trail is indistinguishable from a
 * broken one. Wildcards are limited to the two suffixes below.
 */
final class SensitiveLogAttributes
{
    /**
     * Literal attribute names that are always credentials.
     *
     * @var list<string>
     */
    public const ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Attribute name suffixes that mark a value as a credential.
     *
     * @var list<string>
     */
    public const SUFFIXES = [
        '_token',
        '_secret',
    ];

    /**
     * Does this attribute/property key name denote a credential?
     *
     * Matching is case-insensitive, and BOTH paths route through here.
     *
     * The cleanup path walks free-form JSON written by arbitrary
     * `withProperties()` callers, where `Password` is as plausible as
     * `password`. The write path hands Spatie's exact-name `logExcept()` a list
     * of concrete names, so it resolves that list by asking this predicate
     * about each of the model's REAL attribute names — a `Remember_Token`
     * column is thereby excluded under its own casing. Folding case can only
     * ever keep MORE credentials out, never fewer.
     */
    public static function matches(string $key): bool
    {
        $key = mb_strtolower($key);

        if (in_array($key, self::ATTRIBUTES, true)) {
            return true;
        }

        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
