<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CancelNFSeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
