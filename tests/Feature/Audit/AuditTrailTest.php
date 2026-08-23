<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseRequestService;
use App\Services\Sales\SalesOrderService;
use App\Services\AuditTrailService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = $this->user->fresh()->currentCompany;
    app(CurrentCompany::class)->set($this->company);
    $this->actingAs($this->user);
});

test('auditable documents record creation and field changes with the active actor', function () {
    $request = PurchaseRequest::factory()->for($this->company)->create();

    $created = AuditLog::where('auditable_type', PurchaseRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'created')
        ->firstOrFail();

    expect($created->user_id)->toBe($this->user->id)
        ->and($created->new_values['status'])->toBe('draft');

    $request->update(['notes' => 'Updated note']);

    $updated = AuditLog::where('auditable_type', PurchaseRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($updated->old_values['notes'])->toBeNull()
        ->and($updated->new_values['notes'])->toBe('Updated note');
});

test('approval and rejection records the explicit approver and rejection reason', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $request = PurchaseRequest::factory()->for($this->company)->create([
        'status' => 'submitted',
        'requested_by' => $requester->id,
    ]);

    app(PurchaseRequestService::class)->approve($request, $approver);

    $approved = AuditLog::where('auditable_type', PurchaseRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'approved')
        ->latest('id')
        ->firstOrFail();

    expect($approved->user_id)->toBe($approver->id)
        ->and($approved->old_values['status'])->toBe('submitted')
        ->and($approved->new_values['status'])->toBe('approved');

    $request->update(['status' => 'submitted', 'approved_by' => null, 'approved_at' => null]);
    app(PurchaseRequestService::class)->reject($request, $approver, 'Budget needs review');

    expect($request->fresh()->rejected_by)->toBe($approver->id)
        ->and($request->fresh()->rejection_reason)->toBe('Budget needs review');

    $rejected = AuditLog::where('auditable_type', PurchaseRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'rejected')
        ->latest('id')
        ->firstOrFail();

    expect($rejected->user_id)->toBe($approver->id)
        ->and($rejected->new_values['rejection_reason'])->toBe('Budget needs review');
});

test('a document creator cannot approve their own purchase request, purchase order, or sales order', function () {
    $purchaseRequest = PurchaseRequest::factory()->for($this->company)->create([
        'status' => 'submitted',
        'requested_by' => $this->user->id,
    ]);
    $purchaseOrder = PurchaseOrder::factory()->for($this->company)->create([
        'status' => 'approval',
        'created_by' => $this->user->id,
    ]);
    $salesOrder = SalesOrder::factory()->for($this->company)->create([
        'status' => 'approval',
        'created_by' => $this->user->id,
    ]);

    expect(fn () => app(PurchaseRequestService::class)->approve($purchaseRequest, $this->user))
        ->toThrow(RuntimeException::class, 'creator cannot approve');
    expect(fn () => app(PurchaseOrderService::class)->approve($purchaseOrder, $this->user))
        ->toThrow(RuntimeException::class, 'creator cannot approve');
    expect(fn () => app(SalesOrderService::class)->approve($salesOrder, $this->user))
        ->toThrow(RuntimeException::class, 'creator cannot approve');
});

test('audit records redact sensitive values', function () {
    $request = PurchaseRequest::factory()->for($this->company)->create();

    $log = app(AuditTrailService::class)->record(
        $request,
        'updated',
        ['password' => 'old', 'name' => 'before', 'email_otp_code' => '123456'],
        ['password' => 'new', 'name' => 'after', 'email_otp_code' => '654321'],
    );

    expect($log->old_values)->toBe(['name' => 'before'])
        ->and($log->new_values)->toBe(['name' => 'after']);
});

test('audit trail page is available to an authenticated company administrator', function () {
    $response = $this->get('/audit-logs');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('audit-logs/page')
            ->has('logs.data')
            ->has('events')
            ->where('filters.event', '')
            ->where('filters.search', '')
        );
});
