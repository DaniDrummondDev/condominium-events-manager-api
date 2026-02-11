<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CreateBlockRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'alpha_dash', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'floors' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
