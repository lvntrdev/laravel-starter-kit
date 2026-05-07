// resources/js/components/Lvntr-Starter-Kit/DatatableBuilder/selection.ts
// Re-exported type for SkDatatable's optional `selection` prop.
// The composable lives in the consumer's stubs/resources/js/composables/useDatatableSelection.ts

import type { Ref, ComputedRef } from 'vue';

/**
 * Return type contract for useDatatableSelection composable.
 * SkDatatable accepts this as an optional `selection` prop.
 * When omitted the table behaves exactly as before (opt-in, no regression).
 */
export interface UseDatatableSelectionReturn {
    selectedIds: Ref<Set<string | number>>;
    selectionMode: Ref<'page' | 'all'>;
    submitting: Ref<boolean>;
    selectedCount: ComputedRef<number>;
    hasSelection: ComputedRef<boolean>;
    isAllFilteredMode: ComputedRef<boolean>;
    toggleRow: (row: unknown) => void;
    isRowSelected: (row: unknown) => boolean;
    togglePageSelection: (rows: unknown[], selected: boolean) => void;
    isPageFullySelected: (rows: unknown[]) => boolean;
    isPagePartiallySelected: (rows: unknown[]) => boolean;
    selectAllFiltered: () => void;
    clearSelection: () => void;
    executeBulkAction: (action: string, filterSnapshot?: Record<string, unknown>, bulkRoute?: string) => void;
}
