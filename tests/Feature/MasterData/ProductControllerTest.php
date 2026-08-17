<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;

function makePermissiveUser(array $permissionNames): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $permissions = \App\Models\Permission::whereIn('name', $permissionNames)->get();
    $role->permissions()->sync($permissions->pluck('id'));
    $user->currentCompanyMembership()->update(['role_id' => $role->id]);

    return $user->fresh();
}

test('user with master-data.view can see the product index', function () {
    $user = User::factory()->create(); // super_admin by default, sees everything
    UnitOfMeasure::factory()->for($user->currentCompany)->create();

    $response = $this->actingAs($user)->get('/master/products');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('master/products/page'));
});

test('a product can be created via the store endpoint', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();

    $response = $this->actingAs($user)->post('/master/products', [
        'code' => 'CHR-X1',
        'name' => 'Kursi Kantor X1',
        'type' => 'manufactured',
        'base_uom_id' => $uom->id,
        'make_or_buy' => 'make',
        'standard_cost' => 750000,
        'selling_price' => 1250000,
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'code' => 'CHR-X1',
        'company_id' => $user->currentCompany->id,
    ]);
});

test('product code must be unique per company', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    Product::factory()->for($user->currentCompany)->create(['code' => 'CHR-X1']);

    $response = $this->actingAs($user)->post('/master/products', [
        'code' => 'CHR-X1',
        'name' => 'Duplicate',
        'type' => 'purchased',
        'base_uom_id' => $uom->id,
        'make_or_buy' => 'buy',
        'standard_cost' => 1000,
        'selling_price' => 2000,
        'status' => 'draft',
    ]);

    $response->assertSessionHasErrors('code');
});

test('a product can be updated', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);

    $response = $this->actingAs($user)->put("/master/products/{$product->id}", [
        'code' => $product->code,
        'name' => 'Updated Name',
        'type' => $product->type,
        'base_uom_id' => $uom->id,
        'make_or_buy' => $product->make_or_buy,
        'standard_cost' => 999,
        'selling_price' => 1999,
        'status' => 'active',
    ]);

    $response->assertRedirect();
    expect($product->fresh()->name)->toBe('Updated Name');
});

test('a product can be deleted', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $product = Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);

    $response = $this->actingAs($user)->delete("/master/products/{$product->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('a user without master-data permission cannot view products', function () {
    $user = makePermissiveUser(['users.view']); // unrelated permission only

    $response = $this->actingAs($user)->get('/master/products');

    $response->assertForbidden();
});

test('uom with a product attached cannot be deleted', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    Product::factory()->for($user->currentCompany)->create(['base_uom_id' => $uom->id]);

    $response = $this->actingAs($user)->delete("/master/unit-of-measures/{$uom->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('unit_of_measures', ['id' => $uom->id]);
});

test('product category cascades products relation and blocks deletion when in use', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $category = ProductCategory::factory()->for($user->currentCompany)->create();
    Product::factory()->for($user->currentCompany)->create([
        'base_uom_id' => $uom->id,
        'product_category_id' => $category->id,
    ]);

    $response = $this->actingAs($user)->delete("/master/product-categories/{$category->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
});

test('warehouse with a product attached cannot be deleted', function () {
    $user = User::factory()->create();
    $uom = UnitOfMeasure::factory()->for($user->currentCompany)->create();
    $warehouse = Warehouse::factory()->for($user->currentCompany)->create();
    Product::factory()->for($user->currentCompany)->create([
        'base_uom_id' => $uom->id,
        'default_warehouse_id' => $warehouse->id,
    ]);

    $response = $this->actingAs($user)->delete("/master/warehouses/{$warehouse->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
});
