<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterViolationRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'uuid'],
            'tenant_user_id' => ['nullable', 'uuid'],
            'reservation_id' => ['nullable', 'uuid'],
            'rule_id' => ['nullable', 'uuid'],
            'type' => ['required', 'string', 'in:no_show,late_cancellation,capacity_exceeded,noise_complaint,damage,rule_violation,other'],
            'severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
