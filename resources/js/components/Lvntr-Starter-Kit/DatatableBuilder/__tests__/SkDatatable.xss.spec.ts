import { describe, expect, it } from 'vitest';
import { DB } from '../core';
import { escapeHtml } from '../core/escapeHtml';

describe('escapeHtml', () => {
    it('escapes HTML-significant characters', () => {
        expect(escapeHtml(`<>&"'`)).toBe('&lt;&gt;&amp;&quot;&#039;');
    });

    it('turns an XSS payload into inert text', () => {
        const escaped = escapeHtml('<img src=x onerror="globalThis.__xss = true">');

        expect(escaped).toContain('&lt;img');
        expect(escaped).not.toContain('<img');
        expect(escaped).not.toContain('<');
        expect(escaped).not.toContain('>');
    });

    it('stringifies non-string values without throwing', () => {
        expect([escapeHtml(null), escapeHtml(undefined), escapeHtml(42), escapeHtml({ nested: true })]).toEqual([
            '',
            '',
            '42',
            '[object Object]',
        ]);
    });
});

describe('ColumnBuilder custom renderer escaping', () => {
    it('uses the supplied escape helper for user-controlled values', () => {
        const column = DB.column<{ name: string }>()
            .key('name')
            .render((row, escape) => escape(row.name))
            .build();
        const payload = '<img src=x onerror="globalThis.__xss = true">';

        expect(column.render).toBeTypeOf('function');

        const rendered = column.render?.({ name: payload }, escapeHtml);

        expect(rendered).toContain('&lt;img');
        expect(rendered).not.toContain('<img');
    });
});
