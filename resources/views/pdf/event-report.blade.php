<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчёт по мероприятию</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .export-meta { margin-bottom: 8px; }
        .export-meta p { margin: 2px 0; }
    </style>
</head>
<body>
    <h1>{{ $event->name }}</h1>
    <p class="meta">
        Тип: {{ $event->eventType?->name ?? '—' }} |
        Уровень: {{ $event->eventLevel?->name ?? '—' }} |
        Дата: {{ $event->event_date ?? '—' }} |
        Место: {{ $event->event_place ?? '—' }} |
        Ведущий: {{ $event->eventHost?->full_name ?? '—' }} |
        Стоимость: {{ $event->cost }} ₽
    </p>
    @include('pdf.partials.export-meta')

    <table>
        <thead>
            <tr>
                <th>Спортсмен</th>
                <th>Результат</th>
                <th>Место</th>
                <th>Разряд</th>
                <th>Сертификат</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ trim(($row->athlete->last_name_nom ?? '') . ' ' . ($row->athlete->first_name_nom ?? '')) }}</td>
                    <td>{{ $row->result_label ?? '—' }}</td>
                    <td>{{ $row->result_place ?? '—' }}</td>
                    <td>{{ $row->resultRank?->name ?? '—' }}</td>
                    <td>{{ $row->certificate_id ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Нет участников</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
