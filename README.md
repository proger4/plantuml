# PlantUML Studio

Минималистичная студия для редактирования PlantUML-диаграмм с live-preview и WebSocket-коллаборацией.

## Что в репозитории

- `src/` — доменная и прикладная логика (UseCases, репозитории, коллаборация).
- `index.php` — HTTP entrypoint для API.
- `ws-server.php` — WebSocket сервер для совместного редактирования.
- `ui/` — фронтенд на React + Vite.
- `migrations/` и `bin/migrate.php` — инициализация SQLite.
- `docs/` — UML/PlantUML схемы.
- `old/` — предыдущий прототип (оставлен как архив).

## Быстрый старт (локально, без Docker)

Требования:
- PHP `>= 8.2`
- Composer
- Node.js `>= 18`
- npm

1. Установить PHP-зависимости:
```bash
composer install
```

2. Поднять SQLite схему:
```bash
php bin/migrate.php
```

3. Запустить HTTP API:
```bash
php -S 127.0.0.1:8000 index.php
```

4. В отдельном терминале запустить WebSocket сервер:
```bash
php ws-server.php
```

5. В отдельном терминале запустить UI:
```bash
cd ui
npm install
npm run dev
```

UI по умолчанию: `http://127.0.0.1:5173`

## Статус Docker

`docker/` и `docker-compose.yaml` зарезервированы под будущую контейнеризацию.
Когда конфигурация будет заполнена, в README будет добавлен отдельный раздел с Docker-запуском.
