import { usePage } from '@inertiajs/vue3';

export interface EnumItem {
    value: string | number;
    label: string;
    severity: string | null;
    icon?: string | null;
}

export type EnumKey = 'userStatus' | 'identityType' | 'yesNo' | (string & {});

type EnumStore = Record<string, EnumItem[]>;

/**
 * Access PHP enum data from Inertia shared props.
 *
 * Only enums marked with `#[InertiaShared]` are available here.
 * For DB-based definitions, use `useDefinition()` instead.
 *
 * Usage:
 *   const { list, find, options } = useEnum();
 *
 *   const statuses = list('userStatus');
 *   const active = find('userStatus', 'active');
 *   const opts = options('userStatus'); // for Select / filter dropdowns
 */
export function useEnum() {
    const page = usePage<{ enums?: EnumStore }>();

    /**
     * Get enum items from Inertia shared props.
     */
    function list(key: EnumKey): EnumItem[] {
        return page.props.enums?.[key] ?? [];
    }

    /**
     * Get enum items formatted as select/filter options.
     */
    function options(key: EnumKey): { label: string; value: string | number }[] {
        return list(key).map((item) => ({
            label: item.label,
            value: item.value,
        }));
    }

    /**
     * Find a single enum item by value.
     */
    function find(key: EnumKey, value: string | number): EnumItem | undefined {
        return list(key).find((item) => String(item.value) === String(value));
    }

    return { list, options, find };
}
