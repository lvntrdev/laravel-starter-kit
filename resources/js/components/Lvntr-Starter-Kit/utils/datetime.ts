import { usePage } from '@inertiajs/vue3';
import { getActiveLanguage } from 'laravel-vue-i18n';

export type DateTimeValue = Date | string | null | undefined;

export interface DateTimeFormatOptions extends Omit<Intl.DateTimeFormatOptions, 'timeZone'> {
    timeZone?: string | null;
}

interface SharedDateTimeProps {
    locale?: unknown;
    timezone?: unknown;
}

const LEGACY_DATE_TIME_PATTERN = /^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}$/;

function nonEmptyString(value: unknown): string | undefined {
    if (typeof value !== 'string') return undefined;

    const trimmed = value.trim();
    return trimmed === '' ? undefined : trimmed;
}

function readSharedProps(): SharedDateTimeProps {
    try {
        const page = usePage();
        const props = page?.props as Record<string, unknown> | undefined;

        return {
            locale: props?.locale,
            timezone: props?.timezone,
        };
    } catch {
        return {};
    }
}

function browserTimeZone(): string | undefined {
    try {
        return nonEmptyString(Intl.DateTimeFormat().resolvedOptions().timeZone);
    } catch {
        return undefined;
    }
}

function isValidTimeZone(timeZone: string): boolean {
    try {
        new Intl.DateTimeFormat(undefined, { timeZone });
        return true;
    } catch {
        return false;
    }
}

function resolveTimeZone(explicitTimeZone: unknown, sharedTimeZone: unknown): string {
    const candidates = [nonEmptyString(explicitTimeZone), nonEmptyString(sharedTimeZone), browserTimeZone(), 'UTC'];

    return candidates.find((candidate): candidate is string => Boolean(candidate && isValidTimeZone(candidate))) ?? 'UTC';
}

function isValidLocale(locale: string): boolean {
    try {
        new Intl.DateTimeFormat(locale);
        return true;
    } catch {
        return false;
    }
}

function resolveLocale(sharedLocale: unknown): string | undefined {
    const pageLocale = nonEmptyString(sharedLocale);
    if (pageLocale && isValidLocale(pageLocale)) return pageLocale;

    try {
        const activeLanguage = nonEmptyString(getActiveLanguage());
        return activeLanguage && isValidLocale(activeLanguage) ? activeLanguage : undefined;
    } catch {
        return undefined;
    }
}

function format(
    value: DateTimeValue,
    defaults: Intl.DateTimeFormatOptions,
    options: DateTimeFormatOptions = {},
): string {
    if (value == null) return '';
    if (typeof value === 'string' && LEGACY_DATE_TIME_PATTERN.test(value)) return value;

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const sharedProps = readSharedProps();
    const { timeZone: explicitTimeZone, ...formatOptions } = options;
    const timeZone = resolveTimeZone(explicitTimeZone, sharedProps.timezone);
    const locale = resolveLocale(sharedProps.locale);
    const localizedOptions =
        formatOptions.dateStyle !== undefined || formatOptions.timeStyle !== undefined
            ? formatOptions
            : { ...defaults, ...formatOptions };

    return new Intl.DateTimeFormat(locale, {
        ...localizedOptions,
        timeZone,
    }).format(date);
}

export function formatDateTime(value: DateTimeValue, options: DateTimeFormatOptions = {}): string {
    return format(
        value,
        { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' },
        options,
    );
}

export function formatDate(value: DateTimeValue, options: DateTimeFormatOptions = {}): string {
    return format(value, { year: 'numeric', month: 'short', day: 'numeric' }, options);
}

export function formatTime(value: DateTimeValue, options: DateTimeFormatOptions = {}): string {
    return format(value, { hour: '2-digit', minute: '2-digit' }, options);
}
