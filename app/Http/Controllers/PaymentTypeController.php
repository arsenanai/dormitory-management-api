<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function index()
    {
        return response()->json(PaymentType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:payment_types,name|max:255',
        ]);

        $paymentType = PaymentType::create($validated);
        return response()->json($paymentType, 201);
    }

    public function show(PaymentType $paymentType)
    {
        return response()->json($paymentType);
    }

    public function update(Request $request, PaymentType $paymentType)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:payment_types,name,' . $paymentType->id . '|max:255',
        ]);

        $paymentType->update($validated);
        return response()->json($paymentType);
    }

    public function destroy(PaymentType $paymentType)
    {
        $paymentType->delete();
        return response()->json(['message' => 'Payment type deleted successfully']);
    }
}
