<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterServiceProviderRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_type' => ['required', 'string', 'in:buffet,cleaning,decoration,dj,security,maintenance,moving,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
