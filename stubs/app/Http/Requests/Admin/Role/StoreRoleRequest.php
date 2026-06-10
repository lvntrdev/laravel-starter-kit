<?php

namespace App\Http\Requests\Admin\Role;

use App\Enums\RoleEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for creating a new role in admin panel.
 */
class StoreRoleRequest extends FormRequest
{
    /** Allowed tag colors — the Tailwind palette offered by SkColorSelector. */
    public const TAG_COLORS = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink',
        'rose', 'slate', 'gray', 'zinc', 'neutral', 'stone',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:roles,name'],
            'display_name' => ['required', 'array'],
            'display_name.*' => ['required', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', Rule::in(self::TAG_COLORS)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Human-readable attribute names — per-locale display_name keys would
     * otherwise surface as raw "display_name.en" in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'name' => __('validation.attributes.role_name'),
            'color' => __('sk-role.color'),
        ];

        foreach (array_keys(config('app.languages', [])) as $locale) {
            $attributes["display_name.{$locale}"] = __('validation.attributes.display_name').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    /**
     * Non-system_admin users can only assign permissions they themselves possess.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        $user = $this->user();

        if (! $user->hasRole(RoleEnum::SystemAdmin)) {
            $userPermissions = $user->getAllPermissions()->pluck('name')->all();
            $data['permissions'] = array_values(array_intersect(
                $data['permissions'] ?? [],
                $userPermissions,
            ));
        }

        return $data;
    }
}
