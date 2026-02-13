<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceProviderRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'service_type' => ['sometimes', 'string', 'in:buffet,cleaning,decoration,dj,security,maintenance,moving,other'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
