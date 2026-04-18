<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private UserStatusService $userStatusService)
    {
    }
    /**
     * Get payments with filters and pagination
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(array $filters = []): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = Payment::with([ 'user', 'user.role', 'user.studentProfile', 'user.guestProfile', 'user.room', 'user.room.roomType', 'type' ]);

        // Apply date range filter for payment period overlap
        if (isset($filters['date_from'])) {
            // Payments that end on or after the filter start date
            $query->whereDate('date_to', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            // Payments that start on or before the filter end date
            $query->whereDate('date_from', '<=', $filters['date_to']);
        }

        // Apply role filter
        if (! empty($filters['role'])) {
            $query->whereHas('user.role', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhereHas('studentProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('iin', 'like', '%' . $search . '%');
                        });
                })
                    ->orWhere('amount', $search);
            });
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_type'])) {
            // Filter by payment type name through the relationship
            $query->whereHas('type', function ($q) use ($filters) {
                $q->where('name', $filters['payment_type']);
            });
        }

        $perPage = $filters['per_page'] ?? 20;

        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return PaymentResource::collection($payments);
    }

    /**
     * Create a new payment
     *
     * @return PaymentResource
     */
    public function create(array $data): PaymentResource
    {
        return DB::transaction(function () use ($data) {
            // Set deal_date if not provided
            if (! isset($data['deal_date'])) {
                $dealDate = isset($data['date_from']) ? new \DateTime($data['date_from']) : new \DateTime();
                $data['deal_date'] = $dealDate->format('Y-m-d');
            }

            // If amount is not provided, try to get it from the student's room type semester_rate
            // TODO: meal calculation will change
            if (! isset($data['amount'])) {
                $user = User::with('room.roomType', 'role')->find($data['user_id']);
                if ($user && $user->hasRole('student') && $user->room && $user->room->roomType) {
                    $data['amount'] = $user->room->roomType->semester_rate;
                } else {
                    // Fallback or throw an error if amount is mandatory and cannot be determined
                    $data['amount'] = 0;
                }
            }

            // Resolve payment_type name to payment_type_id
            if (isset($data['payment_type'])) {
                $paymentType = PaymentType::where('name', $data['payment_type'])->first();
                if ($paymentType) {
                    $data['payment_type_id'] = $paymentType->id;
                }
                unset($data['payment_type']);
            }

            // Set initial status for student/guest roles (only if not explicitly set by admin)
            if (!isset($data['status'])) {
                $user = User::with('role')->find($data['user_id']);
                if ($user && ($user->hasRole('student') || $user->hasRole('guest'))) {
                    $data['status'] = PaymentStatus::Pending;
                }
            }

            $payment = Payment::create($data);

            // Sync user status if payment was created with Completed status
            if (isset($data['status']) && $data['status'] === PaymentStatus::Completed) {
                $this->userStatusService->syncUserStatus($data['user_id']);
            }

            return new PaymentResource($payment->load([ 'user', 'user.role', 'user.studentProfile', 'user.guestProfile', 'user.room', 'user.room.roomType', 'type' ]));
        });
    }

    /**
     * Get payment details
     *
     * @return PaymentResource
     */
    public function getPaymentDetails(int|string $id): PaymentResource
    {
        $payment = Payment::with([ 'user', 'user.role', 'user.studentProfile', 'user.guestProfile', 'user.room', 'user.room.roomType', 'type' ])->findOrFail($id);
        return new PaymentResource($payment);
    }

    /**
     * Update payment
     *
     * @return PaymentResource
     */
    public function update(int|string $id, array $data): PaymentResource
    {
        return DB::transaction(function () use ($id, $data) {
            $payment = Payment::findOrFail($id);

            // Remove payment_check handling - moved to TransactionService
            if (array_key_exists('payment_check', $data)) {
                unset($data['payment_check']);
            }

            // Resolve payment_type name to payment_type_id
            if (isset($data['payment_type'])) {
                $paymentType = PaymentType::where('name', $data['payment_type'])->first();
                if ($paymentType) {
                    $data['payment_type_id'] = $paymentType->id;
                }
                unset($data['payment_type']);
            }

            $oldStatus = $payment->status;
            $payment->update($data);

            // Sync user status if payment status changed
            if (isset($data['status']) && $oldStatus !== $payment->status) {
                $this->userStatusService->syncUserStatus($payment->user_id);
            }

            // Event handling moved to TransactionService

            return new PaymentResource($payment);
        });
    }

    // syncGuestStatusBasedOnPayments method removed - logic moved to TransactionService

    /**
     * Delete payment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(int|string $id): \Illuminate\Http\JsonResponse
    {
        $payment = Payment::findOrFail($id);

        // payment_check file handling moved to TransactionService

        $payment->delete();
        return response()->json([ 'message' => 'Payment deleted successfully' ], 200);
    }

    /**
     * Export payments to CSV
     */
    public function exportPayments(array $filters = [])
    {
        $query = Payment::with([ 'user' ]);

        // Apply same filters as getPaymentsWithFilters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('deal_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('deal_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['payment_type'])) {
            $query->whereHas('type', function ($q) use ($filters) {
                $q->where('name', $filters['payment_type']);
            });
        }

        $payments = $query->orderBy('deal_date', 'desc')->get();

        $configurationService = new ConfigurationService();
        $currencySymbol = $configurationService->getCurrencySymbol();

        // Create CSV content
        $csvContent = "Payment ID,Student Name,Student Email,Payment Type,Deal Number,Deal Date,Amount ({$currencySymbol}),Paid Amount ({$currencySymbol}),Remaining Amount ({$currencySymbol}),Date From,Date To,Status\n";

        foreach ($payments as $payment) {
            $dealDate = $payment->deal_date ? (new \DateTime($payment->deal_date))->format('Y-m-d') : '';
            $dateFrom = $payment->date_from ? (new \DateTime($payment->date_from))->format('Y-m-d') : '';
            $dateTo = $payment->date_to ? (new \DateTime($payment->date_to))->format('Y-m-d') : '';
            $remainingAmount = $payment->amount - ($payment->paid_amount ?? 0);

            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $payment->id,
                '"' . str_replace('"', '""', $payment->user->name ?? '') . '"',
                ($payment->user->email ?? ''),
                '"' . str_replace('"', '""', $payment->type->name ?? '') . '"',
                '"' . str_replace('"', '""', $payment->deal_number ?? '') . '"',
                $dealDate,
                $payment->amount,
                $payment->paid_amount ?? 0,
                $remainingAmount,
                $dateFrom,
                $dateTo,
                $payment->status->value
            );
        }

        $filename = 'payments_export_' . date('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Recalculate payment status based on linked transactions
     * Called by TransactionService when transactions change
     */
    public function recalculatePaymentStatus(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        // Calculate paid_amount from completed transactions
        $paidAmount = $payment->transactions()
            ->where('status', \App\Enums\TransactionStatus::Completed)
            ->sum('payment_transaction.amount');

        $payment->paid_amount = (float) $paidAmount;

        // Determine payment status
        if ($paidAmount >= $payment->amount) {
            $payment->status = PaymentStatus::Completed;
        } elseif ($paidAmount > 0) {
            $payment->status = PaymentStatus::PartiallyPaid;
        } else {
            $payment->status = PaymentStatus::Pending;
        }

        $payment->save();
    }
}
