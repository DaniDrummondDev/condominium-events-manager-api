<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeatureOverrideRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'feature_id' => ['required', 'string', 'uuid'],
            'value' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
