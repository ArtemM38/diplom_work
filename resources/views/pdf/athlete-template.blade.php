<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.5; }
        h1 { font-size: 16px; margin-bottom: 12px; }
        .meta { color: #666; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Сформировано: {{ $generatedAt->format('d.m.Y H:i') }}</div>
    <p>ФИО (Им.п.): {{ trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')) }}</p>
    <p>ФИО (Род.п.): {{ $athlete->full_name_gen ?? '—' }}</p>
    <p>ФИО (Дат.п.): {{ $athlete->full_name_dat ?? '—' }}</p>
    <p>Дата рождения: {{ $athlete->birth_date ?? '—' }}</p>
    <p>Телефон: {{ $athlete->phone ?? '—' }}</p>
    <p>Адрес: {{ $athlete->registration_address ?? '—' }}</p>
    <hr>
    <p>Текст шаблона №{{ $template }} автоматически заполнен данными из профиля спортсмена.</p>
</body>
</html>
