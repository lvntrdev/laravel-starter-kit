<?php

namespace App\Http\Requests\Admin\User;

use App\Domain\Role\Queries\RoleSelectOptionsQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation rules for creating a new user in admin panel.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Defense in depth — CheckResourcePermission middleware also gates this
     * route, but the FormRequest enforces the permission directly so the
     * route stays safe if the middleware binding is ever misconfigured.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedRoles = collect(app(RoleSelectOptionsQuery::class)->get($this->user()))
            ->pluck('value')
            ->all();

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'banned'])],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }
}
