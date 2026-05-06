<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p>Сформировано: {{ $generatedAt->format('d.m.Y H:i') }}</p>
    <p>ФИО (Им.п.): {{ trim($athlete->last_name_nom . ' ' . $athlete->first_name_nom . ' ' . ($athlete->middle_name_nom ?? '')) }}</p>
    <p>ФИО (Род.п.): {{ $athlete->full_name_gen ?? '—' }}</p>
    <p>ФИО (Дат.п.): {{ $athlete->full_name_dat ?? '—' }}</p>
    <p>Дата рождения: {{ $athlete->birth_date ?? '—' }}</p>
    <p>Телефон: {{ $athlete->phone ?? '—' }}</p>
    <p>Адрес: {{ $athlete->registration_address ?? '—' }}</p>
    <p>Шаблон №{{ $template }} заполнен автоматически.</p>
</body>
</html>
