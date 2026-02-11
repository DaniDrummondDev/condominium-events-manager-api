<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TenantLoginRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'tenant_slug' => ['required', 'string', 'alpha_dash'],
        ];
    }
}
