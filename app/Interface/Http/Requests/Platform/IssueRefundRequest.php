<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class IssueRefundRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10'],
        ];
    }
}
