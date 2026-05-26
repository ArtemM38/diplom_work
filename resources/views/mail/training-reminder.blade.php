<x-mail::message>
# Напоминание о тренировке

Здравствуйте, {{ $user->display_name ?? $user->name }}!

Через **2 часа** у вас тренировка:

- **Группа:** {{ $groupName }}
- **Зал:** {{ $locationName }}
- **Время:** {{ $lessonAt->format('d.m.Y H:i') }}
@if ($coachName)
- **Тренер:** {{ $coachName }}
@endif

<x-mail::button :url="url('/athlete/schedule-calendar')">
Расписание
</x-mail::button>

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
