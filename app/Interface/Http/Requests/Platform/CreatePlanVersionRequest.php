<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanVersionRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*.key' => ['required_with:features', 'string', 'max:100'],
            'features.*.value' => ['required_with:features', 'string', 'max:255'],
            'features.*.type' => ['required_with:features', 'string', 'in:boolean,integer,enum'],
        ];
    }
}
