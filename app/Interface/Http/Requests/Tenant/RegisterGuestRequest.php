<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterGuestRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'vehicle_plate' => ['nullable', 'string', 'max:15'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ];
    }
}
