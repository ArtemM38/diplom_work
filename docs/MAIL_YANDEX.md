# Почта через Yandex для уведомлений CRM

## 1. Пароль приложения в Yandex

1. Откройте [Яндекс ID → Пароли приложений](https://id.yandex.ru/security/app-passwords).
2. Нажмите **Создать пароль приложения**.
3. Тип: **Почта**.
4. Скопируйте пароль (обычный пароль от входа **не подойдёт**, если включена двухфакторная защита).

## 2. Доступ для почтовых программ

1. [Яндекс.Почта](https://mail.yandex.ru) → **Настройки** → **Почтовые программы**.
2. Включите **С сервера imap.yandex.ru по протоколу IMAP** (без этого SMTP часто не работает).

## 3. Настройка `.env`

Скопируйте блок из `.env.example` в ваш `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yandex.ru
MAIL_PORT=465
MAIL_USERNAME=ваш-ящик@yandex.ru
MAIL_PASSWORD=пароль-приложения-из-шага-1
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=ваш-ящик@yandex.ru
MAIL_FROM_NAME="Айкидо CRM"
```

**Важно:** `MAIL_USERNAME` и `MAIL_FROM_ADDRESS` должны быть **одним и тем же** ящиком Yandex.

### Вариант через порт 587 (STARTTLS)

```env
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

## 4. Применить настройки

```bash
php artisan config:clear
php artisan mail:test ваш@yandex.ru
```

Письмо должно прийти в течение 1–2 минут (проверьте «Спам»).

## 5. Автоматические уведомления CRM

Команда (документы + тренировки за 2 часа):

```bash
php artisan notifications:dispatch-athletes
```

В cron на сервере (каждую минуту):

```bash
* * * * * cd /путь/к/diplom_work && php artisan schedule:run >> /dev/null 2>&1
```

Локально:

```bash
php artisan schedule:work
```

## Частые ошибки

| Ошибка | Решение |
|--------|---------|
| `535 authentication failed` | Неверный пароль; используйте **пароль приложения**, не пароль от аккаунта |
| `530 Authentication required` | Пустой `MAIL_USERNAME` / `MAIL_PASSWORD` или не выполнен `config:clear` |
| Письмо не приходит | Спам; `MAIL_FROM_ADDRESS` ≠ ящику Yandex; не включён IMAP в настройках почты |
| Таймаут на `yandex.ru` | В `MAIL_HOST` должно быть `smtp.yandex.ru`, не `yandex.ru` |
