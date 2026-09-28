<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AiCopilotController;
use App\Http\Controllers\AiDocumentController;
use App\Http\Controllers\AiVoiceController;
use App\Http\Controllers\ApPaymentController;
use App\Http\Controllers\ArReceiptController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\CompanySubscriptionController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ExchangeRevaluationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\DocumentExportController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\MrpController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NonConformanceReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\QualityInspectionController;
use App\Http\Controllers\ReadinessController;
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

// Public, detail-free readiness endpoint for a load balancer or process monitor.
// Laravel's /up endpoint checks that the application boots; /ready also verifies
// the database is accepting connections without exposing exception details.
Route::get('/ready', ReadinessController::class)->name('health.ready');

// Server-to-server payment notifications (CSRF-exempt in bootstrap/app.php).
Route::post('/billing/webhook/{provider}', BillingWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('billing.webhook');

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

    Route::middleware(['permission:ai.use', 'feature:ai'])->group(function () {
        Route::get('/ai/copilot', [AiCopilotController::class, 'index'])->name('ai.copilot.index');
        Route::post('/ai/copilot/chat', [AiCopilotController::class, 'chat'])->name('ai.copilot.chat');
        Route::post('/ai/voice/transcribe', [AiVoiceController::class, 'transcribe'])->name('ai.voice.transcribe');
    });
    Route::middleware(['permission:ai.documents.view,ai.documents.manage', 'feature:ai'])->group(function () {
        Route::get('/ai/documents', [AiDocumentController::class, 'index'])->name('ai.documents.index');
    });
    Route::middleware(['permission:ai.documents.view,ai.documents.manage', 'feature:ai'])->group(function () {
        Route::get('/ai/documents/{document}/file', [AiDocumentController::class, 'file'])->name('ai.documents.file');
        Route::get('/ai/documents/{document}', [AiDocumentController::class, 'show'])->name('ai.documents.show');
    });
    Route::middleware(['permission:ai.documents.manage', 'feature:ai'])->group(function () {
        Route::post('/ai/documents', [AiDocumentController::class, 'store'])->name('ai.documents.store');
        Route::post('/ai/documents/{document}/process', [AiDocumentController::class, 'process'])->name('ai.documents.process');
        Route::post('/ai/documents/{document}/accept', [AiDocumentController::class, 'accept'])->name('ai.documents.accept');
        Route::post('/ai/documents/{document}/reject', [AiDocumentController::class, 'reject'])->name('ai.documents.reject');
    });
    Route::post('/ai/copilot/actions/{aiActionRun}/confirm', [AiCopilotController::class, 'confirm'])
        ->middleware('permission:ai.execute')
        ->name('ai.copilot.confirm');
    Route::post('/ai/copilot/actions/{aiActionRun}/reject', [AiCopilotController::class, 'reject'])
        ->middleware('permission:ai.execute')
        ->name('ai.copilot.reject');

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

    Route::get('/company/subscription', [CompanySubscriptionController::class, 'show'])
        ->name('company.subscription')
        ->middleware('permission:companies.view,companies.manage');
    Route::post('/company/subscription/suspend', [CompanySubscriptionController::class, 'suspend'])
        ->name('company.subscription.suspend')
        ->middleware('permission:companies.manage');
    Route::post('/company/subscription/activate', [CompanySubscriptionController::class, 'activate'])
        ->name('company.subscription.activate')
        ->middleware('permission:companies.manage');

    Route::get('/trial-expired', [TrialExpiredController::class, 'show'])->name('trial-expired');

    Route::prefix('onboarding')->name('onboarding.')->controller(OnboardingController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/profile', 'saveProfile')->name('profile');
        Route::post('/generate', 'generate')->name('generate');
        Route::get('/coa-template', 'template')->name('template');
        Route::post('/coa-upload', 'upload')->name('upload');
        Route::put('/draft', 'updateDraft')->name('draft.update');
        Route::post('/draft/review', 'backToReview')->name('draft.review');
        Route::post('/complete', 'complete')->name('complete');
    });

    Route::prefix('billing')->name('billing.')->middleware('permission:companies.manage')->controller(BillingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/orders', 'store')->name('orders.store');
        Route::get('/orders/{order}', 'show')->name('orders.show');
        Route::get('/checkout/{order}', 'checkout')->name('checkout');
        Route::post('/checkout/{order}/simulate', 'simulate')->name('checkout.simulate');
    });

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

    Route::middleware(['permission:currencies.view,currencies.manage', 'feature:multicurrency'])->group(function () {
        Route::get('master/currencies', [CurrencyController::class, 'index'])->name('master.currencies.index');
    });
    Route::middleware(['permission:currencies.manage', 'feature:multicurrency'])->group(function () {
        Route::post('master/currencies/enable', [CurrencyController::class, 'enable'])->name('master.currencies.enable');
        Route::delete('master/currencies/{currency}/disable', [CurrencyController::class, 'disable'])->name('master.currencies.disable');
        Route::post('master/currency-rates', [CurrencyController::class, 'storeRate'])->name('master.currency-rates.store');
        Route::post('master/currency-revaluations', [ExchangeRevaluationController::class, 'store'])->name('master.currency-revaluations.store');
        Route::delete('master/currency-revaluations/{run}', [ExchangeRevaluationController::class, 'destroy'])->name('master.currency-revaluations.destroy');
    });
    Route::middleware('permission:accounts.create,accounts.manage')->post('master/accounts', [AccountController::class, 'store'])->name('master.accounts.store');
    Route::middleware('permission:accounts.edit,accounts.manage')->put('master/accounts/{account}', [AccountController::class, 'update'])->name('master.accounts.update');
    Route::middleware('permission:accounts.manage')->put('master/account-roles', [AccountController::class, 'updateRoles'])->name('master.account-roles.update');
    Route::middleware('permission:accounts.delete,accounts.manage')->delete('master/accounts/{account}', [AccountController::class, 'destroy'])->name('master.accounts.destroy');

    Route::middleware('permission:master-data.view,master-data.manage')->group(function () {
        Route::get('master/unit-of-measures', [UnitOfMeasureController::class, 'index'])->name('master.uoms.index');
        Route::get('master/warehouses', [WarehouseController::class, 'index'])->name('master.warehouses.index');
        Route::get('master/tax-codes', [TaxCodeController::class, 'index'])->name('master.tax-codes.index');
        Route::get('master/suppliers', [SupplierController::class, 'index'])->name('master.suppliers.index');
        Route::get('master/customers', [CustomerController::class, 'index'])->name('master.customers.index');
        Route::get('master/products', [ProductController::class, 'index'])->name('master.products.index');
        Route::get('master/products/{product}', [ProductController::class, 'show'])->name('master.products.show');
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
Route::post('manufacturing/boms/{bom}/new-version', [BomController::class, 'newVersion'])->name('manufacturing.boms.new-version');
Route::delete('manufacturing/boms/{bom}', [BomController::class, 'destroy'])->name('manufacturing.boms.destroy');

        Route::post('manufacturing/routings', [RoutingController::class, 'store'])->name('manufacturing.routings.store');
        Route::put('manufacturing/routings/{routing}', [RoutingController::class, 'update'])->name('manufacturing.routings.update');
        Route::delete('manufacturing/routings/{routing}', [RoutingController::class, 'destroy'])->name('manufacturing.routings.destroy');

        Route::post('manufacturing/production-orders', [ProductionOrderController::class, 'store'])->name('manufacturing.production-orders.store');
        Route::post('manufacturing/production-orders/{productionOrder}/release', [ProductionOrderController::class, 'release'])->name('manufacturing.production-orders.release');
        Route::post('manufacturing/production-orders/{productionOrder}/issue-materials', [ProductionOrderController::class, 'issueMaterials'])->name('manufacturing.production-orders.issue-materials');
Route::post('manufacturing/production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('manufacturing.production-orders.complete');
        Route::post('manufacturing/production-orders/{productionOrder}/complete-without-qc', [ProductionOrderController::class, 'complete'])->name('manufacturing.production-orders.complete-without-qc');
Route::post('manufacturing/production-orders/{productionOrder}/cost', [ProductionOrderController::class, 'cost'])->name('manufacturing.production-orders.cost');
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
        Route::post('manufacturing/operations/{operation}/in-process-inspection', [QualityInspectionController::class, 'storeInProcess'])->name('quality.inspections.store-in-process');
        Route::post('quality/inspections/{inspection}/release', [QualityInspectionController::class, 'release'])->name('quality.inspections.release');
        Route::post('quality/ncrs/{ncr}/disposition', [NonConformanceReportController::class, 'disposition'])->name('quality.ncrs.disposition');
        Route::post('quality/ncrs/{ncr}/corrective-action', [NonConformanceReportController::class, 'correctiveAction'])->name('quality.ncrs.corrective-action');
        Route::post('quality/ncrs/{ncr}/close', [NonConformanceReportController::class, 'close'])->name('quality.ncrs.close');
        Route::post('quality/ncrs/{ncr}/create-rework', [NonConformanceReportController::class, 'createRework'])->name('quality.ncrs.create-rework');
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
        Route::post('purchasing/quality-inspections/{inspection}/release', [GoodsReceiptController::class, 'releaseInspection'])->name('purchasing.goods-receipts.release-inspection');

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
        Route::post('sales/delivery-orders/{deliveryOrder}/cancel', [DeliveryOrderController::class, 'cancel'])->name('sales.delivery-orders.cancel');

        Route::post('sales/sales-orders/{salesOrder}/sales-invoices', [SalesInvoiceController::class, 'store'])->name('sales.sales-invoices.store');

        Route::post('sales/sales-invoices/{salesInvoice}/sales-returns', [SalesReturnController::class, 'store'])->name('sales.sales-returns.store');
    });

    Route::middleware('permission:cash-bank.view,cash-bank.manage')->group(function () {
        Route::get('cash-bank/bank-accounts', [BankAccountController::class, 'index'])->name('cash-bank.bank-accounts.index');
        Route::get('cash-bank/cash-transactions', [CashTransactionController::class, 'index'])->name('cash-bank.cash-transactions.index');
        Route::get('cash-bank/bank-reconciliations', [BankReconciliationController::class, 'index'])->name('cash-bank.bank-reconciliations.index');
        Route::get('cash-bank/bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show'])->name('cash-bank.bank-reconciliations.show');
    });
    Route::middleware('permission:cash-bank.manage')->group(function () {
        Route::post('cash-bank/bank-accounts', [BankAccountController::class, 'store'])->name('cash-bank.bank-accounts.store');
        Route::put('cash-bank/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('cash-bank.bank-accounts.update');

        Route::post('cash-bank/cash-transactions', [CashTransactionController::class, 'store'])->name('cash-bank.cash-transactions.store');

        Route::post('cash-bank/bank-reconciliations', [BankReconciliationController::class, 'store'])->name('cash-bank.bank-reconciliations.store');
        Route::put('cash-bank/bank-reconciliations/{bankReconciliation}/lines', [BankReconciliationController::class, 'updateLines'])->name('cash-bank.bank-reconciliations.update-lines');
        Route::post('cash-bank/bank-reconciliations/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('cash-bank.bank-reconciliations.complete');
    });

    Route::middleware('permission:ar.view,ar.manage')->group(function () {
        Route::get('ar/ar-receipts', [ArReceiptController::class, 'index'])->name('ar.ar-receipts.index');
    });
    Route::middleware('permission:ar.manage')->group(function () {
        Route::post('ar/ar-receipts', [ArReceiptController::class, 'store'])->name('ar.ar-receipts.store');
    });

    Route::middleware('permission:ap.view,ap.manage')->group(function () {
        Route::get('ap/ap-payments', [ApPaymentController::class, 'index'])->name('ap.ap-payments.index');
    });
    Route::middleware('permission:ap.manage')->group(function () {
        Route::post('ap/ap-payments', [ApPaymentController::class, 'store'])->name('ap.ap-payments.store');
    });

    Route::middleware('permission:fixed-assets.view,fixed-assets.manage')->group(function () {
        Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    });
    Route::middleware('permission:fixed-assets.manage')->group(function () {
        Route::post('fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
        Route::post('fixed-assets/{fixedAsset}/activate', [FixedAssetController::class, 'activate'])->name('fixed-assets.activate');
        Route::post('fixed-assets/{fixedAsset}/depreciate', [FixedAssetController::class, 'depreciate'])->name('fixed-assets.depreciate');
        Route::post('fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');
    });

    Route::middleware('permission:maintenance.view,maintenance.manage')->group(function () {
        Route::get('maintenance/equipment', [MaintenanceController::class, 'equipment'])->name('maintenance.equipment.index');
        Route::get('maintenance/work-orders', [MaintenanceController::class, 'workOrders'])->name('maintenance.work-orders.index');
        Route::get('maintenance/schedules', [MaintenanceController::class, 'schedules'])->name('maintenance.schedules.index');
        Route::get('maintenance/readings', [MaintenanceController::class, 'readings'])->name('maintenance.readings.index');
    });
    Route::middleware('permission:maintenance.manage')->group(function () {
        Route::post('maintenance/equipment', [MaintenanceController::class, 'storeEquipment'])->name('maintenance.equipment.store');
        Route::post('maintenance/work-orders', [MaintenanceController::class, 'storeWorkOrder'])->name('maintenance.work-orders.store');
        Route::post('maintenance/schedules', [MaintenanceController::class, 'storeSchedule'])->name('maintenance.schedules.store');
        Route::post('maintenance/readings', [MaintenanceController::class, 'storeReading'])->name('maintenance.readings.store');
        Route::post('maintenance/equipment/{equipment}/predict', [MaintenanceController::class, 'predict'])->name('maintenance.equipment.predict');
    });
    Route::middleware('permission:maintenance.execute')->group(function () {
        Route::post('maintenance/work-orders/{workOrder}/open', [MaintenanceController::class, 'open'])->name('maintenance.work-orders.open');
        Route::post('maintenance/work-orders/{workOrder}/start', [MaintenanceController::class, 'start'])->name('maintenance.work-orders.start');
        Route::post('maintenance/work-orders/{workOrder}/complete', [MaintenanceController::class, 'complete'])->name('maintenance.work-orders.complete');
        Route::post('maintenance/work-orders/{workOrder}/cancel', [MaintenanceController::class, 'cancel'])->name('maintenance.work-orders.cancel');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports/gl', [FinancialReportController::class, 'index'])->name('reports.gl.index');
    });
    Route::middleware('permission:reports.export')->group(function () {
        Route::get('reports/{report}/pdf', [ReportExportController::class, 'pdf'])
            ->whereIn('report', ['trial_balance', 'general_ledger', 'profit_loss', 'balance_sheet', 'cash_flow', 'equity', 'notes', 'financial_statements'])
            ->name('reports.export.pdf');
        Route::get('reports/{report}/xlsx', [ReportExportController::class, 'xlsx'])
            ->whereIn('report', ['trial_balance', 'general_ledger', 'profit_loss', 'balance_sheet', 'cash_flow', 'equity', 'notes', 'financial_statements'])
            ->name('reports.export.xlsx');
    });
    Route::middleware('permission:documents.print')->group(function () {
        Route::get('documents/{type}/{document}/print', DocumentPrintController::class)
            ->whereIn('type', ['purchase_request', 'purchase_order', 'goods_receipt', 'supplier_invoice', 'sales_order', 'delivery_order', 'sales_invoice', 'sales_return', 'ar_receipt', 'ap_payment', 'cash_transaction', 'bank_reconciliation', 'production_order', 'bom', 'routing', 'qc_inspection', 'ncr', 'fixed_asset', 'maintenance_work_order'])
            ->whereNumber('document')
            ->name('documents.print');
        Route::get('documents/{type}/{document}/xlsx', [DocumentExportController::class, 'xlsx'])
            ->whereIn('type', ['purchase_request', 'purchase_order', 'goods_receipt', 'supplier_invoice', 'sales_order', 'delivery_order', 'sales_invoice', 'sales_return', 'ar_receipt', 'ap_payment', 'cash_transaction', 'bank_reconciliation', 'production_order', 'bom', 'routing', 'qc_inspection', 'ncr', 'fixed_asset', 'maintenance_work_order'])
            ->whereNumber('document')
            ->name('documents.export.xlsx');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('notifications/clear-all', [NotificationController::class, 'destroyAll'])->name('notifications.clear-all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

require __DIR__.'/settings.php';
