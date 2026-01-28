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
            'payment_check' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'amount'		=> 'required|numeric|min:0',
            'payment_type'  => 'nullable|string|max:255',
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

    /**
     * Update payment for students/guests (self-service)
     * Only allows updating payment_check for pending payments
     * Students cannot edit or delete payments - only upload bank checks
     */
    public function updateMyPayment(Request $request, $id)
    {
        $payment = \App\Models\Payment::findOrFail($id);
        $user = auth()->user();
        $userRole = $user->role->name ?? null;

        // Only students and guests can use this endpoint
        if (!in_array($userRole, ['student', 'guest'])) {
            return response()->json(['message' => 'Unauthorized. This endpoint is for students and guests only.'], 403);
        }

        // Ensure the payment belongs to the authenticated user
        if ($payment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only update your own payments.'], 403);
        }

        // Only allow bank check upload for pending payments
        if ($payment->status !== \App\Enums\PaymentStatus::Pending) {
            return response()->json([
                'message' => 'You can only upload bank checks for pending payments.'
            ], 422);
        }

        // Security: Check for any unexpected fields before validation
        // Only payment_check is allowed - reject any other fields to prevent hacking attempts
        $allowedFields = ['payment_check'];
        $requestInput = $request->except(['_token', '_method']); // Exclude Laravel internal fields
        $requestKeys = array_keys($requestInput);
        $unexpectedFields = array_diff($requestKeys, $allowedFields);
        
        if (!empty($unexpectedFields)) {
            return response()->json([
                'message' => 'Invalid request. Only payment_check field is allowed.',
                'errors' => ['payment_check' => ['Only payment_check field can be updated. Unexpected fields: ' . implode(', ', $unexpectedFields)]]
            ], 422);
        }

        // Strictly validate only payment_check field
        $validated = $request->validate([
            'payment_check' => [
                'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'
            ],
        ]);

        // Update payment with only the payment_check field
        $updated = $this->paymentService->update($id, $validated);

        return response()->json([
            'data' => $updated,
            'message' => 'Payment check uploaded successfully. Payment will be validated soon.',
        ]);
    }
}
