// resources/js/datatable/index.ts

export type {
    DataTableConfig,
    ColumnConfig,
    FilterConfig,
    ActionConfig,
    MenuActionConfig,
    FilterOption,
    FilterType,
    FilterPlacement,
    ActionSeverity,
    TagColor,
    DataTableResponse,
} from './types';
export { TableBuilder, ColumnBuilder, FilterBuilder, ActionBuilder, MenuActionBuilder } from './builder';

import { TableBuilder, ColumnBuilder, FilterBuilder, ActionBuilder, MenuActionBuilder } from './builder';

/**
 * DataTable builder — fluent API for configuring the <DataTable> component.
 *
 * @example
 * const config = DB.table<UserDTO>()
 *   .route('/api/admin/users/dt')
 *   .searchable(true)
 *   .sortable(true)
 *   .pagination(true)
 *   .perPage(15)
 *   .addColumns(
 *     DB.column().label('Full Name').key('full_name'),
 *     DB.column().label('Role').key('role.name').sortable(false),
 *   )
 *   .addFilters(
 *     DB.filter().key('status').label('Status').options([
 *       { label: 'Active', value: 'active' },
 *       { label: 'Inactive', value: 'inactive' },
 *     ]),
 *   )
 *   .addActions(
 *     DB.action<UserDTO>()
 *       .icon('pi pi-eye')
 *       .severity('info')
 *       .tooltip('View')
 *       .handle((row) => router.visit(`/admin/users/${row.id}`)),
 *   )
 *   .build();
 */
export const DB = {
    table: <T = unknown>() => new TableBuilder<T>(),
    column: <T = unknown>() => new ColumnBuilder<T>(),
    filter: () => new FilterBuilder(),
    action: <T = unknown>() => new ActionBuilder<T>(),
    menuAction: <T = unknown>() => new MenuActionBuilder<T>(),
};
