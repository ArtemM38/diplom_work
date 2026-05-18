<?php

use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AthletePortfolioController;
use App\Http\Controllers\FinanceViewController;
use App\Http\Controllers\AthleteDocumentsController;
use App\Http\Controllers\AddressSuggestionController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\PortfolioController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ProfileController; // Обязательно добавь этот импорт
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Athlete;
use App\Models\Schedule;

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
Route::middleware(['auth', 'verified', 'active.user'])->group(function () {
    // Анкета спортсмена
    Route::get('/athlete/setup', [AthleteController::class, 'create'])->name('athlete.create');
    Route::post('/athlete/setup', [AthleteController::class, 'store'])->name('athlete.store');
    Route::get('/athlete/{athlete}/edit', [AthleteController::class, 'edit'])->name('athlete.edit');
    Route::patch('/athlete/{athlete}', [AthleteController::class, 'update'])->name('athlete.update');

    // Анкета родителя
    Route::get('/guardian/setup', [AthleteController::class, 'createGuardian'])->name('guardian.create');
    Route::post('/guardian/setup', [AthleteController::class, 'storeGuardian'])->name('guardian.store');
    Route::get('/address/suggest', [AddressSuggestionController::class, 'suggest'])->name('address.suggest');
});

// 3. Основная рабочая область CRM (С middleware profile.completed)
Route::middleware(['auth', 'verified', 'active.user', 'profile.completed'])->group(function () {

    // Дашборд
    Route::get('/dashboard', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guardian = null;
        $guardianAthletes = collect();

        if ($user->hasRole('guardian')) {
            $guardian = $user->guardian?->load(['athletes.rankHistories.rank', 'athletes.documents', 'athletes.groups']);
            $guardianAthletes = $guardian?->athletes ?? collect();
        }

        $athlete = $user->athlete()
            ->with(['rankHistories.rank', 'refereeHistories.refereeCategory', 'documents', 'inventory', 'groups', 'guardians'])
            ->first();

        $athleteGuardians = $athlete?->guardians?->map(fn ($g) => [
            'id' => $g->id,
            'full_name' => $g->full_name,
            'phone' => $g->phone,
            'relation' => $g->relation,
        ]) ?? collect();

        $athleteSchedule = collect();
        if ($athlete) {
            $groupIds = $athlete->groups()->pluck('groups.id');
            $athleteSchedule = Schedule::query()
                ->with(['group', 'location', 'coach'])
                ->when(request('from'), fn ($q) => $q->whereDate('lesson_date', '>=', request('from')))
                ->when(request('to'), fn ($q) => $q->whereDate('lesson_date', '<=', request('to')))
                ->when(request('group_id'), fn ($q) => $q->where('group_id', request('group_id')))
                ->whereIn('group_id', $groupIds)
                ->orderBy('lesson_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'lesson_date' => $s->lesson_date,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'group' => $s->group?->name,
                    'location' => $s->location?->name,
                    'coach' => $s->coach?->name,
                ]);
        }

        return Inertia::render('Dashboard', [
            'athlete' => $athlete,
            'guardian' => $guardian,
            'guardianAthletes' => $guardianAthletes,
            'userRole' => $user->role,
            'userRoles' => $user->getRolesList(),
            'athleteSchedule' => $athleteSchedule,
            'scheduleFilters' => request()->only(['from', 'to', 'group_id']),
            'athleteGroups' => $athlete?->groups ?? collect(),
            'athleteGuardians' => $athleteGuardians,
        ]);
    })->name('dashboard');

    // Маршруты ПРОФИЛЯ (Которых не хватало для выпадающего меню)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/guardian', [ProfileController::class, 'updateGuardian'])->name('profile.guardian.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/athlete/portfolio', [AthletePortfolioController::class, 'index'])->name('athlete.portfolio');
    Route::get('/finance', [FinanceViewController::class, 'index'])->name('finance');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/athlete/schedule-calendar', [ScheduleController::class, 'athleteCalendar'])->name('athlete.schedule.calendar');
    Route::get('/athlete/documents/template/{template}/pdf', [AthleteDocumentsController::class, 'downloadPdf'])->name('athlete.documents.pdf');
    Route::get('/athlete/documents/template/{template}/word', [AthleteDocumentsController::class, 'downloadWord'])->name('athlete.documents.word');


    // АДМИН-ПАНЕЛЬ
    Route::middleware(['can:access-admin-panel'])->group(function () {
        Route::get('/admin/athletes', [AdminDashboardController::class, 'index'])->name('admin.athletes');
        Route::get('/admin/athletes/{athlete}', [AdminDashboardController::class, 'show'])->name('admin.athletes.show');
        Route::post('/admin/athletes/{athlete}/guardians', [AdminDashboardController::class, 'storeGuardian'])->name('admin.athletes.guardians.store');
        Route::patch('/admin/athletes/{athlete}/guardians/{guardian}', [AdminDashboardController::class, 'updateGuardian'])->name('admin.athletes.guardians.update');
        Route::get('/admin/users/{user}', [UserManagementController::class, 'show'])->name('admin.users.show');

        Route::get('/admin/groups', [GroupController::class, 'index'])->name('admin.groups');
        Route::post('/admin/groups', [GroupController::class, 'store'])->name('admin.groups.store');
        Route::patch('/admin/groups/{group}', [GroupController::class, 'update'])->name('admin.groups.update');
        Route::delete('/admin/groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups.destroy');
        Route::get('/admin/groups/{group}', [GroupController::class, 'show'])->name('admin.groups.show');
        Route::post('/admin/groups/{group}/attach', [GroupController::class, 'attachAthlete'])->name('admin.groups.attach');
        Route::delete('/admin/groups/{group}/detach/{athlete}', [GroupController::class, 'detachAthlete'])->name('admin.groups.detach');

        Route::get('/admin/schedule', [ScheduleController::class, 'index'])->name('admin.schedule');
        Route::post('/admin/schedule', [ScheduleController::class, 'store'])->name('admin.schedule.store');
        Route::patch('/admin/schedule/{schedule}', [ScheduleController::class, 'update'])->name('admin.schedule.update');
        Route::delete('/admin/schedule/{schedule}', [ScheduleController::class, 'destroy'])->name('admin.schedule.destroy');
        Route::get('/admin/locations', [LocationController::class, 'index'])->name('admin.locations');
        Route::post('/admin/locations', [LocationController::class, 'store'])->name('admin.locations.store');
        Route::patch('/admin/locations/{location}', [LocationController::class, 'update'])->name('admin.locations.update');
        Route::delete('/admin/locations/{location}', [LocationController::class, 'destroy'])->name('admin.locations.destroy');

        Route::get('/admin/attendance/{schedule}', [AttendanceController::class, 'show'])->name('admin.attendance.show');
        Route::post('/admin/attendance/{schedule}', [AttendanceController::class, 'store'])->name('admin.attendance.store');
        Route::get('/admin/attendance-journal', [AttendanceController::class, 'journal'])->name('admin.attendance.journal');

        Route::get('/admin/coaches', [UserManagementController::class, 'index'])->name('admin.coaches');
        Route::post('/admin/coaches', [UserManagementController::class, 'storeCoach'])->name('admin.coaches.store');
        Route::patch('/admin/coaches/{coach}', [UserManagementController::class, 'updateCoach'])->name('admin.coaches.update');
        Route::patch('/admin/coaches/{coach}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('admin.coaches.toggle-status');
        Route::delete('/admin/coaches/{coach}', [UserManagementController::class, 'destroyCoach'])->name('admin.coaches.destroy');
        Route::get('/admin/finance', [FinanceController::class, 'index'])->name('admin.finance');
        Route::patch('/admin/finance/{athlete}', [FinanceController::class, 'update'])->name('admin.finance.update');
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
