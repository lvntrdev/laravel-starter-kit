import { describe, expect, it } from 'vitest';
import { resolveEffectiveAccent } from '../useAccentColor';

// ── Effective accent resolution ──────────────────────────────────────────────
//
// `'default'` is overloaded: in the Görünüm tab it literally means kit-blue (the
// admin DEFINES the default there); everywhere else it means "follow the admin
// global default". A user who never picks a colour — or who picks "Varsayılan" —
// must render the admin's chosen Varsayılan Renk, not the hardcoded blue.

describe('resolveEffectiveAccent()', () => {
    it('follows the admin global default when the user value is "default"', () => {
        expect(resolveEffectiveAccent('default', 'green')).toBe('green');
    });

    it('falls back to "default" (kit blue) when the admin default is itself "default"', () => {
        expect(resolveEffectiveAccent('default', 'default')).toBe('default');
    });

    it('falls back to "default" when the admin global is missing/invalid', () => {
        expect(resolveEffectiveAccent('default', undefined)).toBe('default');
        expect(resolveEffectiveAccent('default', 'not-a-color')).toBe('default');
    });

    it('keeps an explicit personal colour (override wins over the global)', () => {
        expect(resolveEffectiveAccent('red', 'green')).toBe('red');
    });

    it('treats "default" literally when followGlobal is false (Görünüm preview)', () => {
        expect(resolveEffectiveAccent('default', 'green', false)).toBe('default');
    });
});
