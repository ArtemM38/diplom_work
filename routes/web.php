<?php

use App\Http\Controllers\AthleteController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\PortfolioController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\ProfileController; // Обязательно добавь этот импорт
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Athlete;

// 1. Главная страница (Логин)
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Auth/Login', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

// 2. Маршруты первичной настройки (БЕЗ middleware profile.completed)
Route::middleware(['auth', 'verified'])->group(function () {
    // Анкета спортсмена
    Route::get('/athlete/setup', [AthleteController::class, 'create'])->name('athlete.create');
    Route::post('/athlete/setup', [AthleteController::class, 'store'])->name('athlete.store');

    // Анкета родителя
    Route::get('/guardian/setup', [AthleteController::class, 'createGuardian'])->name('guardian.create');
    Route::post('/guardian/setup', [AthleteController::class, 'storeGuardian'])->name('guardian.store');
});

// 3. Основная рабочая область CRM (С middleware profile.completed)
Route::middleware(['auth', 'verified', 'profile.completed'])->group(function () {

    // Дашборд
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $athlete = $user->athlete()
            ->with(['rankHistories.rank', 'refereeHistories.refereeCategory', 'documents', 'inventory'])
            ->first();

        return Inertia::render('Dashboard', [
            'athlete' => $athlete,
            'userRole' => $user->role
        ]);
    })->name('dashboard');

    // Маршруты ПРОФИЛЯ (Которых не хватало для выпадающего меню)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // АДМИН-ПАНЕЛЬ
    Route::middleware(['can:access-admin-panel'])->group(function () {
        Route::get('/admin/athletes', [AdminDashboardController::class, 'index'])->name('admin.athletes');

        Route::get('/admin/groups', [GroupController::class, 'index'])->name('admin.groups');
        Route::post('/admin/groups', [GroupController::class, 'store'])->name('admin.groups.store');
        Route::get('/admin/groups/{group}', [GroupController::class, 'show'])->name('admin.groups.show');
        Route::post('/admin/groups/{group}/attach', [GroupController::class, 'attachAthlete'])->name('admin.groups.attach');
        Route::delete('/admin/groups/{group}/detach/{athlete}', [GroupController::class, 'detachAthlete'])->name('admin.groups.detach');

        Route::get('/admin/schedule', [ScheduleController::class, 'index'])->name('admin.schedule');
        Route::post('/admin/schedule', [ScheduleController::class, 'store'])->name('admin.schedule.store');
        Route::delete('/admin/schedule/{schedule}', [ScheduleController::class, 'destroy'])->name('admin.schedule.destroy');

        Route::get('/admin/attendance/{schedule}', [AttendanceController::class, 'show'])->name('admin.attendance.show');
        Route::post('/admin/attendance/{schedule}', [AttendanceController::class, 'store'])->name('admin.attendance.store');

        Route::get('/admin/portfolio', [PortfolioController::class, 'index'])->name('admin.portfolio');
        Route::post('/admin/portfolio/hosts', [PortfolioController::class, 'storeHost'])->name('admin.portfolio.hosts.store');
        Route::patch('/admin/portfolio/hosts/{host}', [PortfolioController::class, 'updateHost'])->name('admin.portfolio.hosts.update');
        Route::delete('/admin/portfolio/hosts/{host}', [PortfolioController::class, 'destroyHost'])->name('admin.portfolio.hosts.destroy');
        Route::post('/admin/portfolio/achievements', [PortfolioController::class, 'storeAchievement'])->name('admin.portfolio.achievements.store');
        Route::patch('/admin/portfolio/achievements/{achievement}', [PortfolioController::class, 'updateAchievement'])->name('admin.portfolio.achievements.update');
        Route::delete('/admin/portfolio/achievements/{achievement}', [PortfolioController::class, 'destroyAchievement'])->name('admin.portfolio.achievements.destroy');
        Route::get('/admin/portfolio/export/summary', [PortfolioController::class, 'exportSummaryCsv'])->name('admin.portfolio.export.summary');
        Route::get('/admin/portfolio/export/athlete', [PortfolioController::class, 'exportAthleteCsv'])->name('admin.portfolio.export.athlete');
        Route::get('/admin/portfolio/export/summary-pdf', [PortfolioController::class, 'exportSummaryPdf'])->name('admin.portfolio.export.summary.pdf');
        Route::get('/admin/portfolio/export/athlete-pdf', [PortfolioController::class, 'exportAthletePdf'])->name('admin.portfolio.export.athlete.pdf');
    });
});

require __DIR__ . '/auth.php';
