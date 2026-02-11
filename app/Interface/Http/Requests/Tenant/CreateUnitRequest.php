<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CreateUnitRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'block_id' => ['nullable', 'string', 'uuid'],
            'number' => ['required', 'string', 'max:50'],
            'floor' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:apartment,house,store,office,other'],
        ];
    }
}
