<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Schedule/Index', [
            'schedules' => Schedule::with(['group', 'location', 'coach'])->get(),
            'groups' => Group::where('status', 'active')->get(),
            'locations' => Location::all(),
            'coaches' => User::where('role', 'coach')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'location_id' => 'required|exists:locations,id',
            'coach_id' => 'required|exists:users,id',
            'lesson_date' => 'required|date', // Обязательная дата
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        // Вычисляем день недели (1-7) из даты для совместимости
        $dayOfWeek = \Carbon\Carbon::parse($request->lesson_date)->dayOfWeekIso;

        // Проверка конфликтов в этом зале ИМЕННО в этот день
        $conflict = Schedule::where('lesson_date', $request->lesson_date)
            ->where(function ($q) use ($request) {
                $q->where('location_id', $request->location_id)
                    ->orWhere('coach_id', $request->coach_id);
            })
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time]);
            })
            ->first();

        if ($conflict) {
            return back()->withErrors(['conflict' => 'В этот день зал или тренер уже заняты в это время!']);
        }

        Schedule::create(array_merge($request->all(), ['day_of_week' => $dayOfWeek]));
        return back()->with('success', 'Занятие создано');
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();
        return redirect()->back();
    }

}
