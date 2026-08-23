<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrailService
{
    /**
     * Record a business-document event without coupling the document models
     * to HTTP or authentication details.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(
        Model $model,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $actorId = null,
    ): AuditLog {
        $request = app()->bound('request') ? app(Request::class) : null;
        $companyId = $model->getAttribute('company_id') ?? app(CurrentCompany::class)->id();

        return AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $actorId ?? ($model->auditActorId() ?? auth()->id()),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'description' => $description ?? $this->defaultDescription($model, $event, $oldValues, $newValues),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function defaultDescription(Model $model, string $event, ?array $oldValues, ?array $newValues): string
    {
        $label = $model->getAttribute('number')
            ?? $model->getAttribute('code')
            ?? ('#' . $model->getKey());

        if (isset($oldValues['status'], $newValues['status'])) {
            return sprintf('Status changed from %s to %s for %s.', $oldValues['status'], $newValues['status'], $label);
        }

        return ucfirst(str_replace('_', ' ', $event)) . ' ' . $label . '.';
    }

    /**
     * Keep credentials, tokens, OTP material, and encrypted recovery data out
     * of the audit trail even when a future model opts into Auditable.
     *
     * @param array<string, mixed>|null $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitive = ['password', 'secret', 'token', 'otp', 'recovery', 'remember'];

        return collect($values)->reject(function ($value, $key) use ($sensitive) {
            $key = strtolower((string) $key);

            foreach ($sensitive as $needle) {
                if (str_contains($key, $needle)) {
                    return true;
                }
            }

            return false;
        })->all();
    }
}
