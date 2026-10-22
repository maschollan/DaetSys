<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'role_id.exists' => 'Role tidak ditemukan.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    public function roleName(): ?string
    {
        $roleId = $this->validated('role_id');

        if ($roleId === null) {
            return null;
        }

        return Role::query()->whereKey($roleId)->value('name');
    }

    public function isKnownRole(): bool
    {
        $name = $this->roleName();

        return $name !== null && in_array($name, UserRole::values(), true);
    }
}
