<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\IssueRefundRequest;
use App\Interface\Http\Resources\Platform\PaymentResource;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\UseCases\IssueRefund;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;

class PaymentController
{
    public function show(string $id, PaymentRepositoryInterface $paymentRepository): JsonResponse
    {
        $payment = $paymentRepository->findById(Uuid::fromString($id));

        if ($payment === null) {
            return new JsonResponse([
                'error' => 'PAYMENT_NOT_FOUND',
                'message' => 'Payment not found',
            ], 404);
        }

        return (new PaymentResource($payment))->response();
    }

    public function refund(
        string $id,
        IssueRefundRequest $request,
        IssueRefund $useCase,
    ): JsonResponse {
        try {
            $payment = $useCase->execute(
                $id,
                $request->validated('amount'),
                $request->validated('reason'),
            );

            return (new PaymentResource($payment))->response();
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
