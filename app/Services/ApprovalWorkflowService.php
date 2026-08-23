<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ApprovalWorkflowService
{
    /**
     * Enforce four-eyes approval where a document identifies its creator or
     * requester. Legacy/system-created documents with no actor remain
     * approvable, which keeps imports and existing seeded data usable.
     *
     * @param array<int, string> $actorFields
     */
    public function assertCanApprove(Model $document, User $approver, array $actorFields = ['created_by', 'requested_by']): void
    {
        foreach ($actorFields as $field) {
            $actorId = $document->getAttribute($field);

            if ($actorId !== null && (int) $actorId === (int) $approver->id) {
                throw new RuntimeException('The document creator cannot approve their own document.');
            }
        }
    }
}
