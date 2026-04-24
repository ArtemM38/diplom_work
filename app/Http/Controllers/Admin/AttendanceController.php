<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    // Открыть страницу отметки для конкретной тренировки
    public function show(Schedule $schedule)
    {
        // Загружаем тренировку вместе с группой и её спортсменами
        $schedule->load(['group.athletes', 'attendances']);

        return Inertia::render('Admin/Attendance/Mark', [
            'schedule' => $schedule,
            // Передаем существующих спортсменов группы
            'athletes' => $schedule->group->athletes,
            // Передаем уже сохраненные отметки (если они есть)
            'existingAttendances' => $schedule->attendances->pluck('status', 'athlete_id')
        ]);
    }

    // Сохранить отметки
    public function store(Request $request, Schedule $schedule)
    {
        // Принимаем массив вида { athlete_id: 'Я', athlete_id: 'Н' }
        foreach ($request->attendance as $athleteId => $status) {
            Attendance::updateOrCreate(
                ['schedule_id' => $schedule->id, 'athlete_id' => $athleteId],
                ['status' => $status]
            );
        }

        return redirect()->route('admin.schedule')->with('success', 'Посещаемость сохранена');
    }
}
