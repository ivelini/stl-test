# stl-test — сервис бронирования слотов

Тестовое задание: минимальный сервис бронирования слотов с горячим кэшем и защитой от перепродажи. Слот — окно склада/доставки с вместимостью; пользователь создаёт удержание (5 минут годности), затем подтверждает или отменяет его. Полное ТЗ — `documentations/tz/test-task.md`.

**Соответствие ТЗ:** «SlotService» из задания трактуется как сервисный слой — каталог `app/Services/SlotService/` (ADR 0005); класса с именем `SlotService` нет.

## Запуск (Docker)

```bash
make setup        # up + composer install + key:generate + storage:link
make fresh        # migrate:fresh --seed (10 слотов)
```

API — на `http://localhost:5580/api` (порт из `DOCKER_NGINX_PORT`). Артизан — через `docker compose exec app php artisan`.

## Примеры вызовов

Создание удержания (обязателен заголовок `Idempotency-Key`):

```bash
curl -X POST http://localhost:5580/api/slots/1/hold \
  -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111"
# 201: {"id":1,"slot_id":1,"idempotency_key":"1111...","status":"held","expires_at":"...","created_at":"...","updated_at":"..."}
```

Повтор с тем же ключом — исходный результат (идемпотентность), 200 вместо 201:

```bash
curl -X POST http://localhost:5580/api/slots/1/hold \
  -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111"
# 200: тот же билет
```

Подтверждение удержания (атомарно занимает место; при исчерпании — 409):

```bash
curl -X POST http://localhost:5580/api/holds/1/confirm
# 200: {"id":1,...,"status":"confirmed",...}
```

Отмена (место возвращается в доступность автоматически — остаток вычисляется):

```bash
curl -X DELETE http://localhost:5580/api/holds/1
# 204
```

Конфликт перепродажи (слот вместимостью 1, второе подтверждение):

```bash
curl -X POST http://localhost:5580/api/holds/2/confirm
# 409: {"message":"Capacity exhausted"}
```

Доступность (кэш 5–15 с, защита от лавины):

```bash
curl http://localhost:5580/api/slots/availability
# 200: [{"slot_id":1,"capacity":10,"remaining":9}, ...]
```

## Тесты и проверки

```bash
docker compose exec app php artisan test   # PHPUnit
make lint                                  # phpstan level 6
make fix                                   # pint
```

Конкурентная проверка против перепродажи (200 параллельных подтверждений на слот вместимостью 10) — `holds:stress`, см. `documentations/operations.md`.

## Документация проекта

- `documentations/tz/` — ТЗ и его трактовка
- `documentations/adr/` — архитектурные решения (индекс — в `CLAUDE.md`)
- `documentations/architecture/` — характеристики, компоненты, стиль, схема
- `documentations/operations.md` — эксплуатация: env, запуск, ручные сценарии
