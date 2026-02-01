<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'department' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'language_ids' => ['sometimes', 'array'],
            'language_ids.*' => ['exists:languages,id'],
            'skill_ids' => ['sometimes', 'array'],
            'skill_ids.*' => ['exists:skills,id'],
        ];
    }
}
