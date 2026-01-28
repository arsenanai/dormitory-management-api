<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTypeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Unique|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        /** @var int|string|null $id */
        $id = $this->route('payment_type');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('payment_types')->ignore($id)],
            'frequency' => ['required', Rule::in(['monthly', 'semesterly', 'once'])],
            'calculation_method' => ['required', Rule::in(['room_semester_rate', 'room_daily_rate', 'fixed'])],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'target_role' => ['required', Rule::in(['student', 'guest'])],
            'trigger_event' => ['nullable', Rule::in(['registration', 'new_semester', 'new_month', 'new_booking', 'room_type_change'])],
        ];
    }
}
