<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpaceRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'string', 'in:party_hall,bbq,pool,gym,playground,sports_court,meeting_room,other'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'requires_approval' => ['sometimes', 'boolean'],
            'max_duration_hours' => ['nullable', 'integer', 'min:1'],
            'max_advance_days' => ['sometimes', 'integer', 'min:1'],
            'min_advance_hours' => ['sometimes', 'integer', 'min:0'],
            'cancellation_deadline_hours' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
