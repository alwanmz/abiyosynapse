<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

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

    Route::post('/company/switch', CompanySwitchController::class)->name('company.switch');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:roles.create,roles.manage');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles.edit,roles.manage');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.delete,roles.manage');

    Route::get('manage-users', [UserController::class, 'index'])->name('manage-users')->middleware('permission:users.view');
    Route::post('manage-users', [UserController::class, 'store'])->name('manage-users.store')->middleware('permission:users.create,users.manage');
    Route::put('manage-users/{user}', [UserController::class, 'update'])->name('manage-users.update')->middleware('permission:users.edit,users.manage');
    Route::put('manage-users/{user}/role', [UserController::class, 'updateRole'])->name('manage-users.update-role')->middleware('permission:users.edit,users.manage');
    Route::delete('manage-users/{user}', [UserController::class, 'destroy'])->name('manage-users.destroy')->middleware('permission:users.delete,users.manage');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('notifications/clear-all', [NotificationController::class, 'destroyAll'])->name('notifications.clear-all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

require __DIR__.'/settings.php';
