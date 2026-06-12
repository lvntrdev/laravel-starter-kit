// resources/js/composables/index.ts
// Single entry point for composables.
//
// Kit composables run from the vendor package and are re-exported here through
// the `@/composables/<name>` alias, which resolves to a local copy when present
// (published via `php artisan sk:publish --tag=composables` or customized) and
// otherwise falls back to the vendor copy. Only `useAdminMenu` ships locally —
// it depends on the consumer's generated `@/routes/*` and is meant to be edited.
export { useAdminMenu } from './useAdminMenu';

export { useSidebar } from '@/composables/useSidebar';
export { useMenuBuilder } from '@/composables/useMenuBuilder';
export { useDarkMode } from '@/composables/useDarkMode';
export { useAccentColor, ACCENT_COLORS, ACCENT_SWATCH, SIDEBAR_STYLES } from '@/composables/useAccentColor';
export type { AccentColor, SidebarStyle } from '@/composables/useAccentColor';
export { useAppearanceDefaults } from '@/composables/useAppearanceDefaults';
export { useFlash } from '@/composables/useFlash';
export { useConfirm } from '@/composables/useConfirm';
export { useApi } from '@/composables/useApi';
export type { ApiEnvelope, ApiError } from '@/composables/useApi';
export { useDialog } from '@/composables/useDialog';
export { useRefreshBus } from '@/composables/useRefreshBus';
export { useUrlTab } from '@/composables/useUrlTab';
export type { TabDefinition } from '@/composables/useUrlTab';
export { useCan } from '@/composables/useCan';
export { useDefinition } from '@/composables/useDefinition';
export type { EnumItem, DefinitionKey, DefinitionFilter } from '@/composables/useDefinition';
export { usePageLoading } from '@/composables/usePageLoading';
export { useDatatableSelection } from '@/composables/useDatatableSelection';
export type { BulkSelectionMode, BulkActionResult, BulkActionPayload } from '@/composables/useDatatableSelection';
