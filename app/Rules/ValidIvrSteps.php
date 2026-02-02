<?php

namespace App\Rules;

use App\Enums\IvrActionType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIvrSteps implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Just in case
        if (! is_array($value)) {
            $fail('The :attribute must be an array.');

            return;
        }

        $this->validateSteps($value, $fail, $attribute);
    }

    private function validateSteps(array $steps, Closure $fail, string $path): void
    {
        foreach ($steps as $index => $step) {
            $stepPath = "$path.$index";

            if (! is_array($step)) {
                $fail("$stepPath must be an array.");

                continue;
            }

            if (is_null($step['digit'])) {
                $fail("$stepPath.digit is required.");
            }

            if (empty($step['action_type'])) {
                $fail("$stepPath.action_type is required.");
            } elseif (! IvrActionType::tryFrom($step['action_type'])) {
                $fail("$stepPath.action_type is invalid.");
            }

            if (isset($step['label']) && (! is_string($step['label']) || strlen($step['label']) > 255)) {
                $fail("$stepPath.label must be a string with max 255 characters.");
            }

            if (isset($step['target_id']) && ! is_null($step['target_id']) && ! is_int($step['target_id'])) {
                $fail("$stepPath.target_id must be an integer.");
            }

            if (isset($step['context']) && ! is_array($step['context'])) {
                $fail("$stepPath.context must be an array.");
            }

            if (! empty($step['sub_steps'])) {
                if (! is_array($step['sub_steps'])) {
                    $fail("$stepPath.sub_steps must be an array.");
                } else {
                    $this->validateSteps($step['sub_steps'], $fail, "$stepPath.sub_steps");
                }
            }
        }
    }
}
