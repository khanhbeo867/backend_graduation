<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->resolveUser();

        return [
            'username' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('users', 'employee_id')->ignore($user),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function resolveUser(): User|int|string|null
    {
        return $this->route('user')
            ?? $this->route('User')
            ?? $this->route('id');
    }
}
