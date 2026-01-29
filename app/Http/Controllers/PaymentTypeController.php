<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentTypeRequest;
use App\Http\Resources\PaymentTypeResource;
use App\Models\PaymentType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentTypeResource::collection(PaymentType::all());
    }

    public function store(PaymentTypeRequest $request): PaymentTypeResource
    {
        $paymentType = PaymentType::create($request->validated());

        return new PaymentTypeResource($paymentType);
    }

    public function show(PaymentType $paymentType): PaymentTypeResource
    {
        return new PaymentTypeResource($paymentType);
    }

    public function update(PaymentTypeRequest $request, PaymentType $paymentType): PaymentTypeResource
    {
        $paymentType->update($request->validated());

        return new PaymentTypeResource($paymentType);
    }

    public function destroy(PaymentType $paymentType): Response
    {
        $paymentType->delete();

        return response()->noContent();
    }
}
