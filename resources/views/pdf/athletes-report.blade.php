<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчёт по спортсменам</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        th { background: #f3f4f6; }
        .export-meta { margin-bottom: 10px; font-size: 10px; }
        .export-meta p { margin: 2px 0; }
    </style>
</head>
<body>
    <h1>Отчёт по спортсменам</h1>
    <p>Период: {{ $filters['date_from'] }} — {{ $filters['date_to'] }}</p>
    @include('pdf.partials.export-meta')
    <table>
        <thead>
            <tr>
                <th>ФИО</th>
                <th>Разряд</th>
                <th>Группы</th>
                <th>Мероприятие</th>
                <th>Дата</th>
                <th>Результат</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['athlete_name'] }}</td>
                    <td>{{ $row['current_rank'] }}</td>
                    <td>{{ $row['groups'] }}</td>
                    <td>{{ $row['event_name'] }}</td>
                    <td>{{ $row['event_date'] }}</td>
                    <td>{{ $row['result'] }} {{ $row['result_place'] ? '('.$row['result_place'].')' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
