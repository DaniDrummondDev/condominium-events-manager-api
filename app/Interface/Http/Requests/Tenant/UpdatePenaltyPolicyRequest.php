<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePenaltyPolicyRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'occurrence_threshold' => ['nullable', 'integer', 'min:1'],
            'penalty_type' => ['nullable', 'string', 'in:warning,temporary_block,permanent_block'],
            'block_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
