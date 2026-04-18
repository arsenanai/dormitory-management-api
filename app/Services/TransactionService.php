<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Events\MailEventOccurred;
use App\Http\Resources\TransactionResource;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionService
{
    public function __construct(private UserStatusService $userStatusService)
    {
    }

    /**
     * Get transactions with filters and pagination
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(array $filters = []): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = Transaction::with(['user', 'user.role', 'payments']);

        // Apply user filter (for admin view)
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply payment method filter
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Apply date range filter
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })
                ->orWhere('amount', $search)
                ->orWhere('gateway_transaction_id', 'like', '%' . $search . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return TransactionResource::collection($transactions);
    }

    /**
     * Create a new transaction and link it to payments
     *
     * @param  array<string, mixed>  $data
     * @return \App\Http\Resources\TransactionResource
     */
    public function create(array $data): \App\Http\Resources\TransactionResource
    {
        return DB::transaction(function () use ($data) {
            // Handle payment_check file upload
            if (isset($data['payment_check']) && $data['payment_check'] instanceof \Illuminate\Http\UploadedFile) {
                $original = $data['payment_check']->getClientOriginalName();
                $storagePath = 'payment_checks/' . $original;
                if (Storage::disk('public')->exists($storagePath)) {
                    $ext = $data['payment_check']->getClientOriginalExtension();
                    $filename = time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext;
                } else {
                    $filename = $original;
                }
                $data['payment_check'] = $data['payment_check']->storeAs('payment_checks', $filename, 'public');
            }

            // Set initial status
            $data['status'] = $data['status'] ?? TransactionStatus::Pending;

            // Create transaction
            $transaction = Transaction::create($data);

            // Link to payments
            $paymentIds = $data['payment_ids'] ?? [];
            $amounts = $data['amounts'] ?? [];

            foreach ($paymentIds as $index => $paymentId) {
                $amount = $amounts[$index] ?? $data['amount'] / count($paymentIds);

                $transaction->payments()->attach($paymentId, ['amount' => $amount]);
            }

            // Recalculate payment statuses when transaction is created with a relevant status
            if (in_array($transaction->status, [TransactionStatus::Processing, TransactionStatus::Completed])) {
                $this->recalculatePaymentStatuses($transaction);
            }

            return new TransactionResource($transaction->load(['user', 'payments']));
        });
    }

    /**
     * Get transaction details
     *
     * @param  int|string  $id
     * @return \App\Http\Resources\TransactionResource
     */
    public function show($id): \App\Http\Resources\TransactionResource
    {
        $transaction = Transaction::with(['user', 'user.role', 'payments'])->findOrFail($id);
        return new TransactionResource($transaction);
    }

    /**
     * Update transaction (admin approval/rejection)
     *
     * @param  int|string  $id
     * @param  array<string, mixed>  $data
     * @return \App\Http\Resources\TransactionResource
     */
    public function update($id, array $data): \App\Http\Resources\TransactionResource
    {
        return DB::transaction(function () use ($id, $data) {
            $transaction = Transaction::findOrFail($id);

            // Handle payment_check file update
            if (isset($data['payment_check'])) {
                if ($data['payment_check'] instanceof \Illuminate\Http\UploadedFile) {
                    // Delete old file if exists
                    if ($transaction->payment_check) {
                        Storage::disk('public')->delete($transaction->payment_check);
                    }

                    // Upload new file
                    $original = $data['payment_check']->getClientOriginalName();
                    $storagePath = 'payment_checks/' . $original;
                    if (Storage::disk('public')->exists($storagePath)) {
                        $ext = $data['payment_check']->getClientOriginalExtension();
                        $filename = time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext;
                    } else {
                        $filename = $original;
                    }
                    $data['payment_check'] = $data['payment_check']->storeAs('payment_checks', $filename, 'public');
                } elseif ($data['payment_check'] === '') {
                    // Remove file
                    if ($transaction->payment_check) {
                        Storage::disk('public')->delete($transaction->payment_check);
                    }
                    $data['payment_check'] = null;
                }
            }

            $oldStatus = $transaction->status;
            $transaction->update($data);

            // If status changed to completed or refunded, recalculate payment statuses
            if ($oldStatus !== $transaction->status &&
                in_array($transaction->status, [TransactionStatus::Completed, TransactionStatus::Refunded])) {
                $this->recalculatePaymentStatuses($transaction);
            }

            return new TransactionResource($transaction->load(['user', 'payments']));
        });
    }

    /**
     * Delete a transaction
     *
     * @param  int|string  $id
     */
    public function delete($id): void
    {
        DB::transaction(function () use ($id) {
            $transaction = Transaction::findOrFail($id);

            // Delete payment_check file if exists
            if ($transaction->payment_check) {
                Storage::disk('public')->delete($transaction->payment_check);
            }

            // Get linked payments to recalculate later
            $linkedPaymentIds = $transaction->payments()->pluck('payments.id')->toArray();

            // Delete transaction and pivot records
            $transaction->delete();

            // Recalculate statuses for linked payments
            foreach ($linkedPaymentIds as $paymentId) {
                $this->recalculateSinglePaymentStatus($paymentId);
            }
        });
    }

    /**
     * Upload bank check for an existing transaction
     *
     * @param  int|string  $transactionId
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \App\Http\Resources\TransactionResource
     */
    public function uploadBankCheck($transactionId, \Illuminate\Http\UploadedFile $file): \App\Http\Resources\TransactionResource
    {
        return DB::transaction(function () use ($transactionId, $file) {
            $transaction = Transaction::findOrFail($transactionId);

            // Upload file
            $original = $file->getClientOriginalName();
            $storagePath = 'payment_checks/' . $original;
            if (Storage::disk('public')->exists($storagePath)) {
                $ext = $file->getClientOriginalExtension();
                $filename = time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext;
            } else {
                $filename = $original;
            }
            $filePath = $file->storeAs('payment_checks', $filename, 'public');

            // Update transaction
            $transaction->update([
                'payment_check' => $filePath,
                'status' => TransactionStatus::Processing
            ]);

            return new TransactionResource($transaction->load(['user', 'payments']));
        });
    }

    /**
     * Export transactions to CSV
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Http\Response
     */
    public function exportTransactions(array $filters = []): \Illuminate\Http\Response
    {
        $query = Transaction::with(['user', 'payments']);

        // Apply same filters as index method
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $csvData = [];
        $csvData[] = ['ID', 'User', 'Email', 'Amount', 'Payment Method', 'Status', 'Linked Payments', 'Created At'];

        foreach ($transactions as $transaction) {
            $linkedPayments = $transaction->payments->pluck('id')->implode(',');
            $csvData[] = [
                $transaction->id,
                $transaction->user->name ?? 'N/A',
                $transaction->user->email ?? 'N/A',
                $transaction->amount,
                $transaction->payment_method,
                $transaction->status->value,
                $linkedPayments,
                $transaction->created_at->format('Y-m-d H:i:s')
            ];
        }

        $csvContent = collect($csvData)->map(function ($row) {
            return implode(',', array_map(function ($cell) {
                return '"' . str_replace('"', '""', (string) $cell) . '"';
            }, $row));
        })->implode("\n");

        $filename = 'transactions_' . now()->format('Y_m_d_H_i_s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Recalculate payment statuses when transaction status changes
     */
    private function recalculatePaymentStatuses(Transaction $transaction): void
    {
        $payments = $transaction->payments;

        foreach ($payments as $payment) {
            $this->recalculateSinglePaymentStatus($payment->id);
        }

        // Update user status based on all payments
        $this->syncUserStatus($transaction->user_id);
    }

    /**
     * Recalculate status for a single payment
     */
    private function recalculateSinglePaymentStatus(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        // Calculate paid_amount from completed transactions
        $paidAmount = $payment->transactions()
            ->where('status', TransactionStatus::Completed)
            ->sum('payment_transaction.amount');

        $payment->paid_amount = $paidAmount;

        // Determine payment status
        if ($paidAmount >= $payment->amount) {
            $payment->status = PaymentStatus::Completed;
        } elseif ($paidAmount > 0) {
            $payment->status = PaymentStatus::PartiallyPaid;
        } else {
            $payment->status = PaymentStatus::Pending;
        }

        $payment->save();

        // Fire event for payment status change
        event(new MailEventOccurred('payment_status_changed', [
            'user' => $payment->user,
            'payment_id' => $payment->id,
            'old_status' => $payment->getOriginal('status'),
            'new_status' => $payment->status->value,
            'amount' => $payment->amount,
            'paid_amount' => $payment->paid_amount,
        ]));
    }

    /**
     * Sync user status based on payment statuses
     */
    private function syncUserStatus(int $userId): void
    {
        $this->userStatusService->syncUserStatus($userId);
    }
}
