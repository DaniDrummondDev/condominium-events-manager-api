<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NFSeDocumentModel extends Model
{
    use HasUuids;

    protected $connection = 'platform';

    protected $table = 'nfse_documents';

    protected $fillable = [
        'id',
        'tenant_id',
        'invoice_id',
        'status',
        'provider_ref',
        'nfse_number',
        'verification_code',
        'service_description',
        'competence_date',
        'total_amount',
        'iss_rate',
        'iss_amount',
        'pdf_url',
        'xml_content',
        'provider_response',
        'authorized_at',
        'cancelled_at',
        'error_message',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'competence_date' => 'date',
            'authorized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'provider_response' => 'array',
            'iss_rate' => 'float',
        ];
    }
}
