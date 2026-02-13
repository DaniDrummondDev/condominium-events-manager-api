<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\AI;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Infrastructure\MultiTenancy\TenantContext;
use Application\AI\DTOs\ChatRequestDTO;
use Application\AI\DTOs\SuggestRequestDTO;
use Application\AI\UseCases\ConfirmAction;
use Application\AI\UseCases\ListPendingActions;
use Application\AI\UseCases\ProcessChat;
use Application\AI\UseCases\ProcessSuggestion;
use Application\AI\UseCases\RejectAction;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController
{
    public function chat(
        Request $request,
        ProcessChat $useCase,
        AuthenticatedUser $user,
        TenantContext $tenantContext,
    ): JsonResponse {
        $request->validate([
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string|uuid',
        ]);

        try {
            $result = $useCase->execute(
                dto: new ChatRequestDTO(
                    message: $request->input('message'),
                    sessionId: $request->input('session_id'),
                ),
                tenantUserId: $user->userId->value(),
                tenantName: $tenantContext->tenantName,
                userName: $user->userId->value(),
                userRole: $user->roles[0] ?? '',
            );

            return response()->json(['data' => $result]);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function suggest(
        Request $request,
        ProcessSuggestion $useCase,
        AuthenticatedUser $user,
        TenantContext $tenantContext,
    ): JsonResponse {
        $request->validate([
            'context' => 'required|string|max:500',
            'space_id' => 'nullable|string|uuid',
            'date' => 'nullable|date',
        ]);

        try {
            $result = $useCase->execute(
                dto: new SuggestRequestDTO(
                    context: $request->input('context'),
                    spaceId: $request->input('space_id'),
                    date: $request->input('date'),
                ),
                tenantUserId: $user->userId->value(),
                tenantName: $tenantContext->tenantName,
                userRole: $user->roles[0] ?? '',
            );

            return response()->json(['data' => $result]);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function pendingActions(
        ListPendingActions $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        $actions = $useCase->execute($user->userId->value());

        return response()->json(['data' => $actions]);
    }

    public function confirmAction(
        string $id,
        ConfirmAction $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return response()->json(['data' => $result]);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function rejectAction(
        string $id,
        Request $request,
        RejectAction $useCase,
    ): JsonResponse {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $useCase->execute($id, $request->input('reason'));

            return response()->json(['data' => ['status' => 'rejected']]);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
