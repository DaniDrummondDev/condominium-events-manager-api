<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'condominium.name' => ['required', 'string', 'max:255'],
            'condominium.slug' => ['required', 'string', 'alpha_dash', 'max:100', 'unique:platform.tenants,slug'],
            'condominium.type' => ['required', 'string', 'in:horizontal,vertical,mixed'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin.phone' => ['nullable', 'string', 'max:20'],
            'plan_slug' => ['required', 'string', 'exists:platform.plans,slug'],
        ];
    }
}
