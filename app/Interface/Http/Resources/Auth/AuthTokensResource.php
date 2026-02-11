<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Auth;

use Application\Auth\DTOs\AuthTokensDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AuthTokensDTO $resource
 */
class AuthTokensResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'refresh_token' => $this->resource->refreshToken,
            'token_type' => $this->resource->tokenType,
            'expires_in' => $this->resource->expiresIn,
        ];
    }
}
