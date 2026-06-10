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
        'intro' => 'severity defines the tag\'s color. Alongside the built-in severities, every Tailwind color family can be used directly as a severity — e.g. severity="indigo".',
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
        'intro' => 'severity defines the button\'s color. Alongside the built-in severities, every Tailwind color family (including the custom mauve · olive · mist · taupe) can be used directly as a severity — e.g. severity="indigo". Works with all of the filled, outlined, text, raised, rounded and icon-only variants.',
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
        'intro' => 'severity defines the message\'s color. Alongside the built-in severities, every Tailwind color family (including the custom mauve · olive · mist · taupe) can be used directly as a severity — e.g. severity="indigo". The default banner is the calm accent style; add class="p-message-fill" for the solid filled look, or the built-in variant="outlined" / "simple".',
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

    'toast' => [
        'title' => 'Toast',
        'subtitle' => 'Toast notifications — all severities, variants and colors.',
        'intro' => 'severity defines the toast type. Alongside the built-in severities, every Tailwind color family (including the custom mauve · olive · mist · taupe) can be used directly as a severity — e.g. toast.add({ severity: "indigo" }). The default look is the lightly tinted accent card; pass styleClass: "sk-toast-solid" for the filled look, or styleClass: "sk-toast-outlined" for the outlined one.',
        'sections' => [
            'severity' => [
                'title' => 'Severity',
                'desc' => 'severity defines the toast type — the lightly tinted default variant, with a circular ring icon and progress bar.',
            ],
            'variants' => [
                'title' => 'Outlined & Solid',
                'desc' => 'Outlined: flat surface, colored ring icon. Solid: fully colored card, white ring icon and text.',
            ],
            'sticky' => [
                'title' => 'Sticky / Actions',
                'desc' => 'A toast that must wait for the user, carrying pill-shaped confirm buttons — a delete confirmation or a single inline action.',
            ],
            'colors' => [
                'title' => 'All Tailwind Colors',
                'desc' => ':count color families (including mauve · olive · mist · taupe) as toasts — the 600 shade paints the ring icon and accent.',
                'badge' => ':count colors',
            ],
            'live' => [
                'title' => 'Live Preview',
                'desc' => 'Fire real toast notifications — these buttons use the runtime ToastComponent.',
                'update' => 'Action toast',
                'delete' => 'Delete confirm',
            ],
        ],
        'items' => [
            'success' => ['summary' => 'Success', 'detail' => 'The record was created successfully.'],
            'info' => ['summary' => 'Info', 'detail' => 'Your profile was updated.'],
            'warn' => ['summary' => 'Warning', 'detail' => 'Your disk space is 90% full.'],
            'error' => ['summary' => 'Error', 'detail' => 'The operation failed, please try again.'],
            'secondary' => ['summary' => 'Secondary', 'detail' => 'Changes were saved as a draft.'],
            'contrast' => ['summary' => 'Contrast', 'detail' => 'A high-contrast notification.'],
        ],
        'sticky' => [
            'delete' => [
                'summary' => 'Delete record?',
                'detail' => 'This action cannot be undone.',
                'confirm' => 'Delete',
                'cancel' => 'Cancel',
            ],
            'update' => [
                'summary' => 'New version ready',
                'detail' => 'Please restart the app.',
                'confirm' => 'Update',
                'cancel' => 'Later',
            ],
            'retry' => [
                'summary' => 'Connection lost.',
                'retry' => 'Retry',
            ],
        ],
    ],

    'form' => [
        'title' => 'FormBuilder',
        'subtitle' => 'Declarative forms — every FB.* field type plus the card, aside and divided layout compositions.',
        'intro' => 'Forms are configured declaratively with the FB builder and rendered by <SkForm>. Each example below is a live <SkForm> in v-model mode (no submit) — read the heading for the FB.* factory or layout method it demonstrates.',
        'docs' => 'FormBuilder Docs',
        'sections' => [
            'text' => [
                'title' => 'Text / number inputs',
                'desc' => 'inputText, inputMask, inputNumber, password, inputOtp, datePicker, textarea and editor.',
            ],
            'choice' => [
                'title' => 'Choice fields',
                'desc' => 'select, multiselect, radio, checkboxGroup and selectButton — each carries options or a definition key.',
            ],
            'boolean' => [
                'title' => 'Boolean / toggle',
                'desc' => 'checkbox, toggleSwitch and toggleButton — inline-label controls.',
            ],
            'special' => [
                'title' => 'Special fields',
                'desc' => 'fileUpload (dropzone) and colorSelector (family + tone).',
            ],
            'i18n' => [
                'title' => 'Translatable',
                'desc' => 'translatableText and translatableTextarea — one value per active locale.',
            ],
            'layouts' => [
                'title' => 'Layout compositions',
                'desc' => 'The same fields assembled into real layouts — card with action bar, aside section, divided horizontal rows and an inline filter bar.',
            ],
        ],
        'layouts' => [
            'card' => [
                'title' => 'Form card + action bar',
                'desc' => 'cardTitle / cardSubtitle with a bottom action bar (submit mode).',
            ],
            'aside' => [
                'title' => 'Aside section',
                'desc' => 'FB.section().aside() — header on the left, fields grid on the right. Settings-page style.',
            ],
            'divided' => [
                'title' => 'Divided horizontal rows',
                'desc' => "FB.form().layout('horizontal').dividers() — labels left, hairline dividers between rows.",
            ],
            'filter' => [
                'title' => 'Inline filter bar',
                'desc' => 'A compact single-row form for list filtering.',
            ],
        ],
    ],

];
