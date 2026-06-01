/**
 * Форматирует дату без сдвига из-за часового пояса (для Y-m-d и ISO-строк).
 */
export function formatDisplayDate(value) {
    if (!value) {
        return '—';
    }

    const string = String(value);
    const match = string.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (match) {
        return `${match[3]}.${match[2]}.${match[1]}`;
    }

    const parsed = new Date(string);
    if (Number.isNaN(parsed.getTime())) {
        return string;
    }

    return parsed.toLocaleDateString('ru-RU');
}
