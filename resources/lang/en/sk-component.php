<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Component Showcase (developer-only)
    |--------------------------------------------------------------------------
    | Visible copy on the Admin/Components/* pages. PrimeVue severity names
    | (Primary, Info, …), Tailwind color families (slate, indigo, …) and code
    | samples are API values, so they are left literal in the page, not keyed.
    */

    // Variant headings — shared by the Tag and Button showcases.
    'variants' => [
        'filled' => 'Filled',
        'soft' => 'Soft',
        'outlined' => 'Outlined',
        'text' => 'Text',
        'raised_rounded' => 'Raised · Rounded',
        'outlined_text' => 'Outlined · Text',
        'icon_only' => 'Icon Only',
    ],

    'sizes' => [
        'small' => 'Small',
        'normal' => 'Normal',
        'large' => 'Large',
    ],

    'docs' => 'Documentation',

    'tag' => [
        'title' => 'Tags',
        'subtitle' => 'Tag component — all variants and colors.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> defines the tag\'s color. Alongside the built-in severities, every Tailwind color family can be used directly as a <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> — e.g. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Tag severity="indigo" value="Secondary" /&gt;</code>.',
        'sections' => [
            'filled' => [
                'title' => 'Severities · Filled',
                'desc' => 'Default filled tags — the severity type drives the tag color.',
            ],
            'rounded' => [
                'title' => 'Rounded',
                'desc' => 'The rounded prop gives pill-shaped, fully rounded edges.',
            ],
            'icon' => [
                'title' => 'With Icon',
                'desc' => 'The icon prop prepends a meaningful PrimeIcon.',
            ],
            'soft' => [
                'title' => 'Soft · Tonal',
                'desc' => 'Lightly tinted background with a dark label — calmer in dense tables.',
            ],
            'outlined' => [
                'title' => 'Outlined',
                'desc' => 'Transparent background with a colored ring and label — for low-emphasis contexts.',
            ],
            'removable' => [
                'title' => 'Removable & Sizes',
                'desc' => 'Filter chips with a close button; small · default · large size scale.',
            ],
            'colors' => [
                'title' => 'All Tailwind Colors',
                'desc' => ':count color families (including mauve · olive · mist · taupe) — filled, soft and outlined.',
                'badge' => ':count colors',
            ],
        ],
        'remove' => 'Remove',
        'states' => [
            'active' => 'Active',
            'approved' => 'Approved',
            'urgent' => 'Urgent',
        ],
    ],

    'button' => [
        'title' => 'Buttons',
        'subtitle' => 'Button component — severity, variants and Tailwind colors.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> defines the button\'s color. Alongside the built-in severities, every Tailwind color family (including the custom <strong>mauve · olive · mist · taupe</strong> added in v4.2) can be used directly as a <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> — e.g. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Button severity="indigo" label="Save" /&gt;</code>. Works with all of the filled, outlined, text, raised, rounded and icon-only variants.',
        'sections' => [
            'severities' => [
                'title' => 'PrimeVue · Severities',
                'desc' => 'Built-in severities keep their PrimeVue styling — filled, outlined and text.',
            ],
            'variants' => [
                'title' => 'Variants',
                'desc' => 'Icon, rounded, icon-only, sizes and states.',
            ],
        ],
        'actions' => [
            'save' => 'Save',
            'next' => 'Next',
            'delete' => 'Delete',
            'loading' => 'Loading',
            'disabled' => 'Disabled',
        ],
        'aria' => [
            'like' => 'Like',
            'confirm' => 'Confirm',
            'settings' => 'Settings',
            'bookmark' => 'Bookmark',
        ],
    ],

    'message' => [
        'title' => 'Messages',
        'subtitle' => 'Message & InlineMessage components — all variants and colors.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> defines the message\'s color. Alongside the built-in severities, every Tailwind color family (including the custom <strong>mauve · olive · mist · taupe</strong>) can be used directly as a <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> — e.g. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Message severity="indigo"&gt;</code>. The default banner is the calm accent style; add <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">class="p-message-fill"</code> for the solid filled look, or the built-in <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">variant="outlined" / "simple"</code>.',
        'sections' => [
            'filled' => [
                'title' => 'Severities · Filled',
                'desc' => 'Solid colored banner — white ring icon, bold title and description line, close button.',
            ],
            'accent' => [
                'title' => 'Accent (Default)',
                'desc' => 'Lightly tinted background with a bold colored left edge and ring icon — the calm default for inline notices.',
            ],
            'outlined' => [
                'title' => 'Outlined',
                'desc' => 'Transparent background with a colored ring — for low-emphasis contexts.',
            ],
            'simple' => [
                'title' => 'Simple',
                'desc' => 'No background or border — just the colored icon and text inline.',
            ],
            'inline' => [
                'title' => 'InlineMessage',
                'desc' => 'Compact soft chips for inline form and field-level feedback.',
            ],
            'colors' => [
                'title' => 'All Tailwind Colors',
                'desc' => ':count color families (including mauve · olive · mist · taupe) as filled banners.',
                'badge' => ':count colors',
            ],
        ],
        'items' => [
            'success' => ['title' => 'Success', 'desc' => 'The record was created and published successfully.'],
            'info' => ['title' => 'Info', 'desc' => 'A new version update is available.'],
            'warn' => ['title' => 'Warning', 'desc' => 'Your session will expire in 5 minutes.'],
            'danger' => ['title' => 'Error', 'desc' => 'An error occurred while saving, please try again.'],
            'secondary' => ['title' => 'Secondary', 'desc' => 'Changes were saved as a draft.'],
            'contrast' => ['title' => 'Contrast', 'desc' => 'A high-contrast notification message.'],
        ],
    ],

];
