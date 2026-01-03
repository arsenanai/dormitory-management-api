<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {
    }

    /**
     * Display a listing of payments
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id'   => 'sometimes|integer|exists:users,id',
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'search'    => 'sometimes|nullable|string|max:255', // Searches deal_number or user name
            'role'      => 'sometimes|nullable|string|max:50', // Add role filter
            'per_page'  => 'sometimes|integer|min:1|max:1000',
            'status'	=> ['sometimes', new Enum(PaymentStatus::class)],
        ]);

        $payments = $this->paymentService->index($filters);
        return $payments;
    }

    /**
     * Store a newly created payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|integer|exists:users,id',
            'date_from'     => 'required|date',
            'date_to'       => 'required|date|after_or_equal:date_from',
            'amount'        => 'required|numeric|min:0',
            'deal_number'   => 'nullable|string|max:255',
            'deal_date'     => 'nullable|date',
            'payment_check' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'status'		=> ['sometimes', new Enum(PaymentStatus::class)],
        ]);

        return $this->paymentService->create($validated);
    }

    public function myStore(Request $request)
    {
        $validated = $request->validate([
            'date_from'     => 'required|date',
            'date_to'       => 'required|date|after_or_equal:date_from',
            'deal_number'   => 'nullable|string|max:255',
            'deal_date'     => 'nullable|date',
            'payment_check' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'amount'		=> 'required|numeric|min:0',
        ]);
        $user = auth()->user();
        $validated['user_id'] = $user->id;

        return $this->paymentService->create($validated);
    }

    /**
     * Display the specified payment
     */
    public function show($id)
    {
        return $this->paymentService->getPaymentDetails($id);
    }

    /**
     * Update the specified payment
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date_from'     => 'sometimes|date',
            'date_to'       => 'sometimes|date|after_or_equal:date_from',
            'amount'        => 'sometimes|numeric|min:0',
            'deal_number'   => 'sometimes|nullable|string|max:255',
            'deal_date'     => 'sometimes|nullable|date',
            // Allow string for deletion signal ('') or file for upload.
            // The 'file' rule only applies if the input is an actual file upload.
            'payment_check' => [
                'sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048' // 2MB max
            ],
            'status'		=> ['sometimes', new Enum(PaymentStatus::class)],
        ]);

        return $this->paymentService->update($id, $validated);
    }

    /**
     * Remove the specified payment
     */
    public function destroy($id)
    {
        $this->paymentService->delete($id);
        return response()->json([ 'message' => 'Payment deleted successfully' ], 200);
    }

    /**
     * Export payments to CSV
     */
    public function export(Request $request)
    {
        $filters = $request->validate([
            'user_id'   => 'sometimes|integer|exists:users,id',
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'status'	=> ['sometimes', new Enum(PaymentStatus::class)],
        ]);

        return $this->paymentService->exportPayments($filters);
    }

    public function myPayments(Request $request)
    {
        $filters = $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'search'    => 'sometimes|nullable|numeric|min:0', // Searches amount or deal_number
            'per_page'  => 'sometimes|integer|min:1|max:1000',
            'status'	=> ['sometimes', new Enum(PaymentStatus::class)],
        ]);
        $filters['user_id'] = auth()->user()->id;

        $payments = $this->paymentService->index($filters);
        return $payments;
    }
}
