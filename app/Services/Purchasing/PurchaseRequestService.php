<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use RuntimeException;

/**
 * Drives a Purchase Request through Draft -> Submitted -> Approved/Rejected
 * (blueprint §8, §17). Approval is a prerequisite for converting lines into
 * a Purchase Order (see PurchaseOrderService::createFromRequest()).
 */
class PurchaseRequestService
{
    public function submit(PurchaseRequest $request): PurchaseRequest
    {
        if ($request->status !== 'draft') {
            throw new RuntimeException("Purchase request {$request->number} must be a draft before it can be submitted.");
        }

        $request->update(['status' => 'submitted', 'submitted_at' => now()]);

        return $request->fresh();
    }

    public function approve(PurchaseRequest $request, User $approver): PurchaseRequest
    {
        if ($request->status !== 'submitted') {
            throw new RuntimeException("Purchase request {$request->number} must be submitted before it can be approved.");
        }

        app(ApprovalWorkflowService::class)->assertCanApprove($request, $approver, ['requested_by']);

        $request->auditAs($approver)->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return $request->fresh();
    }

    public function reject(PurchaseRequest $request, User $approver, ?string $reason = null): PurchaseRequest
    {
        if ($request->status !== 'submitted') {
            throw new RuntimeException("Purchase request {$request->number} must be submitted before it can be rejected.");
        }

        app(ApprovalWorkflowService::class)->assertCanApprove($request, $approver, ['requested_by']);

        $request->auditAs($approver)->update([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh();
    }
}
