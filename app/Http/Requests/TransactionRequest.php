<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $isPost = $this->isMethod('POST');
        $isPut = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'payment_ids'     => ($isPost ? 'required' : 'sometimes') . '|array|min:1',
            'payment_ids.*'   => 'exists:payments,id',
            'amounts'         => 'sometimes|array',
            'amounts.*'       => 'numeric|min:0.01',
            'amount'          => ($isPost ? 'required' : 'sometimes') . '|numeric|min:0.01',
            'payment_method'  => 'sometimes|string|in:bank_check,kaspi,stripe',
            'payment_check'   => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];

        $user = $this->user();
        $userRole = $user?->role?->name;

        if ($isPost && in_array($userRole, ['admin', 'sudo'], true)) {
            $rules['user_id'] = 'required|exists:users,id';
            $rules['status'] = 'sometimes|string|in:pending,processing,completed,failed,cancelled,refunded';
        }

        if ($isPut && in_array($userRole, ['admin', 'sudo'], true)) {
            $rules['status'] = 'sometimes|string|in:pending,processing,completed,failed,cancelled,refunded';
        }

        if ($isPost && in_array($userRole, ['student', 'guest'], true)) {
            $rules['payment_method'] = 'required|string|in:bank_check';
            $rules['payment_check'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:2048';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_ids.required' => 'You must select at least one payment to cover.',
            'payment_ids.*.exists' => 'One or more selected payments are invalid.',
            'amount.required' => 'The transaction amount is required.',
            'amount.min' => 'The amount must be at least 0.01.',
            'payment_check.required' => 'Bank check upload is required for bank check payments.',
            'payment_check.mimes' => 'The bank check must be a file of type: jpg, jpeg, png, pdf.',
            'payment_check.max' => 'The bank check file may not be larger than 2MB.',
        ];
    }
}
