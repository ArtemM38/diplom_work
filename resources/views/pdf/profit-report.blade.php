<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Финансовый отчёт</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 14px; }
        .summary { margin: 12px 0; }
        .summary p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Финансовый отчёт (прибыль)</h1>
    <p>Период: {{ $filters['date_from'] }} — {{ $filters['date_to'] }}</p>
    @include('pdf.partials.export-meta')
    <div class="summary">
        <p><strong>Общая прибыль:</strong> {{ number_format($total_profit, 0, ',', ' ') }} ₽</p>
        <p><strong>Операций:</strong> {{ $operations_count }}</p>
        <p>Тренировки: {{ number_format($by_source['training'] ?? 0, 0, ',', ' ') }} ₽</p>
        <p>Мероприятия: {{ number_format($by_source['event'] ?? 0, 0, ',', ' ') }} ₽</p>
        <p>Ручные: {{ number_format($by_source['manual'] ?? 0, 0, ',', ' ') }} ₽</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>Спортсмен</th>
                <th>Сумма</th>
                <th>Источник</th>
                <th>Группа</th>
                <th>Тренер</th>
                <th>Основание</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['athlete_name'] }}</td>
                    <td>{{ number_format($row['amount'], 0, ',', ' ') }}</td>
                    <td>{{ $row['source_label'] }}</td>
                    <td>{{ $row['group'] ?? '' }}</td>
                    <td>{{ $row['coach'] ?? '' }}</td>
                    <td>{{ $row['reason'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
