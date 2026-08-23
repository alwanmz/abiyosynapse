<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\Auditable;

class ReportExportRun extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'company_id', 'user_id', 'report_code', 'format', 'reporting_standard',
        'language', 'functional_currency', 'presentation_currency', 'parameters',
        'status', 'error_message',
    ];

    protected function casts(): array
    {
        return ['parameters' => 'array'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
