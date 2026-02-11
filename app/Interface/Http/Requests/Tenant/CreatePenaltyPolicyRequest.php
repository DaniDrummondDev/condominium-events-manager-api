<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CreatePenaltyPolicyRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'violation_type' => ['required', 'string', 'in:no_show,late_cancellation,capacity_exceeded,noise_complaint,damage,rule_violation,other'],
            'occurrence_threshold' => ['required', 'integer', 'min:1'],
            'penalty_type' => ['required', 'string', 'in:warning,temporary_block,permanent_block'],
            'block_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
