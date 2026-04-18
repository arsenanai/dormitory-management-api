<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transactionService)
    {
    }

    /**
     * Display a listing of transactions (admin view).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $filters = $request->all();
            return $this->transactionService->index($filters);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '42S02') {
                return TransactionResource::collection(
                    new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20)
                );
            }
            throw $e;
        }
    }

    /**
     * Store a newly created transaction (admin manual entry).
     */
    public function store(TransactionRequest $request): TransactionResource
    {
        $data = $request->validated();
        $authUser = $request->user();
        $data['user_id'] = $data['user_id'] ?? ($authUser !== null ? $authUser->id : null);

        return $this->transactionService->create($data);
    }

    /**
     * Display the specified transaction.
     */
    public function show(int $id): TransactionResource
    {
        return $this->transactionService->show($id);
    }

    /**
     * Update the specified transaction (admin approval/rejection).
     */
    public function update(TransactionRequest $request, int $id): TransactionResource
    {
        $data = $request->validated();
        return $this->transactionService->update($id, $data);
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->transactionService->delete($id);
        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    /**
     * Export transactions to CSV (admin only).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->all();
        $csvData = $this->transactionService->exportTransactions($filters);

        $filename = 'transactions_export_' . date('Y-m-d_H-i-s') . '.csv';

        return response()->stream(function () use ($csvData) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Display a listing of current user's transactions (student/guest self-service).
     */
    public function myTransactions(Request $request): AnonymousResourceCollection
    {
        $filters = $request->all();
        $authUser = $request->user();
        $filters['user_id'] = $authUser?->id;

        return $this->transactionService->index($filters);
    }

    /**
     * Store a newly created transaction for current user (student/guest self-service).
     */
    public function myStore(TransactionRequest $request): TransactionResource
    {
        $data = $request->validated();
        $authUser = $request->user();
        $data['user_id'] = $authUser?->id;
        $data['status'] = TransactionStatus::Processing;

        return $this->transactionService->create($data);
    }

    /**
     * Upload/update bank check on own transaction (student/guest self-service).
     */
    public function updateMyTransaction(Request $request, int $id): TransactionResource
    {
        $request->validate([
            'payment_check' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $authUser = $request->user();
        $transaction = Transaction::findOrFail($id);
        if ($authUser !== null && $transaction->user_id !== $authUser->id) {
            abort(403, 'You can only update your own transactions.');
        }

        $file = $request->file('payment_check');
        return $this->transactionService->uploadBankCheck($id, $file);
    }
}
