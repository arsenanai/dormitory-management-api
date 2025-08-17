<?php

namespace App\Rules;

use App\Services\IINValidationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for Kazakhstan IIN
 * 
 * @package App\Rules
 */
class ValidIIN implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iinValidationService = new IINValidationService();
        
        if (!$iinValidationService->validate($value)) {
            $fail('The :attribute must be a valid Kazakhstan IIN.');
        }
    }
}
