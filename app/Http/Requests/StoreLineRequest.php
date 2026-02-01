<?php

namespace App\Http\Requests;

use App\Rules\ValidIvrSteps;
use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'unique:lines,phone_number'],
            'ivr_steps' => ['nullable', new ValidIvrSteps],
        ];
    }
}
