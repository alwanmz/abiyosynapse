<?php

use App\Models\AiActionRun;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AiCopilotService;
use App\Services\CurrentCompany;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder())->run();
    (new RolePermissionSeeder())->run();

    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);

    $this->user = User::factory()->create();
    $role = Role::where('name', 'super_admin')->firstOrFail();
    CompanyUser::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'role_id' => $role->id,
        'is_default' => true,
        'joined_at' => now(),
    ]);
    $this->user->forceFill(['current_company_id' => $this->company->id])->save();

    $this->uom = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS']);
    $this->warehouse = Warehouse::factory()->for($this->company)->create([
        'code' => 'WH-AI',
        'name' => 'AI Warehouse',
    ]);
    $this->product = Product::factory()->for($this->company)->create([
        'code' => 'RM-BUSA',
        'name' => 'Busa Kursi',
        'base_uom_id' => $this->uom->id,
        'default_warehouse_id' => $this->warehouse->id,
        'selling_price' => 125000,
    ]);
});

test('AI read tool returns inventory risk for the active company only', function () {
    StockLevel::factory()->for($this->company)->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 4,
        'average_unit_cost' => 5000,
    ]);

    $otherCompany = Company::factory()->create();
    $otherProduct = Product::factory()->for($otherCompany)->create();
    $otherWarehouse = Warehouse::factory()->for($otherCompany)->create();
    StockLevel::factory()->for($otherCompany)->create([
        'product_id' => $otherProduct->id,
        'warehouse_id' => $otherWarehouse->id,
        'quantity_on_hand' => 0,
    ]);

    $result = app(AiCopilotService::class)->chat('cek risiko stok', $this->user->id);

    expect($result['status'])->toBe('answered')
        ->and($result['tool'])->toBe('inventory_risk')
        ->and($result['data']['rows'])->toHaveCount(1)
        ->and($result['data']['rows'][0]['product_code'])->toBe('RM-BUSA')
        ->and($result['data']['attention_count'])->toBe(1)
        ->and(AiActionRun::where('tool', 'inventory_risk')->where('status', 'executed')->count())->toBe(1);
});

test('AI write tool requires confirmation and creates a draft purchase request only after confirmation', function () {
    $planned = app(AiCopilotService::class)->chat('buat PR RM-BUSA qty 20', $this->user->id);

    expect($planned['status'])->toBe('confirmation_required')
        ->and($planned['tool'])->toBe('draft_purchase_request')
        ->and(PurchaseRequest::count())->toBe(0);

    $run = AiActionRun::findOrFail($planned['action_run_id']);
    expect($run->status)->toBe('planned');

    $executed = app(AiCopilotService::class)->confirm($run, $this->user->id);
    $request = PurchaseRequest::with('lines')->findOrFail($executed['data']['id']);

    expect($executed['status'])->toBe('executed')
        ->and($request->status)->toBe('draft')
        ->and($request->number)->toBe('PR-' . now()->format('Y') . '-000001')
        ->and((float) $request->lines->first()->quantity)->toBe(20.0)
        ->and($run->fresh()->status)->toBe('executed');
});

test('rejecting an AI draft is audited and creates no document', function () {
    $planned = app(AiCopilotService::class)->chat('buat PR RM-BUSA qty 20', $this->user->id);
    $run = AiActionRun::findOrFail($planned['action_run_id']);

    $rejected = app(AiCopilotService::class)->reject($run, $this->user->id);

    expect($rejected['status'])->toBe('rejected')
        ->and($run->fresh()->status)->toBe('rejected')
        ->and(PurchaseRequest::count())->toBe(0);
});

test('AI sales order draft uses the business total calculation', function () {
    $customer = Customer::factory()->for($this->company)->create(['code' => 'CUST-001']);
    $planned = app(AiCopilotService::class)->chat(
        'buat SO RM-BUSA qty 3 customer CUST-001',
        $this->user->id,
    );

    $executed = app(AiCopilotService::class)->confirm(
        AiActionRun::findOrFail($planned['action_run_id']),
        $this->user->id,
    );
    $order = SalesOrder::findOrFail($executed['data']['id']);

    expect($order->status)->toBe('draft')
        ->and((float) $order->subtotal)->toBe(375000.0)
        ->and((float) $order->total)->toBe(375000.0)
        ->and($order->customer_id)->toBe($customer->id);
});

test('AI copilot page is available to an authenticated company member', function () {
    $response = $this->actingAs($this->user)->get('/ai/copilot');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ai/copilot/page')
        ->where('configured', false)
        ->has('tools', 15)
    );
});
