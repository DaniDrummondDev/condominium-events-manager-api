<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeatureRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:platform.features,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:boolean,integer,enum'],
            'description' => ['sometimes', 'string'],
        ];
    }
}
