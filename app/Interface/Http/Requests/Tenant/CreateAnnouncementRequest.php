<?php

declare(strict_types=1);

namespace App\Interface\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CreateAnnouncementRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'audience_type' => ['required', 'string', 'in:all,block,units'],
            'audience_ids' => ['nullable', 'array'],
            'audience_ids.*' => ['string', 'uuid'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
