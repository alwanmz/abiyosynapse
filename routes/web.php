<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\GuidebookCategoryController;
use App\Http\Controllers\GuidebookController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\MinuteAttachmentController;
use App\Http\Controllers\MinuteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskTypeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AnalyticsController::class, 'index'])->name('dashboard');
    Route::get('api/dashboard-stats', [AnalyticsController::class, 'dashboardStats'])->name('dashboard.stats');
    Route::get('analytics/burndown', [AnalyticsController::class, 'burndownData'])->name('analytics.burndown');
    Route::get('analytics', [AnalyticsController::class, 'analyticsIndex'])->name('analytics')->middleware('permission:analytics.view');
    Route::post('analytics/ai-analysis', [AnalyticsController::class, 'generateAIAnalysis'])->name('analytics.ai-analysis');
    Route::post('analytics/weekly-digest', [AnalyticsController::class, 'weeklyDigest'])->name('analytics.weekly-digest');
    
    Route::get('daily-logs', [DailyLogController::class, 'index'])->name('daily-logs')->middleware('permission:daily-logs.view');
    Route::post('daily-logs', [DailyLogController::class, 'store'])->name('daily-logs.store')->middleware('permission:daily-logs.create');
    Route::post('daily-logs/{dailyLog}/react', [DailyLogController::class, 'toggleReaction'])->name('daily-logs.react')->middleware('permission:daily-logs.view');
    Route::delete('daily-logs/{dailyLog}', [DailyLogController::class, 'destroy'])->name('daily-logs.destroy')->middleware('permission:daily-logs.delete,daily-logs.manage');

    Route::get('minutes', [MinuteController::class, 'index'])->name('minutes.index')->middleware('permission:minutes.view');
    Route::get('minutes/create', [MinuteController::class, 'create'])->name('minutes.create')->middleware('permission:minutes.create');
    Route::post('minutes', [MinuteController::class, 'store'])->name('minutes.store')->middleware('permission:minutes.create');
    Route::get('minutes/{minute}', [MinuteController::class, 'show'])->name('minutes.show')->middleware('permission:minutes.view');
    Route::get('minutes/{minute}/edit', [MinuteController::class, 'edit'])->name('minutes.edit')->middleware('permission:minutes.edit,minutes.update');
    Route::put('minutes/{minute}', [MinuteController::class, 'update'])->name('minutes.update')->middleware('permission:minutes.edit,minutes.update');
    Route::delete('minutes/{minute}', [MinuteController::class, 'destroy'])->name('minutes.destroy')->middleware('permission:minutes.delete');
    Route::delete('minute-attachments/{minuteAttachment}', [MinuteAttachmentController::class, 'destroy'])->name('minute-attachments.destroy');

    Route::get('guidebooks', [GuidebookController::class, 'index'])->name('guidebooks.index')->middleware('permission:guidebooks.view');
    Route::post('guidebooks', [GuidebookController::class, 'store'])->name('guidebooks.store')->middleware('permission:guidebooks.manage');
    // Rute literal didaftarkan sebelum {guidebook} agar tidak tertangkap binding.
    Route::post('guidebook-categories', [GuidebookCategoryController::class, 'store'])->name('guidebook-categories.store')->middleware('permission:guidebooks.manage');
    Route::put('guidebook-categories/{guidebookCategory}', [GuidebookCategoryController::class, 'update'])->name('guidebook-categories.update')->middleware('permission:guidebooks.manage');
    Route::delete('guidebook-categories/{guidebookCategory}', [GuidebookCategoryController::class, 'destroy'])->name('guidebook-categories.destroy')->middleware('permission:guidebooks.manage');
    Route::get('guidebooks/{guidebook}', [GuidebookController::class, 'show'])->name('guidebooks.show')->middleware('permission:guidebooks.view');
    Route::get('guidebooks/{guidebook}/pdf', [GuidebookController::class, 'pdf'])->name('guidebooks.pdf')->middleware('permission:guidebooks.view');
    Route::put('guidebooks/{guidebook}', [GuidebookController::class, 'update'])->name('guidebooks.update')->middleware('permission:guidebooks.manage');
    Route::post('guidebooks/{guidebook}/pin', [GuidebookController::class, 'togglePin'])->name('guidebooks.pin')->middleware('permission:guidebooks.manage');
    Route::delete('guidebooks/{guidebook}', [GuidebookController::class, 'destroy'])->name('guidebooks.destroy')->middleware('permission:guidebooks.manage');

    Route::get('maintenance-reports', [MaintenanceReportController::class, 'index'])->name('maintenance-reports.index')->middleware('permission:maintenance-reports.view');
    Route::get('maintenance-reports/create', [MaintenanceReportController::class, 'create'])->name('maintenance-reports.create')->middleware('permission:maintenance-reports.manage');
    Route::post('maintenance-reports', [MaintenanceReportController::class, 'store'])->name('maintenance-reports.store')->middleware('permission:maintenance-reports.manage');
    Route::get('maintenance-reports/{maintenanceReport}/edit', [MaintenanceReportController::class, 'edit'])->name('maintenance-reports.edit')->middleware('permission:maintenance-reports.manage');
    Route::put('maintenance-reports/{maintenanceReport}', [MaintenanceReportController::class, 'update'])->name('maintenance-reports.update')->middleware('permission:maintenance-reports.manage');
    Route::post('maintenance-reports/{maintenanceReport}/publish', [MaintenanceReportController::class, 'publish'])->name('maintenance-reports.publish')->middleware('permission:maintenance-reports.manage');
    Route::post('maintenance-reports/{maintenanceReport}/unpublish', [MaintenanceReportController::class, 'unpublish'])->name('maintenance-reports.unpublish')->middleware('permission:maintenance-reports.manage');
    Route::get('maintenance-reports/{maintenanceReport}/export/docx', [MaintenanceReportController::class, 'exportDocx'])->name('maintenance-reports.export.docx')->middleware('permission:maintenance-reports.view');
    Route::delete('maintenance-reports/{maintenanceReport}', [MaintenanceReportController::class, 'destroy'])->name('maintenance-reports.destroy')->middleware('permission:maintenance-reports.manage');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');
    Route::get('reports/export/projects', [ReportController::class, 'exportProjects'])->name('reports.export.projects');
    Route::get('reports/export/tickets', [ReportController::class, 'exportTickets'])->name('reports.export.tickets');
    Route::get('reports/export/daily-logs', [ReportController::class, 'exportDailyLogs'])->name('reports.export.daily_logs');
    Route::get('reports/export/minutes', [ReportController::class, 'exportMinutes'])->name('reports.export.minutes');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects')->middleware('permission:projects.view');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store')->middleware('permission:projects.create');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update')->middleware('permission:projects.edit,projects.update-any');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy')->middleware('permission:projects.delete');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets')->middleware('permission:tickets.view');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store')->middleware('permission:tickets.create');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update')->middleware('permission:tickets.edit,tickets.update,tickets.update-any');
    Route::patch('tickets/{ticket}/move', [TicketController::class, 'move'])->name('tickets.move')->middleware('permission:tickets.edit,tickets.update,tickets.update-any');
    Route::post('tickets/{ticket}/archive', [TicketController::class, 'archive'])->name('tickets.archive')->middleware('permission:tickets.edit,tickets.update,tickets.update-any');
    Route::post('tickets/{ticket}/unarchive', [TicketController::class, 'unarchive'])->name('tickets.unarchive')->middleware('permission:tickets.edit,tickets.update,tickets.update-any');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy')->middleware('permission:tickets.delete');

    Route::post('tickets/{ticket}/approve', [TicketController::class, 'approve'])->name('tickets.approve')->middleware('permission:tickets.approve');
    Route::post('tickets/{ticket}/reject', [TicketController::class, 'reject'])->name('tickets.reject')->middleware('permission:tickets.approve');
    Route::post('tickets/{ticket}/delegate', [TicketController::class, 'delegate'])->name('tickets.delegate')->middleware('permission:tickets.approve');

    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store')->middleware('permission:tickets.view');
    Route::put('tickets/comments/{comment}', [TicketCommentController::class, 'update'])->name('tickets.comments.update')->middleware('permission:tickets.view');
    Route::delete('tickets/comments/{comment}', [TicketCommentController::class, 'destroy'])->name('tickets.comments.destroy')->middleware('permission:tickets.view');
    Route::post('tickets/comments/{comment}/react', [TicketCommentController::class, 'toggleReaction'])->name('tickets.comments.react')->middleware('permission:tickets.view');

    Route::get('timelines', [TimelineController::class, 'index'])->name('timelines')->middleware('permission:timelines.view');
    Route::post('timelines', [TimelineController::class, 'store'])->name('timelines.store')->middleware('permission:timelines.create');
    Route::put('timelines/{timeline}', [TimelineController::class, 'update'])->name('timelines.update')->middleware('permission:timelines.edit,timelines.update');
    Route::delete('timelines/{timeline}', [TimelineController::class, 'destroy'])->name('timelines.destroy')->middleware('permission:timelines.delete');

    Route::get('/teams', [TeamController::class, 'index'])->name('teams')->middleware('permission:teams.view');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store')->middleware('permission:teams.create');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update')->middleware('permission:teams.edit,teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy')->middleware('permission:teams.delete');
    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.add-member')->middleware('permission:teams.manage-members');
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.remove-member')->middleware('permission:teams.manage-members');

    Route::get('master/company', [CompanySettingController::class, 'index'])->name('master.company')->middleware('permission:companies.view');
    Route::post('master/company', [CompanySettingController::class, 'update'])->name('master.company.update')->middleware('permission:companies.edit,companies.manage');

    Route::get('master/clients', [ClientController::class, 'index'])->name('master.clients')->middleware('permission:clients.view');
    Route::post('master/clients', [ClientController::class, 'store'])->name('master.clients.store')->middleware('permission:clients.create,clients.manage');
    Route::put('master/clients/{client}', [ClientController::class, 'update'])->name('master.clients.update')->middleware('permission:clients.edit,clients.manage');
    Route::delete('master/clients/{client}', [ClientController::class, 'destroy'])->name('master.clients.destroy')->middleware('permission:clients.delete,clients.manage');
    Route::post('master/clients/{client}/telegram-code', [ClientController::class, 'regenerateTelegramCode'])->name('master.clients.telegram-code')->middleware('permission:clients.edit,clients.manage');
    Route::post('master/clients/{client}/portal-password', [ClientController::class, 'regeneratePortalPassword'])->name('master.clients.portal-password')->middleware('permission:clients.edit,clients.manage');
    Route::post('master/clients/{client}/reset-request-quota', [ClientController::class, 'resetRequestQuota'])->name('master.clients.reset-request-quota')->middleware('permission:clients.edit,clients.manage');
    Route::delete('master/clients/telegram-contacts/{telegramContact}', [ClientController::class, 'revokeTelegramContact'])->name('master.clients.telegram-contact.revoke')->middleware('permission:clients.edit,clients.manage');

    Route::get('master/task-types', [TaskTypeController::class, 'index'])->name('master.task-types')->middleware('permission:task-types.view');
    Route::post('master/task-types', [TaskTypeController::class, 'store'])->name('master.task-types.store')->middleware('permission:task-types.create,task-types.manage');
    Route::put('master/task-types/{taskType}', [TaskTypeController::class, 'update'])->name('master.task-types.update')->middleware('permission:task-types.edit,task-types.manage');
    Route::delete('master/task-types/{taskType}', [TaskTypeController::class, 'destroy'])->name('master.task-types.destroy')->middleware('permission:task-types.delete,task-types.manage');

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

    Route::get('attachments/{ticket}/preview', [App\Http\Controllers\AttachmentController::class, 'show'])->name('attachments.preview');
    Route::get('attachments/{ticket}/{filename}', [App\Http\Controllers\AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('tickets/{ticket}/attachments', [TicketController::class, 'deleteAttachment'])->name('tickets.attachments.destroy')->middleware('permission:tickets.view');

    /*
    |--------------------------------------------------------------------------
    | AI endpoints (Groq-backed)
    |--------------------------------------------------------------------------
    | Shared across modules (Daily Logs voice, Analytics insights, etc.).
    | All routes auth-protected; the API key lives only on the server.
    */
    Route::prefix('ai')->name('ai.')->middleware('throttle:20,1')->group(function () {
        Route::post('transcribe', [AiController::class, 'transcribe'])->name('transcribe');
        Route::post('polish', [AiController::class, 'polish'])->name('polish');
        Route::post('summarize', [AiController::class, 'summarize'])->name('summarize');
        Route::post('suggest-category', [AiController::class, 'suggestCategory'])->name('suggest-category');
        Route::post('chat-context', [AiController::class, 'chatContext'])->name('chat-context');
        Route::post('draft-ticket', [AiController::class, 'draftTicket'])->name('draft-ticket');
        Route::post('extract-decisions', [AiController::class, 'extractDecisions'])->name('extract-decisions');
        Route::post('check-similar-ticket', [AiController::class, 'checkSimilarTicket'])->name('check-similar-ticket');
        Route::post('maintenance-report-draft', [AiController::class, 'maintenanceReportDraft'])->name('maintenance-report-draft')->middleware('permission:maintenance-reports.manage');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/client-portal.php';
