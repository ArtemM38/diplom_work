<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аккаунт неактивен</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; max-width: 560px; width: 100%; text-align: center; }
        .btn { display: inline-block; margin-top: 12px; padding: 10px 16px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 10px; border: 0; cursor: pointer; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Аккаунт неактивен</h1>
            <p>Ваш аккаунт временно отключен администратором. Доступ к системе ограничен.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn" type="submit">Выйти из аккаунта</button>
            </form>
        </div>
    </div>
</body>
</html>
