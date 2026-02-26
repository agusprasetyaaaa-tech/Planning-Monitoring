<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PlanningReportController;
use App\Http\Controllers\TimeSettingController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\OnlineUserController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\Settings\EmailSettingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;

Route::get('/', function () {
    return Inertia::render('Auth/NexusLogin', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
})->name('logout');

Route::get('/portal-login', function () {
    if (Auth::check()) {
        return back();
    }
    return Inertia::render('Auth/NexusLogin');
})->name('portal-login');

Route::middleware(['auth'])->group(function () {
    Route::post('/logout-all', function () {
        Auth::logout();
        return back();
    })->name('logout-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- SUPER ADMIN ONLY MASTER DATA ---
    Route::group([
        'middleware' => function ($request, $next) {
            if (!$request->user() || !$request->user()->hasRole('Super Admin')) {
                abort(404);
            }
            return $next($request);
        }
    ], function () {
        Route::delete('users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
        Route::resource('users', UserController::class);

        Route::delete('roles/bulk-destroy', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::resource('roles', RoleController::class);

        Route::delete('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::resource('products', ProductController::class);

        Route::get('customers/template', [CustomerController::class, 'template'])->name('customers.template');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::delete('customers/bulk-destroy', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-destroy');
        Route::resource('customers', CustomerController::class);

        Route::delete('teams/bulk-destroy', [TeamController::class, 'bulkDestroy'])->name('teams.bulk-destroy');
        Route::resource('teams', TeamController::class);

        // Team members management
        Route::get('/teams/{team}/members', [TeamController::class, 'members'])->name('teams.members');
        Route::post('/teams/{team}/members', [TeamController::class, 'assignMember'])->name('teams.assign-member');
        Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.remove-member');

        Route::get('security', [SecurityController::class, 'index'])->name('security.index');
        Route::patch('security', [SecurityController::class, 'update'])->name('security.update');
        Route::get('security/online', [OnlineUserController::class, 'index'])->name('security.online');
        Route::delete('security/online/clear', [OnlineUserController::class, 'clearHistory'])->name('security.online.clear');

        // Settings
        Route::get('settings/backup', [DatabaseBackupController::class, 'index'])->name('settings.backup.index');
        Route::get('settings/backup/download', [DatabaseBackupController::class, 'download'])->name('settings.backup.download');
        Route::get('settings/email', [EmailSettingController::class, 'index'])->name('settings.email.index');
        Route::post('settings/email', [EmailSettingController::class, 'update'])->name('settings.email.update');
    });

    // Planning
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::get('/planning/create', [PlanningController::class, 'create'])->name('planning.create');
    Route::post('/planning', [PlanningController::class, 'store'])->name('planning.store');
    Route::patch('/planning/{plan}/control', [PlanningController::class, 'updateControl'])->name('planning.update-control');
    Route::patch('/planning/{plan}/monitoring', [PlanningController::class, 'updateMonitoring'])->name('planning.update-monitoring');
    Route::get('/planning/{plan}/status-info', [PlanningController::class, 'getStatusChangeInfo'])->name('planning.status-info');
    Route::get('/planning/{plan}/report/create', [PlanningController::class, 'createReport'])->name('planning.report.create');
    Route::post('/planning/{plan}/report', [PlanningController::class, 'storeReport'])->name('planning.report.store');
    Route::patch('/planning/{plan}/fail', [PlanningController::class, 'markAsFailed'])->name('planning.fail');
    Route::patch('/planning/{plan}/revise', [PlanningController::class, 'revise'])->name('planning.revise');

    // Reset status logs (Super Admin only)
    Route::delete('/planning/{plan}/reset-status-logs', [PlanningController::class, 'resetStatusLogs'])->name('planning.reset-status-logs');
    Route::delete('/planning/reset-all-status-logs', [PlanningController::class, 'resetAllStatusLogs'])->name('planning.reset-all-status-logs');

    // Planning Report
    Route::get('/planning-report', [PlanningReportController::class, 'index'])->name('planning-report.index');
    Route::get('/planning-report/export-excel', [PlanningReportController::class, 'exportExcel'])->name('planning-report.export-excel');
    Route::get('/planning-report/export-pdf', [PlanningReportController::class, 'exportPdf'])->name('planning-report.export-pdf');
    Route::delete('/planning-report/bulk-destroy', [PlanningReportController::class, 'bulkDestroy'])->name('planning-report.bulk-destroy');

    // Daily Report
    Route::get('/daily-report', [DailyReportController::class, 'index'])->name('daily-report.index');
    Route::get('/daily-report/export-excel', [DailyReportController::class, 'exportExcel'])->name('daily-report.export-excel');
    Route::get('/daily-report/export-pdf', [DailyReportController::class, 'exportPdf'])->name('daily-report.export-pdf');
    Route::delete('/daily-report/bulk-destroy', [DailyReportController::class, 'bulkDestroy'])->name('daily-report.bulk-destroy');
    Route::post('/daily-report', [DailyReportController::class, 'store'])->name('daily-report.store');
    Route::put('/daily-report/{daily_report}', [DailyReportController::class, 'update'])->name('daily-report.update');
    Route::delete('/daily-report/{daily_report}', [DailyReportController::class, 'destroy'])->name('daily-report.destroy');

    // Time Settings
    Route::get('/time-settings', [TimeSettingController::class, 'index'])->name('time-settings.index');
    Route::patch('/time-settings', [TimeSettingController::class, 'update'])->name('time-settings.update');

    // Notifications
    Route::post('/notifications/{id}/read', function ($id) {
        $notification = Auth::user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return back();
    })->name('notifications.read');

    Route::post('/notifications/read-all', function () {
        Auth::user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.read-all');
});

Route::get('/api/teams', function () {
    if (Auth::check()) {
        return response()->json(Team::all());
    }
    return response()->json(['message' => 'Unauthorized'], 401);
});

require __DIR__ . '/auth.php';
