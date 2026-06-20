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
        <p><strong>Чистая прибыль:</strong> {{ number_format($total_profit, 2, ',', ' ') }} ₽</p>
        <p><strong>Списания (брутто):</strong> {{ number_format($gross_profit, 2, ',', ' ') }} ₽</p>
        <p><strong>Возвраты:</strong> {{ number_format($total_refunds, 2, ',', ' ') }} ₽</p>
        <p><strong>Пополнения:</strong> {{ number_format($total_deposits, 2, ',', ' ') }} ₽</p>
        <p><strong>Списаний:</strong> {{ $operations_count }}</p>
        <p>Тренировки: {{ number_format($by_source['training'] ?? 0, 2, ',', ' ') }} ₽</p>
        <p>Мероприятия: {{ number_format($by_source['event'] ?? 0, 2, ',', ' ') }} ₽</p>
        <p>Ручные: {{ number_format($by_source['manual'] ?? 0, 2, ',', ' ') }} ₽</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>Спортсмен</th>
                <th>Тип</th>
                <th>Сумма</th>
                <th>Источник</th>
                <th>Группа</th>
                <th>Зал</th>
                <th>Тренер</th>
                <th>Основание</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['athlete_name'] }}</td>
                    <td>{{ $row['operation_label'] }}</td>
                    <td>
                        @if(($row['operation_type'] ?? '') === 'refund')
                            −{{ number_format($row['amount'], 2, ',', ' ') }}
                        @elseif(($row['operation_type'] ?? '') === 'deposit')
                            +{{ number_format($row['amount'], 2, ',', ' ') }}
                        @else
                            {{ number_format($row['amount'], 2, ',', ' ') }}
                        @endif
                    </td>
                    <td>{{ $row['source_label'] }}</td>
                    <td>{{ $row['group'] ?? '' }}</td>
                    <td>{{ $row['location'] ?? '' }}</td>
                    <td>{{ $row['coach'] ?? '' }}</td>
                    <td>{{ $row['reason'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
