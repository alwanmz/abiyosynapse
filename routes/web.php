<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\MrpController;
use App\Http\Controllers\NonConformanceReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\QualityInspectionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockOverviewController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\TaxCodeController;
use App\Http\Controllers\TrialExpiredController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkCenterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

// Own OTP-code email verification flow, replacing Fortify's signed-link
// click flow (see config/fortify.php — Features::emailVerification() is
// disabled there). Route names match what the `verified` middleware
// expects by convention (verification.notice / verification.send), so
// EnsureEmailIsVerified redirects here unchanged.
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailOtpController::class, 'show'])->name('verification.notice');
    Route::post('/email/verify', [EmailOtpController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verify/resend', [EmailOtpController::class, 'resend'])->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reachable even without a company membership yet — EnsureCompanyContext
    // redirects users with zero memberships here, so these two must stay
    // free of any permission: gate.
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index')->middleware('permission:companies.view');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit')->middleware('permission:companies.edit,companies.manage');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update')->middleware('permission:companies.edit,companies.manage');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy')->middleware('permission:companies.delete,companies.manage');

    Route::post('/company/switch', CompanySwitchController::class)->name('company.switch');

    Route::get('/trial-expired', [TrialExpiredController::class, 'show'])->name('trial-expired');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:roles.create,roles.manage');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles.edit,roles.manage');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.delete,roles.manage');

    Route::get('manage-users', [UserController::class, 'index'])->name('manage-users')->middleware('permission:users.view');
    Route::post('manage-users', [UserController::class, 'store'])->name('manage-users.store')->middleware('permission:users.create,users.manage');
    Route::post('manage-users/invite', [UserController::class, 'invite'])->name('manage-users.invite')->middleware('permission:users.create,users.manage');
    Route::put('manage-users/{user}', [UserController::class, 'update'])->name('manage-users.update')->middleware('permission:users.edit,users.manage');
    Route::put('manage-users/{user}/role', [UserController::class, 'updateRole'])->name('manage-users.update-role')->middleware('permission:users.edit,users.manage');
    Route::delete('manage-users/{user}', [UserController::class, 'destroy'])->name('manage-users.destroy')->middleware('permission:users.delete,users.manage');

    Route::middleware('permission:accounts.view,accounts.manage')->group(function () {
        Route::get('master/accounts', [AccountController::class, 'index'])->name('master.accounts.index');
    });
    Route::middleware('permission:accounts.create,accounts.manage')->post('master/accounts', [AccountController::class, 'store'])->name('master.accounts.store');
    Route::middleware('permission:accounts.edit,accounts.manage')->put('master/accounts/{account}', [AccountController::class, 'update'])->name('master.accounts.update');
    Route::middleware('permission:accounts.delete,accounts.manage')->delete('master/accounts/{account}', [AccountController::class, 'destroy'])->name('master.accounts.destroy');

    Route::middleware('permission:master-data.view,master-data.manage')->group(function () {
        Route::get('master/unit-of-measures', [UnitOfMeasureController::class, 'index'])->name('master.uoms.index');
        Route::get('master/warehouses', [WarehouseController::class, 'index'])->name('master.warehouses.index');
        Route::get('master/tax-codes', [TaxCodeController::class, 'index'])->name('master.tax-codes.index');
        Route::get('master/suppliers', [SupplierController::class, 'index'])->name('master.suppliers.index');
        Route::get('master/customers', [CustomerController::class, 'index'])->name('master.customers.index');
        Route::get('master/products', [ProductController::class, 'index'])->name('master.products.index');
    });
    Route::middleware('permission:master-data.manage')->group(function () {
        Route::post('master/unit-of-measures', [UnitOfMeasureController::class, 'store'])->name('master.uoms.store');
        Route::put('master/unit-of-measures/{unitOfMeasure}', [UnitOfMeasureController::class, 'update'])->name('master.uoms.update');
        Route::delete('master/unit-of-measures/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy'])->name('master.uoms.destroy');

        Route::post('master/warehouses', [WarehouseController::class, 'store'])->name('master.warehouses.store');
        Route::put('master/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('master.warehouses.update');
        Route::delete('master/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('master.warehouses.destroy');

        Route::post('master/tax-codes', [TaxCodeController::class, 'store'])->name('master.tax-codes.store');
        Route::put('master/tax-codes/{taxCode}', [TaxCodeController::class, 'update'])->name('master.tax-codes.update');
        Route::delete('master/tax-codes/{taxCode}', [TaxCodeController::class, 'destroy'])->name('master.tax-codes.destroy');

        Route::post('master/suppliers', [SupplierController::class, 'store'])->name('master.suppliers.store');
        Route::put('master/suppliers/{supplier}', [SupplierController::class, 'update'])->name('master.suppliers.update');
        Route::delete('master/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('master.suppliers.destroy');

        Route::post('master/customers', [CustomerController::class, 'store'])->name('master.customers.store');
        Route::put('master/customers/{customer}', [CustomerController::class, 'update'])->name('master.customers.update');
        Route::delete('master/customers/{customer}', [CustomerController::class, 'destroy'])->name('master.customers.destroy');

        Route::post('master/product-categories', [ProductCategoryController::class, 'store'])->name('master.product-categories.store');
        Route::put('master/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('master.product-categories.update');
        Route::delete('master/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('master.product-categories.destroy');

        Route::post('master/products', [ProductController::class, 'store'])->name('master.products.store');
        Route::put('master/products/{product}', [ProductController::class, 'update'])->name('master.products.update');
        Route::delete('master/products/{product}', [ProductController::class, 'destroy'])->name('master.products.destroy');
    });

    Route::middleware('permission:inventory.view,inventory.manage')->group(function () {
        Route::get('inventory/stock-overview', [StockOverviewController::class, 'index'])->name('inventory.stock-overview.index');
        Route::get('inventory/stock-overview/{product}/card', [StockOverviewController::class, 'card'])->name('inventory.stock-overview.card');
        Route::get('inventory/stock-opnames', [StockOpnameController::class, 'index'])->name('inventory.stock-opnames.index');
        Route::get('inventory/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])->name('inventory.stock-opnames.show');
    });
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::post('inventory/stock-opnames', [StockOpnameController::class, 'store'])->name('inventory.stock-opnames.store');
        Route::put('inventory/stock-opnames/{stockOpname}/lines', [StockOpnameController::class, 'updateLines'])->name('inventory.stock-opnames.update-lines');
        Route::post('inventory/stock-opnames/{stockOpname}/complete', [StockOpnameController::class, 'complete'])->name('inventory.stock-opnames.complete');
    });

    Route::middleware('permission:manufacturing.view,manufacturing.manage')->group(function () {
        Route::get('manufacturing/work-centers', [WorkCenterController::class, 'index'])->name('manufacturing.work-centers.index');
        Route::get('manufacturing/boms', [BomController::class, 'index'])->name('manufacturing.boms.index');
        Route::get('manufacturing/routings', [RoutingController::class, 'index'])->name('manufacturing.routings.index');
        Route::get('manufacturing/production-orders', [ProductionOrderController::class, 'index'])->name('manufacturing.production-orders.index');
        Route::get('manufacturing/production-orders/{productionOrder}', [ProductionOrderController::class, 'show'])->name('manufacturing.production-orders.show');
        Route::get('manufacturing/mrp', [MrpController::class, 'index'])->name('manufacturing.mrp.index');
    });
    Route::middleware('permission:manufacturing.manage')->group(function () {
        Route::post('manufacturing/work-centers', [WorkCenterController::class, 'store'])->name('manufacturing.work-centers.store');
        Route::put('manufacturing/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->name('manufacturing.work-centers.update');
        Route::delete('manufacturing/work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->name('manufacturing.work-centers.destroy');

        Route::post('manufacturing/boms', [BomController::class, 'store'])->name('manufacturing.boms.store');
        Route::put('manufacturing/boms/{bom}', [BomController::class, 'update'])->name('manufacturing.boms.update');
        Route::delete('manufacturing/boms/{bom}', [BomController::class, 'destroy'])->name('manufacturing.boms.destroy');

        Route::post('manufacturing/routings', [RoutingController::class, 'store'])->name('manufacturing.routings.store');
        Route::put('manufacturing/routings/{routing}', [RoutingController::class, 'update'])->name('manufacturing.routings.update');
        Route::delete('manufacturing/routings/{routing}', [RoutingController::class, 'destroy'])->name('manufacturing.routings.destroy');

        Route::post('manufacturing/production-orders', [ProductionOrderController::class, 'store'])->name('manufacturing.production-orders.store');
        Route::post('manufacturing/production-orders/{productionOrder}/release', [ProductionOrderController::class, 'release'])->name('manufacturing.production-orders.release');
        Route::post('manufacturing/production-orders/{productionOrder}/issue-materials', [ProductionOrderController::class, 'issueMaterials'])->name('manufacturing.production-orders.issue-materials');
        Route::post('manufacturing/production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('manufacturing.production-orders.complete');
        Route::post('manufacturing/production-orders/{productionOrder}/submit-for-qc', [ProductionOrderController::class, 'submitForQc'])->name('manufacturing.production-orders.submit-for-qc');
        Route::post('manufacturing/operations/{operation}/start', [ProductionOrderController::class, 'startOperation'])->name('manufacturing.operations.start');
        Route::post('manufacturing/operations/{operation}/complete', [ProductionOrderController::class, 'completeOperation'])->name('manufacturing.operations.complete');
    });

    Route::middleware('permission:quality.view,quality.manage')->group(function () {
        Route::get('quality/inspections', [QualityInspectionController::class, 'index'])->name('quality.inspections.index');
        Route::get('quality/ncrs', [NonConformanceReportController::class, 'index'])->name('quality.ncrs.index');
    });
    Route::middleware('permission:quality.manage')->group(function () {
        Route::post('manufacturing/production-orders/{productionOrder}/final-inspection', [QualityInspectionController::class, 'storeFinal'])->name('quality.inspections.store-final');
        Route::post('quality/ncrs/{ncr}/disposition', [NonConformanceReportController::class, 'disposition'])->name('quality.ncrs.disposition');
        Route::post('quality/ncrs/{ncr}/corrective-action', [NonConformanceReportController::class, 'correctiveAction'])->name('quality.ncrs.corrective-action');
        Route::post('quality/ncrs/{ncr}/close', [NonConformanceReportController::class, 'close'])->name('quality.ncrs.close');
    });

    Route::middleware('permission:purchasing.view,purchasing.manage')->group(function () {
        Route::get('purchasing/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchasing.purchase-requests.index');
        Route::get('purchasing/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchasing.purchase-orders.index');
        Route::get('purchasing/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchasing.purchase-orders.show');
        Route::get('purchasing/goods-receipts', [GoodsReceiptController::class, 'index'])->name('purchasing.goods-receipts.index');
        Route::get('purchasing/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('purchasing.goods-receipts.show');
        Route::get('purchasing/supplier-invoices', [SupplierInvoiceController::class, 'index'])->name('purchasing.supplier-invoices.index');
        Route::get('purchasing/supplier-invoices/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('purchasing.supplier-invoices.show');
    });
    Route::middleware('permission:purchasing.manage')->group(function () {
        Route::post('purchasing/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchasing.purchase-requests.store');
        Route::post('purchasing/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchasing.purchase-requests.submit');
        Route::post('purchasing/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchasing.purchase-requests.approve');
        Route::post('purchasing/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('purchasing.purchase-requests.reject');

        Route::post('purchasing/purchase-requests/{purchaseRequest}/purchase-orders', [PurchaseOrderController::class, 'storeFromRequest'])->name('purchasing.purchase-orders.store-from-request');
        Route::post('purchasing/purchase-orders/{purchaseOrder}/submit-for-approval', [PurchaseOrderController::class, 'submitForApproval'])->name('purchasing.purchase-orders.submit-for-approval');
        Route::post('purchasing/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchasing.purchase-orders.approve');
        Route::post('purchasing/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchasing.purchase-orders.send');
        Route::post('purchasing/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchasing.purchase-orders.close');

        Route::post('purchasing/purchase-orders/{purchaseOrder}/goods-receipts', [GoodsReceiptController::class, 'store'])->name('purchasing.goods-receipts.store');
        Route::post('purchasing/goods-receipts/{goodsReceipt}/put-away', [GoodsReceiptController::class, 'putAway'])->name('purchasing.goods-receipts.put-away');

        Route::post('purchasing/purchase-orders/{purchaseOrder}/supplier-invoices', [SupplierInvoiceController::class, 'store'])->name('purchasing.supplier-invoices.store');
    });

    Route::middleware('permission:sales.view,sales.manage')->group(function () {
        Route::get('sales/sales-orders', [SalesOrderController::class, 'index'])->name('sales.sales-orders.index');
        Route::get('sales/sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('sales.sales-orders.show');
        Route::get('sales/delivery-orders', [DeliveryOrderController::class, 'index'])->name('sales.delivery-orders.index');
        Route::get('sales/delivery-orders/{deliveryOrder}', [DeliveryOrderController::class, 'show'])->name('sales.delivery-orders.show');
        Route::get('sales/sales-invoices', [SalesInvoiceController::class, 'index'])->name('sales.sales-invoices.index');
        Route::get('sales/sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show'])->name('sales.sales-invoices.show');
        Route::get('sales/sales-returns', [SalesReturnController::class, 'index'])->name('sales.sales-returns.index');
        Route::get('sales/sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales.sales-returns.show');
    });
    Route::middleware('permission:sales.manage')->group(function () {
        Route::post('sales/sales-orders', [SalesOrderController::class, 'store'])->name('sales.sales-orders.store');
        Route::post('sales/sales-orders/{salesOrder}/submit-for-approval', [SalesOrderController::class, 'submitForApproval'])->name('sales.sales-orders.submit-for-approval');
        Route::post('sales/sales-orders/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->name('sales.sales-orders.approve');
        Route::post('sales/sales-orders/{salesOrder}/close', [SalesOrderController::class, 'close'])->name('sales.sales-orders.close');

        Route::post('sales/sales-orders/{salesOrder}/delivery-orders', [DeliveryOrderController::class, 'store'])->name('sales.delivery-orders.store');
        Route::post('sales/delivery-orders/{deliveryOrder}/ship', [DeliveryOrderController::class, 'ship'])->name('sales.delivery-orders.ship');

        Route::post('sales/sales-orders/{salesOrder}/sales-invoices', [SalesInvoiceController::class, 'store'])->name('sales.sales-invoices.store');

        Route::post('sales/sales-invoices/{salesInvoice}/sales-returns', [SalesReturnController::class, 'store'])->name('sales.sales-returns.store');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('notifications/clear-all', [NotificationController::class, 'destroyAll'])->name('notifications.clear-all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

require __DIR__.'/settings.php';
