<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Communication\DTOs\AnnouncementDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AnnouncementDTO $resource
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'priority' => $this->resource->priority,
            'audience_type' => $this->resource->audienceType,
            'audience_ids' => $this->resource->audienceIds,
            'status' => $this->resource->status,
            'published_by' => $this->resource->publishedBy,
            'published_at' => $this->resource->publishedAt,
            'expires_at' => $this->resource->expiresAt,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
