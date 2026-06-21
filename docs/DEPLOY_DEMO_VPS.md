# Демо CRM в интернет (VPS, ~2–4 часа)

Подходит: **Beget VPS**, Timeweb Cloud, Selectel и любой VPS с Ubuntu + SSH.  
Не подходит: обычный «хостинг сайта» с `public_html` (тот, где был 403).

После настройки заказчики открывают ссылку вида `https://ваш-домен.ru` и входят под тестовыми логинами.

---

## Часть 0. Что подготовить на компьютере

1. Архив проекта **без** `node_modules` и без `.git` (или доступ по GitHub).
2. Локально выполнить (чтобы не ставить Node на сервер):

```bash
cd путь/к/diplom_work
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

В архиве должны быть папки: `vendor/`, `public/build/`.

3. Файл `.env` для сервера (черновик) — см. часть 4.

---

## Часть 1. Заказать VPS (Beget)

1. [beget.com](https://beget.com) → **VPS** (не «Хостинг»).
2. Тариф минимальный (1–2 GB RAM достаточно для демо).
3. ОС: **Ubuntu 22.04** или **24.04**.
4. Запомнить: **IP сервера**, **логин/пароль SSH** (придут на почту).

Привязать домен (если есть) или использовать технический `*.beget.tech` из панели.

---

## Часть 2. Подключиться по SSH

**Windows:** PowerShell или [PuTTY](https://www.putty.org/).

```bash
ssh root@IP_ВАШЕГО_СЕРВЕРА
```

(или `ssh u91495y1@IP` — как в письме от Beget)

---

## Часть 3. Установить PHP, Nginx, MySQL (один раз)

На сервере (Ubuntu):

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-gd unzip git composer
```

Запустить MySQL и создать базу:

```bash
mysql -u root
```

В консоли MySQL:

```sql
CREATE DATABASE aikido_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm'@'localhost' IDENTIFIED BY 'ПридумайтеСложныйПароль123!';
GRANT ALL PRIVILEGES ON aikido_crm.* TO 'crm'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Часть 4. Загрузить проект на сервер

### Вариант А — через SFTP (Sprut.io / FileZilla)

Папка на сервере:

```text
/var/www/aikido-crm/
```

Сюда залить **весь** проект (включая `vendor/`, `public/build/`).

### Вариант Б — через Git

```bash
mkdir -p /var/www/aikido-crm
cd /var/www/aikido-crm
git clone https://ВАШ_РЕПОЗИТОРИЙ.git .
composer install --no-dev --optimize-autoloader
# build залить с ПК в public/build или: npm ci && npm run build
```

Права:

```bash
chown -R www-data:www-data /var/www/aikido-crm
chmod -R 775 /var/www/aikido-crm/storage /var/www/aikido-crm/bootstrap/cache
```

---

## Часть 5. Файл `.env` на сервере

```bash
cd /var/www/aikido-crm
cp .env.example .env
nano .env
```

Минимум для демо:

```env
APP_NAME="Айкидо CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://u91495y1.beget.tech

APP_KEY=
# сгенерировать: php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aikido_crm
DB_USERNAME=crm
DB_PASSWORD=ПридумайтеСложныйПароль123!

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
```

Почту (Yandex) можно включить позже — для просмотра CRM не обязательна.

---

## Часть 6. Laravel: миграции и демо-данные

```bash
cd /var/www/aikido-crm
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

После `db:seed` появится админ (из `AdminSeeder`):

| Роль  | Email           | Пароль     |
|-------|-----------------|------------|
| Админ | admin@test.ru   | qawsedrf   |

**Для заказчика:** смените пароль или создайте отдельного admin в интерфейсе.

---

## Часть 7. Nginx — корень на `public`

```bash
nano /etc/nginx/sites-available/aikido-crm
```

Вставить (замените `server_name`):

```nginx
server {
    listen 80;
    server_name u91495y1.beget.tech ваш-домен.ru;
    root /var/www/aikido-crm/public;

    add_header X-Frame-Options "SAMEORIGIN";
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Включить сайт:

```bash
ln -s /etc/nginx/sites-available/aikido-crm /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

Откройте в браузере `http://IP` или домен — должна быть страница входа.

### HTTPS (рекомендуется)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d ваш-домен.ru
```

В `.env` обновить `APP_URL=https://ваш-домен.ru` и снова:

```bash
php artisan config:cache
```

---

## Часть 8. Cron (уведомления спортсменам)

```bash
crontab -e
```

Добавить строку:

```cron
* * * * * cd /var/www/aikido-crm && php artisan schedule:run >> /dev/null 2>&1
```

---

## Часть 9. Что отдать заказчикам

1. Ссылка: `https://ваш-домен.ru`
2. PDF/сообщение с логинами (admin + при необходимости coach/athlete — создайте в CRM).
3. Кратко: «это демо-стенд, данные тестовые».

---

## Обновление после правок в коде

На компьютере:

```bash
npm run build
```

Залить на сервер изменённые файлы + папку `public/build/`.

На сервере:

```bash
cd /var/www/aikido-crm
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

---

## Часть 10. PDF заявлений из профиля (LibreOffice)

На **Windows** (локально) PDF делается через Microsoft Word/Excel — выглядит как в шаблоне.

На **Linux VPS** без LibreOffice используется упрощённая конвертация (Word → HTML → PDF): ломается вёрстка, шрифты, фото, таблицы.

**Установка на Ubuntu (один раз):**

```bash
apt update
apt install -y libreoffice-writer libreoffice-calc
```

В `.env` на сервере (если бинарник не в PATH):

```env
LIBREOFFICE_BINARY=/usr/bin/libreoffice
```

Проверка от пользователя веб-сервера:

```bash
sudo -u www-data HOME=/tmp libreoffice --headless --convert-to pdf --outdir /tmp /path/to/test.docx
ls -la /tmp/test.pdf
```

После правок:

```bash
php artisan config:cache
```

Если LibreOffice недоступен — скачивайте **DOCX** (Word), а PDF делайте вручную в Word/LibreOffice на компьютере.

---

## Если что-то не работает

| Симптом | Что проверить |
|---------|----------------|
| PDF заявлений «кривой» | установлен ли LibreOffice, `LIBREOFFICE_BINARY`, тест `sudo -u www-data libreoffice ...` |
| 502 / пусто | `systemctl status php8.3-fpm nginx` |
| 500 | `tail -50 /var/www/aikido-crm/storage/logs/laravel.log` |
| Нет стилей | есть ли `public/build/manifest.json` |
| Ошибка БД | `DB_*` в `.env`, запущен ли MySQL |

---

## После защиты диплома (тот же сервер)

1. Сменить все пароли, удалить `admin@test.ru` или сменить пароль.
2. `APP_DEBUG=false`, бэкапы MySQL (cron `mysqldump`).
3. Настроить почту — `docs/MAIL_YANDEX.md`.
4. Привязать боевой домен клуба.
