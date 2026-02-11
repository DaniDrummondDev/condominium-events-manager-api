<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CancelSubscriptionRequest;
use App\Interface\Http\Resources\Platform\SubscriptionResource;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\CancelSubscriptionDTO;
use Application\Billing\DTOs\SubscriptionDTO;
use Application\Billing\UseCases\CancelSubscription;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController
{
    public function index(SubscriptionRepositoryInterface $subscriptionRepository): AnonymousResourceCollection
    {
        // Platform admin view — list would normally be paginated/filtered
        // For now, this returns an empty collection as subscriptions are per-tenant
        return SubscriptionResource::collection([]);
    }

    public function show(string $id, SubscriptionRepositoryInterface $subscriptionRepository): JsonResponse
    {
        $subscription = $subscriptionRepository->findById(Uuid::fromString($id));

        if ($subscription === null) {
            return new JsonResponse([
                'error' => 'SUBSCRIPTION_NOT_FOUND',
                'message' => 'Subscription not found',
            ], 404);
        }

        $dto = new SubscriptionDTO(
            id: $subscription->id()->value(),
            tenantId: $subscription->tenantId()->value(),
            planVersionId: $subscription->planVersionId()->value(),
            status: $subscription->status()->value,
            billingCycle: $subscription->billingCycle()->value,
            currentPeriodStart: $subscription->currentPeriod()->start()->format('c'),
            currentPeriodEnd: $subscription->currentPeriod()->end()->format('c'),
            gracePeriodEnd: $subscription->gracePeriodEnd()?->format('c'),
            canceledAt: $subscription->canceledAt()?->format('c'),
        );

        return (new SubscriptionResource($dto))->response();
    }

    public function cancel(
        string $id,
        CancelSubscriptionRequest $request,
        CancelSubscription $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CancelSubscriptionDTO(
                subscriptionId: $id,
                cancellationType: $request->validated('cancellation_type'),
            ));

            return (new SubscriptionResource($result))->response();
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
