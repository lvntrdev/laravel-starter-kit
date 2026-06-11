<?php

return [
    'entity' => 'Content Language',
    'title' => 'Content Languages',
    'subtitle' => 'Languages that multilingual content fields are translated into — separate from the admin interface language.',

    'create' => 'Add Language',
    'edit' => 'Edit Language',
    'delete_confirm' => 'Are you sure you want to delete the ":name" content language? Existing translations for this language will no longer be shown.',

    'active' => 'Active',
    'inactive' => 'Inactive',
    'default' => 'Default',

    'directions' => [
        'ltr' => 'Left to right (LTR)',
        'rtl' => 'Right to left (RTL)',
    ],

    'fields' => [
        'code' => 'Code',
        'code_hint' => 'Lowercase locale code, e.g. "en", "en-us", "tr".',
        'name' => 'Name',
        'native_name' => 'Native Name',
        'direction' => 'Text Direction',
        'flag' => 'Flag',
        'flag_hint' => 'Optional flag emoji or icon shown next to the language.',
        'is_active' => 'Active',
        'is_active_hint' => 'Only active languages appear as tabs on multilingual content fields.',
        'is_default' => 'Default',
        'is_default_hint' => 'The fallback content language. Exactly one language is the default.',
        'fallback_code' => 'Fallback Language',
        'fallback_none' => 'None',
        'fallback_hint' => 'Language to fall back to when content is missing (stored for future use).',
        'sort_order' => 'Sort Order',
    ],

    'errors' => [
        'delete_default' => 'The default content language cannot be deleted. Set another language as default first.',
        'last_default' => 'At least one active default content language must remain.',
        'fallback_self' => 'A language cannot use itself as its fallback.',
    ],
];
