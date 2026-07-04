import { describe, it, expect } from 'vitest';
import { DB } from '../core';

// ── DatatableBuilder chain — column visibility / lock, route requirement ──────

describe('DB.column() — visible / locked', () => {
    it('is visible and unlocked by default', () => {
        const col = DB.column().key('name').build();

        expect(col.visible).toBeUndefined();
        expect(col.locked).toBeUndefined();
    });

    it('.hidden() starts the column hidden (enable-able from the column menu)', () => {
        const col = DB.column().key('email').hidden().build();

        expect(col.visible).toBe(false);
    });

    it('.visible(false) explicitly hides the column', () => {
        const col = DB.column().key('created_at').visible(false).build();

        expect(col.visible).toBe(false);
    });

    it('.locked() pins the column so it cannot be hidden from the column menu', () => {
        const col = DB.column().key('id').locked().build();

        expect(col.locked).toBe(true);
    });

    it('throws without a key', () => {
        expect(() => DB.column().label('No key').build()).toThrow('Column must have a key');
    });
});

describe('DB.table() — route + columns', () => {
    it('requires a route to build', () => {
        expect(() => DB.table().build()).toThrow('DataTable must have a route');
    });

    it('addColumns() carries built column configs through', () => {
        const cfg = DB.table()
            .route('/api/admin/users/dt')
            .addColumns(DB.column().key('id').locked(), DB.column().key('email').hidden())
            .build();

        expect(cfg.columns).toHaveLength(2);
        expect(cfg.columns[0].locked).toBe(true);
        expect(cfg.columns[1].visible).toBe(false);
    });
});
