<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'department' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'language_ids' => ['sometimes', 'array'],
            'language_ids.*' => ['exists:languages,id'],
            'skill_ids' => ['sometimes', 'array'],
            'skill_ids.*' => ['exists:skills,id'],
        ];
    }
}
