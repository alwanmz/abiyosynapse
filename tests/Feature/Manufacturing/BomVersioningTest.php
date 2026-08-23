<?php

use App\Models\Bom;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CurrentCompany;
use Database\Seeders\CompanySeeder;

beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->firstOrFail();
    app(CurrentCompany::class)->set($this->company);

    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(
        ['name' => 'super_admin', 'company_id' => null],
        ['display_name' => 'Super Admin', 'description' => 'Full system access'],
    );
    CompanyUser::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'role_id' => $role->id,
        'is_default' => true,
        'joined_at' => now(),
    ]);
    $this->user->forceFill(['current_company_id' => $this->company->id])->save();

    $this->uom = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS']);
    $this->material = Product::factory()->for($this->company)->create(['code' => 'MAT-REV', 'base_uom_id' => $this->uom->id]);
    $this->product = Product::factory()->for($this->company)->manufactured()->create([
        'code' => 'FG-REV',
        'base_uom_id' => $this->uom->id,
    ]);
    $this->bom = Bom::factory()->for($this->company)->create([
        'product_id' => $this->product->id,
        'code' => 'BOM-FG-REV',
        'version' => 1,
        'status' => 'active',
    ]);
    $this->bom->lines()->create([
        'component_id' => $this->material->id,
        'quantity_per_batch' => 2,
        'uom_id' => $this->uom->id,
        'sequence' => 10,
    ]);
    $this->product->update(['bom_id' => $this->bom->id]);
});

test('new BOM revision clones the previous structure as a draft', function () {
    $response = $this->actingAs($this->user)->post(route('manufacturing.boms.new-version', $this->bom), [
        'revision_reason' => 'Material substitution',
    ]);

    $response->assertRedirect();
    $revision = Bom::where('product_id', $this->product->id)->where('version', 2)->firstOrFail();

    expect($revision->status)->toBe('draft')
        ->and($revision->code)->toBe($this->bom->code)
        ->and($revision->revision_reason)->toBe('Material substitution')
        ->and($revision->lines)->toHaveCount(1)
        ->and((float) $revision->lines->first()->quantity_per_batch)->toBe(2.0)
        ->and($this->bom->fresh()->status)->toBe('active');
});

test('approved or active BOM cannot be edited in place', function () {
    $response = $this->actingAs($this->user)->put(route('manufacturing.boms.update', $this->bom), [
        'product_id' => $this->product->id,
        'code' => 'MUTATED',
        'batch_quantity' => 1,
        'status' => 'active',
        'lines' => [[
            'component_id' => $this->material->id,
            'quantity_per_batch' => 99,
            'uom_id' => $this->uom->id,
            'scrap_percentage' => 0,
        ]],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($this->bom->fresh()->code)->toBe('BOM-FG-REV')
        ->and((float) $this->bom->fresh()->lines->first()->quantity_per_batch)->toBe(2.0);
});

test('activating a draft revision obsoletes the previous revision and updates the product pointer', function () {
    $revision = $this->bom->replicate();
    $revision->version = 2;
    $revision->status = 'draft';
    $revision->revision_reason = 'Approved revision';
    $revision->save();
    $revision->lines()->create([
        'component_id' => $this->material->id,
        'quantity_per_batch' => 3,
        'uom_id' => $this->uom->id,
        'sequence' => 10,
    ]);

    $response = $this->actingAs($this->user)->put(route('manufacturing.boms.update', $revision), [
        'product_id' => $this->product->id,
        'code' => $revision->code,
        'batch_quantity' => 1,
        'status' => 'active',
        'effective_from' => now()->toDateString(),
        'lines' => [[
            'component_id' => $this->material->id,
            'quantity_per_batch' => 3,
            'uom_id' => $this->uom->id,
            'scrap_percentage' => 0,
        ]],
    ]);

    $response->assertRedirect();
    expect($this->bom->fresh()->status)->toBe('obsolete')
        ->and($revision->fresh()->status)->toBe('active')
        ->and($this->product->fresh()->bom_id)->toBe($revision->id)
        ->and($revision->fresh()->approved_at)->not->toBeNull();
});
