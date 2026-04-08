<?php

namespace App\Http\Requests\Admin\User;

use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation rules for updating an existing user in admin panel.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'gender' => ['required', 'string', 'max:50'],
            'theme_color' => ['nullable', 'string', 'max:50'],
            'identity_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ];
    }
}
