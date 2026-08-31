# Эксплуатация

## Окружение (env)

Полный список — в `.env.example`; ниже назначение по группам.

| Группа | Переменные | Назначение |
|---|---|---|
| Приложение | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Стандартные Laravel |
| База (MySQL) | `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Основной стек по ТЗ; значения использует и mysql-контейнер |
| MySQL root | `MYSQL_ROOT_PASSWORD` | Пароль root контейнера (по умолчанию `root`) |
| Кэш | `CACHE_STORE=redis`, `REDIS_CLIENT`, `REDIS_HOST=redis`, `REDIS_PASSWORD`, `REDIS_PORT` | Кэш доступности (Redis) |
| Очередь/сессии | `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=database` | Очередь на Redis; сессии в БД |
| Docker Compose | `DOCKER_APP_NAME`, `DOCKER_NGINX_PORT=5580`, `DOCKER_MYSQL_PORT=53306`, `DOCKER_REDIS_PORT=56379`, `DOCKER_WORKSPACE_TIMEZONE`, `DOCKER_MYSQL_STORAGE`, `DOCKER_REDIS_STORAGE` | Имена, порты и storage-пути контейнеров |
| Стресс-проверка | `STRESS_API_BASE` | Базовый URL для `holds:stress`; по умолчанию `http://nginx:80/api` (изнутри контейнера) |

Локальный запуск без docker: в `.env.example` закомментирован sqlite-блок (`DB_CONNECTION=sqlite`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`).

## Запуск

```bash
make up        # поднять стек: nginx:5580, app, mysql:53306, redis:56379
make setup     # up + composer install + key:generate + storage:link
make fresh     # migrate:fresh --seed (10 слотов случайной вместимости)
make down      # погасить
```

Артизан — только изнутри контейнера: `docker compose exec app php artisan ...`

## Проверки

| Команда | Что |
|---|---|
| `docker compose exec app php artisan test` | PHPUnit: sqlite `:memory:`, кэш array |
| `make lint` | phpstan level 6 (larastan); конфиг — `phpstan.neon` |
| `make fix` | pint по `app config database routes tests` |

## Ручные сценарии

### Конкурентная проверка против перепродажи (`holds:stress`)

Проверяет меру характеристики «целостность данных» на живом стеке: 200 конкурентных подтверждений на слот вместимостью 10 → подтверждено ровно 10.

```bash
# слот должен быть свежим (без подтверждённых удержаний) — иначе результат искажён:
docker compose exec app php artisan tinker --execute="echo App\Models\Slot::query()->create(['capacity' => 10])->id;"
docker compose exec app php artisan holds:stress <slot_id> 200
```

Ожидаемый вывод:

```
Создание 200 удержаний...
Параллельный confirm (20 потоков)...
  200: 10
  409: 190
OK: confirmed = 10, ожидалось = 10
```

Код выхода 0 при `confirmed == min(capacity, requests)`, иначе 1. Команда ходит в API через `STRESS_API_BASE` (изнутри контейнера — `http://nginx:80/api`).

### Проверка API вручную

Сценарии (создание, повтор ключа, подтверждение, отмена, конфликт) — curl-примеры в `README.md`.

## Крон и воркеры

- Крон-задач нет (`routes/console.php` — только `inspire`).
- Фоновая очистка просроченных удержаний **осознанно не делается** (ADR 0001): удержание не резервирует место, просрочка — производная от `expires_at`, записи безвредны.
- Очередь (`QUEUE_CONNECTION=redis`) настроена, но задачи не публикуются.
