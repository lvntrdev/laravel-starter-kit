// resources/js/datatable/types.ts

export type FilterOption = {
    label: string;
    value: string | number | null;
    /** Optional record count shown as a chip in inline filter pills/menus. */
    count?: number;
    /** Optional dot color (any CSS color) shown next to the option in pill menus. */
    color?: string | null;
};

export type FilterType = 'select' | 'select-button' | 'date' | 'daterange';

export type FilterPlacement = 'inline' | 'panel';

export type ActionSeverity = 'primary' | 'secondary' | 'success' | 'info' | 'warn' | 'danger' | 'contrast';

export type ButtonSize = 'small' | 'large';

export type ButtonVariant = 'outlined' | 'text';

export type TagSeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast';

export type TagColor =
    | 'red'
    | 'orange'
    | 'amber'
    | 'yellow'
    | 'lime'
    | 'green'
    | 'emerald'
    | 'teal'
    | 'cyan'
    | 'sky'
    | 'blue'
    | 'indigo'
    | 'violet'
    | 'purple'
    | 'fuchsia'
    | 'pink'
    | 'rose'
    | 'slate'
    | 'gray'
    | 'zinc'
    | 'neutral'
    | 'stone';

/** PrimeVue Button style options — shared by actions and create button. */
export interface ButtonStyleOptions {
    severity?: ActionSeverity;
    size?: ButtonSize;
    variant?: ButtonVariant;
    rounded?: boolean;
    raised?: boolean;
    text?: boolean;
    outlined?: boolean;
}

export interface ColumnConfig {
    label?: string;
    key: string;
    sortable: boolean;
    /** Initially hidden — the user can enable it from the column menu. Default: true (visible). */
    visible?: boolean;
    /** Locked columns are always visible and cannot be hidden from the column menu. */
    locked?: boolean;
    render?: (row: unknown, escape: (value: unknown) => string) => string;
    /** 'definition': label/severity resolved from DB definitions. 'value': raw cell value, optionally mapped via tagLabels. */
    tag?: 'definition' | 'value';
    tagKey?: string;
    /** Label map for value-mode tags – maps the raw cell value to a display label (e.g. role key → name). */
    tagLabels?: Record<string, string>;
    /** Row property holding the tag severity (e.g. a backend-seeded color column). Overridden by colors(). */
    tagSeverityKey?: string;
    /** Tailwind color map for the tag – keys are matched against the tagKey value. */
    colors?: Record<string, TagColor>;
    /** Icon map – keys are matched against the tagKey value (e.g. { active: 'pi pi-check' }). */
    icons?: Record<string, string>;
    /** Icon position: 'left' (default) or 'right'. */
    tagIconPos?: 'left' | 'right';
    /** Use soft (lighter) tag style. */
    tagSoft?: boolean;
    /** Use rounded (pill) tag style. */
    tagRounded?: boolean;
    /** Use outlined (border only) tag style. */
    tagOutlined?: boolean;
    /** Pin this column so it stays visible while scrolling horizontally. */
    sticky?: boolean;
}

export interface IdColumnConfig {
    /** Set to false to hide the built-in ID column. Default: true. */
    visible?: boolean;
    /** Row property to use as the ID value. Default: 'id'. */
    key?: string;
}

export interface FilterConfig {
    key: string;
    label?: string;
    type: FilterType;
    placement: FilterPlacement;
    options?: FilterOption[];
    /** Load options from the definition system (useDefinition). */
    definitionKey?: string;
    /** Load options from a remote URL (GET → { data: FilterOption[] }). */
    optionsUrl?: string;
    placeholder?: string;
}

export interface ActionConfig<T = unknown> extends ButtonStyleOptions {
    icon: string;
    label?: string;
    tooltip?: string;
    handle: (row: T) => void;
    visible?: (row: T) => boolean;
    disabled?: (row: T) => boolean;
}

export interface MenuActionConfig<T = unknown> {
    label: string;
    icon?: string;
    separator?: boolean;
    handle: (row: T) => void;
    visible?: (row: T) => boolean;
    disabled?: (row: T) => boolean;
}

export interface CreateButtonConfig extends ButtonStyleOptions {
    label?: string;
    icon?: string;
    /** Navigate to this URL (renders as a link). */
    url?: string;
    /** Call this handler (renders as a dialog trigger). */
    onClick?: () => void;
}

export interface MenuButtonConfig extends ButtonStyleOptions {
    icon?: string;
}

/**
 * Column descriptor sent by the backend (DatatableQueryBuilder::columns()).
 * Lets the server expose columns that are initially hidden, and drives
 * which columns' data is fetched (`columns=` request param).
 */
export interface ServerColumn {
    key: string;
    label?: string;
    sortable?: boolean;
    visible?: boolean;
    locked?: boolean;
}

export interface DataTableConfig<T = unknown> {
    route: string;
    sortable: boolean;
    pagination: boolean;
    searchable: boolean;
    isCard: boolean;
    cardTitle?: string;
    cardSubtitle?: string;
    /** Toolbar heading shown to the left of the search input. */
    title?: string;
    /** Toolbar sub-heading shown under the title. */
    subtitle?: string;
    /** Show the column visibility/order menu button. Default: true. */
    columnToggle: boolean;
    perPage: number;
    idColumn: IdColumnConfig;
    columns: ColumnConfig[];
    filters: FilterConfig[];
    actions: ActionConfig<T>[];
    menuActions: MenuActionConfig<T>[];
    menuButton: MenuButtonConfig;
    createButton?: CreateButtonConfig;
}

export interface DataTableResponse<T> {
    data: T[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    /** Optional server-driven column list (DatatableQueryBuilder::columns()). */
    columns?: ServerColumn[];
}
