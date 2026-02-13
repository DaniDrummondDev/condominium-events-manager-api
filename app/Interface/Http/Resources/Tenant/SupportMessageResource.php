<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Communication\DTOs\SupportMessageDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SupportMessageDTO $resource
 */
class SupportMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'support_request_id' => $this->resource->supportRequestId,
            'sender_id' => $this->resource->senderId,
            'body' => $this->resource->body,
            'is_internal' => $this->resource->isInternal,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
