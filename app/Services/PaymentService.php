<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * Get payments with filters and pagination
     */
    public function index(array $filters = [])
    {
        $query = Payment::with([ 'user', 'user.role' ]);

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
                $q->where('deal_number', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
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

        $perPage = $filters['per_page'] ?? 20;

        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return PaymentResource::collection($payments);
    }

    /**
     * Create a new payment
     */
    public function create(array $data, User $user): PaymentResource
    {
        return DB::transaction(function () use ($data) {
            // Set deal_date if not provided
            if (! isset($data['deal_date'])) {
                $dealDate = isset($data['date_from']) ? new \DateTime($data['date_from']) : new \DateTime();
                $data['deal_date'] = $dealDate->format('Y-m-d');
            }

            // If amount is not provided, try to get it from the student's room type semester_rate
            if (! isset($data['amount'])) {
                $user = User::with('room.roomType', 'role')->find($data['user_id']);
                if ($user && $user->hasRole('student') && $user->room && $user->room->roomType) {
                    $data['amount'] = $user->room->roomType->semester_rate;
                } else {
                    // Fallback or throw an error if amount is mandatory and cannot be determined
                    $data['amount'] = 0;
                }
            }

            // Handle payment_check file upload (only store if it's an UploadedFile)
            if (isset($data['payment_check']) && $data['payment_check'] instanceof \Illuminate\Http\UploadedFile) {
                // Preserve original filename when possible. If a file with the
                // same name already exists, generate a short unique filename.
                $original = $data['payment_check']->getClientOriginalName();
                $storagePath = 'payment_checks/' . $original;
                if (Storage::disk('local')->exists($storagePath)) {
                    $ext = $data['payment_check']->getClientOriginalExtension();
                    $filename = time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext;
                } else {
                    $filename = $original;
                }
                $data['payment_check'] = $data['payment_check']->storeAs('payment_checks', $filename, 'local');
            }

            // Set initial status for student/guest roles
            if ($user->hasRole('student') || $user->hasRole('guest')) {
                $data['status'] = PaymentStatus::Pending;
            }

            $payment = Payment::create($data);

            return new PaymentResource($payment->load([ 'user', 'user.role' ]));
        });
    }

    // public function create( array $data ): Payment {
    // 	return DB::transaction( function () use ($data) {
    // 		if ( isset( $data['payment_check'] ) && $data['payment_check'] instanceof \Illuminate\Http\UploadedFile ) {
    // 			$original = $data['payment_check']->getClientOriginalName();
    // 			$storagePath = 'payment_checks/' . $original;
    // 			if ( Storage::disk( 'local' )->exists( $storagePath ) ) {
    // 				$ext = $data['payment_check']->getClientOriginalExtension();
    // 				$filename = time() . '_' . \Illuminate\Support\Str::random( 6 ) . '.' . $ext;
    // 			} else {
    // 				$filename = $original;
    // 			}
    // 			$data['payment_check'] = $data['payment_check']->storeAs( 'payment_checks', $filename, 'local' );
    // 		}
    // 		$payment = new Payment( $data );
    // 		return $payment;
    // 	} );
    // }

    // public function update( Payment $payment, array $data ): Payment {
    // 	return DB::transaction( function () use ($data, $payment) {
    // 		if ( isset( $data['payment_check'] ) ) {
    // 			// If a new uploaded file is provided, delete the old file and store the new one.
    // 			if ( $data['payment_check'] instanceof \Illuminate\Http\UploadedFile ) {
    // 				if ( $payment->payment_check ) {
    // 					Storage::disk( 'local' )->delete( $payment->payment_check );
    // 				}
    // 				$original = $data['payment_check']->getClientOriginalName();
    // 				$storagePath = 'payment_checks/' . $original;
    // 				if ( Storage::disk( 'local' )->exists( $storagePath ) ) {
    // 					$ext = $data['payment_check']->getClientOriginalExtension();
    // 					$filename = time() . '_' . \Illuminate\Support\Str::random( 6 ) . '.' . $ext;
    // 				} else {
    // 					$filename = $original;
    // 				}
    // 				$data['payment_check'] = $data['payment_check']->storeAs( 'payment_checks', $filename, 'local' );
    // 			}
    // 			// If an empty string is sent, it's a signal to delete the file.
    // 			elseif ( $data['payment_check'] === '' ) {
    // 				if ( $payment->payment_check ) {
    // 					Storage::disk( 'local' )->delete( $payment->payment_check );
    // 				}
    // 				// Set to null to clear the database field.
    // 				$data['payment_check'] = null;
    // 			}
    // 		}
    // 		$payment->update( $data );
    // 		return $payment;
    // 	} );
    // }

    /**
     * Get payment details
     */
    public function getPaymentDetails($id)
    {
        $payment = Payment::with([ 'user' ])->findOrFail($id);
        return new PaymentResource($payment);
    }

    /**
     * Update payment
     */
    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $payment = Payment::findOrFail($id);

            // Handle the 3 scenarios for payment_check file
            if (array_key_exists('payment_check', $data)) { // Use array_key_exists to detect null/empty string
                // 1. New file is uploaded
                if ($data['payment_check'] instanceof \Illuminate\Http\UploadedFile) {
                    // ... (upload logic is correct)
                    if ($payment->payment_check) {
                        Storage::disk('local')->delete($payment->payment_check);
                    }
                    // ... store new file
                    $original = $data['payment_check']->getClientOriginalName();
                    $storagePath = 'payment_checks/' . $original;
                    $filename = Storage::disk('local')->exists($storagePath)
                        ? time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $data['payment_check']->getClientOriginalExtension()
                        : $original;
                    $data['payment_check'] = $data['payment_check']->storeAs('payment_checks', $filename, 'local');

                }
                // 2. An empty string or null is sent to signal deletion.
                elseif ($data['payment_check'] === null || $data['payment_check'] === '') {
                    //if ( $payment->payment_check ) {
                    Storage::disk('local')->delete($payment->payment_check);
                    //}
                    $data['payment_check'] = null; // Set to null to clear DB field
                }
            } else {
                // 3. If payment_check is not in the request, leave it untouched.
                unset($data['payment_check']);
            }

            $payment->update($data);

            return new PaymentResource($payment->load([ 'user', 'user.role' ]));
        });
    }

    /**
     * Delete payment
     */
    public function delete($id)
    {
        $payment = Payment::findOrFail($id);

        // Delete associated payment_check file
        if ($payment->payment_check) {
            Storage::disk('local')->delete($payment->payment_check);
        }

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

        $payments = $query->orderBy('deal_date', 'desc')->get();

        // Create CSV content
        $csvContent = "Payment ID,Student Name,Student Email,Deal Number,Deal Date,Amount,Date From,Date To\n";

        foreach ($payments as $payment) {
            $dealDate = $payment->deal_date ? (new \DateTime($payment->deal_date))->format('Y-m-d') : '';
            $dateFrom = $payment->date_from ? (new \DateTime($payment->date_from))->format('Y-m-d') : ''; // Assuming date_from is a string or Carbon instance
            $dateTo = $payment->date_to ? (new \DateTime($payment->date_to))->format('Y-m-d') : ''; // Assuming date_to is a string or Carbon instance

            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $payment->id,
                '"' . str_replace('"', '""', $payment->user->name ?? '') . '"',
                ($payment->user->email ?? ''), // Null-safe for user->email
                '"' . str_replace('"', '""', $payment->deal_number ?? '') . '"',
                $dealDate,
                $payment->amount,
                $dateFrom,
                $dateTo
            );
        }

        $filename = 'payments_export_' . date('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
