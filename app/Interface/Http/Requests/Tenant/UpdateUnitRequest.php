<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'string', 'max:50'],
            'floor' => ['sometimes', 'nullable', 'integer'],
            'type' => ['sometimes', 'string', 'in:apartment,house,store,office,other'],
        ];
    }
}
