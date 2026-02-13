<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CloseSupportRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'in:resolved,admin_closed'],
        ];
    }
}
