<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class DenyGuestRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
