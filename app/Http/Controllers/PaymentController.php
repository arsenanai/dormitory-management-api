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
            'search'    => 'sometimes|nullable|string|max:255', // Searches IIN, user name, email, or amount
            'role'      => 'sometimes|nullable|string|max:50', // Add role filter
            'per_page'  => 'sometimes|integer|min:1|max:1000',
            'status'    => ['sometimes', new Enum(PaymentStatus::class)],
            'payment_type' => 'sometimes|nullable|string|max:255',
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
            'payment_type'  => 'nullable|string|max:255',
            'deal_number'   => 'nullable|string|max:255',
            'deal_date'     => 'nullable|date',
            'status'        => ['sometimes', new Enum(PaymentStatus::class)],
        ]);

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
     * Only admins and sudo can update payments (students use updateMyPayment instead)
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $userRole = $user->role->name ?? null;

        // Only admins and sudo can update payments
        if (!in_array($userRole, ['admin', 'sudo'])) {
            return response()->json(['message' => 'Unauthorized. Only admins can edit payments.'], 403);
        }

        $validated = $request->validate([
            'date_from'     => 'sometimes|date',
            'date_to'       => 'sometimes|date|after_or_equal:date_from',
            'amount'        => 'sometimes|numeric|min:0',
            'payment_type'  => 'sometimes|nullable|string|max:255',
            'deal_number'   => 'sometimes|nullable|string|max:255',
            'deal_date'     => 'sometimes|nullable|date',
            'status'        => ['sometimes', new Enum(PaymentStatus::class)],
        ]);

        return $this->paymentService->update($id, $validated);
    }

    /**
     * Remove the specified payment
     * Only admins and sudo can delete payments
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $userRole = $user->role->name ?? null;

        // Only admins and sudo can delete payments
        if (!in_array($userRole, ['admin', 'sudo'])) {
            return response()->json(['message' => 'Unauthorized. Only admins can delete payments.'], 403);
        }

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
            'status'    => ['sometimes', new Enum(PaymentStatus::class)],
            'payment_type' => 'sometimes|nullable|string|max:255',
        ]);

        return $this->paymentService->exportPayments($filters);
    }

    public function myPayments(Request $request)
    {
        $filters = $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'search'    => 'sometimes|nullable|string|max:255', // Searches IIN, user name, email, or amount
            'per_page'  => 'sometimes|integer|min:1|max:1000',
            'status'    => ['sometimes', new Enum(PaymentStatus::class)],
            'payment_type' => 'sometimes|nullable|string|max:255',
        ]);
        $filters['user_id'] = auth()->user()->id;

        $payments = $this->paymentService->index($filters);
        return $payments;
    }
}
