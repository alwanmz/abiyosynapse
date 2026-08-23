<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    private ?int $auditActor = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAudit('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();

            if ($changes === []) {
                return;
            }

            $oldValues = [];
            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $model->getOriginal($key);
            }

            $event = 'updated';
            if (array_key_exists('status', $changes)) {
                $event = match ($changes['status']) {
                    'submitted', 'approval' => 'submitted',
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    'closed' => 'closed',
                    default => 'status_changed',
                };
            }

            $model->writeAudit($event, $oldValues, $changes);
        });

        static::deleted(function (Model $model): void {
            $model->writeAudit('deleted', $model->getAttributes(), null);
        });
    }

    public function auditAs(User|int|null $actor): static
    {
        $this->auditActor = $actor instanceof User ? $actor->getKey() : $actor;

        return $this;
    }

    public function auditActorId(): ?int
    {
        return $this->auditActor;
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    protected function writeAudit(string $event, ?array $oldValues, ?array $newValues): void
    {
        app(AuditTrailService::class)->record($this, $event, $oldValues, $newValues);
    }
}
