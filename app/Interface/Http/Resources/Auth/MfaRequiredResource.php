<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Auth;

use Application\Auth\DTOs\MfaRequiredDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MfaRequiredDTO $resource
 */
class MfaRequiredResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mfa_required' => $this->resource->mfaRequired,
            'mfa_token' => $this->resource->mfaToken,
            'expires_in' => $this->resource->mfaTokenExpiresIn,
            'methods' => $this->resource->mfaMethods,
        ];
    }
}
