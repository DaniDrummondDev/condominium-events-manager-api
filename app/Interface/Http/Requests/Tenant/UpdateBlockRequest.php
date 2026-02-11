<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlockRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'identifier' => ['sometimes', 'string', 'alpha_dash', 'max:50'],
            'floors' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
