<?php

namespace App\Support;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleConflictChecker
{
    /**
     * @return array<int, string>  'location' | 'coach'
     */
    public static function conflicts(Request $request, ?int $ignoreScheduleId = null): array
    {
        $base = Schedule::query()
            ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
            ->where('lesson_date', $request->lesson_date)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            });

        $result = [];

        if ((clone $base)->where('location_id', $request->location_id)->exists()) {
            $result[] = 'location';
        }

        if ((clone $base)->where('coach_id', $request->coach_id)->exists()) {
            $result[] = 'coach';
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $conflicts
     */
    public static function message(array $conflicts): string
    {
        if (in_array('location', $conflicts, true) && in_array('coach', $conflicts, true)) {
            return 'В это время заняты и зал, и тренер. Выберите другое время, зал или тренера.';
        }

        if (in_array('location', $conflicts, true)) {
            return 'Зал занят в выбранное время. Выберите другой зал или время.';
        }

        if (in_array('coach', $conflicts, true)) {
            return 'Тренер занят в выбранное время. Выберите другого тренера или время.';
        }

        return 'Конфликт расписания.';
    }
}
