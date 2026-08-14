<?php

use App\Http\Controllers\ClientPortal\AiController;
use App\Http\Controllers\ClientPortal\AuthController;
use App\Http\Controllers\ClientPortal\CommentController;
use App\Http\Controllers\ClientPortal\DashboardController;
use App\Http\Controllers\ClientPortal\MaintenanceReportController;
use App\Http\Controllers\ClientPortal\TicketController;
use App\Http\Controllers\ClientPortal\TicketSubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Portal Routes
|--------------------------------------------------------------------------
|
| Self-service portal where a client logs in (via the "client" guard) to
| track the progress of their own tickets and reply to them. Separate from
| the staff app which runs on the "web" guard.
|
*/

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:client')->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets');
        Route::get('kanban', [TicketController::class, 'board'])->name('kanban');
        // Client-side ticket intake. Declared before the {ticket} routes so the
        // literal paths win over the parameterised ones.
        Route::post('tickets', [TicketSubmissionController::class, 'store'])
            ->name('tickets.store')->middleware('throttle:5,1');
        Route::post('tickets/check-duplicate', [TicketSubmissionController::class, 'checkDuplicate'])
            ->name('tickets.check-duplicate')->middleware('throttle:5,1');

        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/detail', [TicketController::class, 'detail'])->name('tickets.detail');
        Route::get('tickets/{ticket}/attachment', [TicketController::class, 'attachment'])->name('tickets.attachment');
        Route::delete('tickets/{ticket}/attachments', [TicketController::class, 'deleteAttachment'])->name('tickets.attachments.destroy');
        Route::post('tickets/{ticket}/comments', [CommentController::class, 'store'])->name('tickets.comments.store');
        Route::post('tickets/comments/{comment}/react', [CommentController::class, 'toggleReaction'])->name('tickets.comments.react');

        // Laporan maintenance yang sudah diterbitkan; unduhan XLSX mandiri.
        Route::get('maintenance-reports', [MaintenanceReportController::class, 'index'])->name('maintenance-reports');
        Route::get('maintenance-reports/{maintenanceReport}/export', [MaintenanceReportController::class, 'exportXlsx'])->name('maintenance-reports.export');

        // Burst guard on top of the per-day quota enforced inside the controller.
        Route::post('ai/chat', [AiController::class, 'chat'])->name('ai.chat')->middleware('throttle:10,1');
    });
});
