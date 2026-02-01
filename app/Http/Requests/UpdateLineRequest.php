<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', Rule::unique('lines')->ignore($this->route('line'))],
            'ivr_steps' => ['sometimes', 'array'],
            'ivr_steps.*.digit' => ['required', 'string', 'max:1'],
            'ivr_steps.*.action_type' => ['required', 'string', 'in:route_to_line,call_user,forward_to_ai'],
            'ivr_steps.*.target_id' => ['sometimes', 'integer'],
            'ivr_steps.*.sub_steps' => ['sometimes', 'array'],
        ];
    }
}
