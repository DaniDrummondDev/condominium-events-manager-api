<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleVisitRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'service_provider_id' => ['required', 'uuid'],
            'unit_id' => ['required', 'uuid'],
            'reservation_id' => ['nullable', 'uuid'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'purpose' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
