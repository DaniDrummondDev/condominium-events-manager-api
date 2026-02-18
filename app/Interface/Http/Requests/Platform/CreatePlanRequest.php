<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:100', 'unique:platform.plans,slug'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.billing_cycle' => ['required', 'string', 'in:monthly,semiannual,yearly'],
            'prices.*.price' => ['required', 'integer', 'min:0'],
            'prices.*.currency' => ['sometimes', 'string', 'size:3'],
            'prices.*.trial_days' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*.key' => ['required_with:features', 'string', 'max:100'],
            'features.*.value' => ['required_with:features', 'string', 'max:255'],
            'features.*.type' => ['required_with:features', 'string', 'in:boolean,integer,enum'],
        ];
    }
}
