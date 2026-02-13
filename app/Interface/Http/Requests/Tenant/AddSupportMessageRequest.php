<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AddSupportMessageRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
