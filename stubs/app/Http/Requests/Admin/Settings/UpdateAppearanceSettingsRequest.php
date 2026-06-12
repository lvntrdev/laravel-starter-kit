<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lvntr\StarterKit\Support\ThemeResolver;

class UpdateAppearanceSettingsRequest extends FormRequest
{
    /**
     * Selectable accent colors — the Tailwind palette names plus the four
     * custom muted tones, mirroring the frontend ACCENT_COLORS set, with
     * `default` (kit primary + neutral sidebar) as the always-valid choice.
     *
     * @var array<int, string>
     */
    private const ACCENT_COLORS = [
        'default',
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green',
        'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo',
        'violet', 'purple', 'fuchsia', 'pink', 'rose',
        'taupe', 'mauve', 'mist', 'olive',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            // The theme must be a member of the full selectable set — the
            // single source of truth (ThemeResolver::availableThemes() = the
            // always-available runtime themes [main, aura] ∪ the build-time
            // custom theme folders; the slug rule there blocks any traversal
            // value). The combined list validates both layers without change.
            'theme' => ['required', 'string', Rule::in(ThemeResolver::availableThemes())],
            'accent_color' => ['required', 'string', Rule::in(self::ACCENT_COLORS)],
            'sidebar_style' => ['required', 'string', Rule::in(['colored', 'light'])],
            'dark_mode_default' => ['required', 'boolean'],
        ];
    }
}
