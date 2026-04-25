// resources/js/tab-builder/types.ts

export type TabLayout = 'horizontal' | 'vertical';

export type TabIconColor =
    | 'blue'
    | 'amber'
    | 'emerald'
    | 'purple'
    | 'teal'
    | 'red'
    | 'indigo'
    | 'slate'
    | 'pink'
    | 'orange'
    | 'cyan'
    | 'green'
    | 'yellow';

export type TabBadgeSeverity = 'success' | 'warn' | 'info' | 'danger' | 'secondary';

export interface TabItemConfig {
    key: string;
    label: string;
    icon?: string;
    /** Secondary description shown below the label. Vertical layout only. */
    description?: string;
    /** Icon tile color preset (vertical layout only). Defaults to 'slate'. */
    iconColor?: TabIconColor;
    /** Trailing badge value (number or short text). Ignored when `checked` is true. */
    badge?: string | number;
    /** Trailing badge severity. Defaults to 'secondary'. */
    badgeSeverity?: TabBadgeSeverity;
    /** Shows a green check mark on the trailing edge. Takes precedence over `badge`. */
    checked?: boolean;
    permission?: string | string[];
    role?: string | string[];
    visible?: boolean | (() => boolean);
    disabled?: boolean | (() => boolean);
    /** Per-tab Card visibility. Overrides the global isCard when set. */
    isCard?: boolean;
    /** Per-tab Card title. Overrides the global cardTitle when set. */
    cardTitle?: string;
    /** Per-tab Card subtitle. Overrides the global cardSubtitle when set. */
    cardSubtitle?: string;
}

export interface TabBuilderConfig {
    layout: TabLayout;
    tabs: TabItemConfig[];
    queryParam: string;
    cssClass?: string;
    cardTitle?: string;
    cardSubtitle?: string;
    isCard?: boolean;
}
