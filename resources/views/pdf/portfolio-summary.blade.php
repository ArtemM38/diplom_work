<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; }
        td { font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta export-meta">
        @include('pdf.partials.export-meta')
    </div>

    <table>
        <thead>
            <tr>
                <th>Спортсмен</th>
                <th>Мероприятие</th>
                <th>Тип</th>
                <th>Уровень</th>
                <th>Дата/Период</th>
                <th>Место проведения</th>
                <th>Ведущий</th>
                <th>Результат</th>
                <th>Место</th>
                <th>Разряд</th>
                <th>Сертификат</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $item)
                <tr>
                    <td>{{ trim(($item->athlete->last_name_nom ?? '') . ' ' . ($item->athlete->first_name_nom ?? '')) }}</td>
                    <td>{{ $item->event_name }}</td>
                    <td>{{ $item->eventType?->name }}</td>
                    <td>{{ $item->eventLevel?->name }}</td>
                    <td>{{ $item->event_date ?: $item->event_period }}</td>
                    <td>{{ $item->event_place }}</td>
                    <td>{{ $item->eventHost?->full_name }}</td>
                    <td>{{ $item->result_label }}</td>
                    <td>{{ $item->result_place }}</td>
                    <td>{{ $item->resultRank?->name }}</td>
                    <td>{{ $item->certificate_id }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Нет данных для выгрузки</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
