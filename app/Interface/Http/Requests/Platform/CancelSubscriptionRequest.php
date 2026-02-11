<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'cancellation_type' => ['required', 'string', 'in:immediate,end_of_period'],
        ];
    }
}
