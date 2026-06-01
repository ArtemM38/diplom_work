<div class="export-meta">
    <p><strong>Дата формирования:</strong> {{ $generatedAt->format('d.m.Y H:i') }} ({{ $timezoneLabel ?? 'Иркутск (UTC+8)' }})</p>
    <p><strong>Сформировал:</strong> {{ $generatedBy ?? '—' }}</p>
</div>
